<?php

namespace App\Jobs;

use App\Models\CutJob;
use App\Notifications\CutJobNotification;
use App\Services\AIService;
use App\Services\ConfidenceService;
use App\Services\ImageProcessingService;
use App\Services\PdfService;
use App\Services\VectorizationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Orchestrates the full CutContour processing pipeline for a single job (PRD §7, §10).
 *
 * Pipeline steps (persisted to `cut_jobs.pipeline_step` so the UI can render live progress):
 *   1. Preprocess (ImageMagick)
 *   2. Confidence check → Fast or AI-Enhanced path
 *   3. [AI path] Subject isolation → normalise output
 *   4. Vectorise (Potrace)
 *   5. Assemble PDF (CutContour spot colour layer)
 *
 * Idempotency: entering handle() locks the row and no-ops if the job already
 * carries a completed `output_path`, so a redelivered message never double-
 * charges AI or overwrites output.
 */
class ProcessCutJob implements ShouldQueue
{
    use Queueable;

    public const STEP_PREPROCESS = 1;

    public const STEP_CONFIDENCE = 2;

    public const STEP_AI = 3;

    public const STEP_MASK = 4;

    public const STEP_VECTORIZE = 5;

    public const STEP_ASSEMBLE = 6;

    /** Abort the job if it runs longer than 5 minutes. */
    public int $timeout = 300;

    /** Do not retry — pipeline failures are deterministic and a second attempt wastes time. */
    public int $tries = 1;

    /** Fail the job immediately without re-queuing. */
    public int $maxExceptions = 1;

    /** Set inside handle() to suppress the duplicate notification from failed(). */
    private bool $failureNotified = false;

    public function __construct(
        public readonly CutJob $cutJob,
        public int $targetWidthPx = 0,
        public int $targetHeightPx = 0,
        public int $offsetPx = 0,
    ) {}

    public function handle(
        ImageProcessingService $imageProcessor,
        ConfidenceService $confidenceService,
        AIService $aiService,
        VectorizationService $vectorizer,
        PdfService $pdfService,
    ): void {
        $startedAt = microtime(true);
        $jobId = $this->cutJob->id;
        $userId = $this->cutJob->user_id;

        // Idempotency guard: a redelivered message must not re-run a completed
        // job, and must not race a worker that still owns the lease. Lock the
        // row and re-read status inside a short transaction.
        //
        // Lease claim is atomic: on entry we bump pipeline_step to STEP_PREPROCESS
        // (1). Any concurrent worker that reaches this transaction later will
        // observe pipeline_step >= 1 with a fresh updated_at and skip. If the
        // lease is stale (updated_at older than timeout + grace) we assume the
        // prior worker died and take over.
        $shouldProcess = DB::transaction(function () use ($jobId) {
            $fresh = CutJob::whereKey($this->cutJob->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->status === 'completed' || $fresh->output_path !== null) {
                Log::info('ProcessCutJob: skipped (already completed or missing)', ['job_id' => $jobId]);

                return false;
            }

            $pipelineStep = (int) ($fresh->pipeline_step ?? 0);

            if ($fresh->status === 'processing' && $pipelineStep >= 1) {
                $leaseExpiry = now()->subSeconds($this->timeout + 30);

                if ($fresh->updated_at?->gt($leaseExpiry)) {
                    Log::info('ProcessCutJob: skipped (fresh lease held by another worker)', [
                        'job_id' => $jobId,
                        'pipeline_step' => $pipelineStep,
                        'updated_at' => $fresh->updated_at?->toIso8601String(),
                    ]);

                    return false;
                }

                Log::info('ProcessCutJob: taking over stale lease from crashed worker', [
                    'job_id' => $jobId,
                    'pipeline_step' => $pipelineStep,
                    'lease_expired_before' => $leaseExpiry->toIso8601String(),
                    'updated_at' => $fresh->updated_at?->toIso8601String(),
                ]);
            }

            // Claim the lease atomically: set pipeline_step >= 1 INSIDE the
            // transaction so a second worker's read sees a claimed row.
            $fresh->forceFill([
                'status' => 'processing',
                'pipeline_step' => self::STEP_PREPROCESS,
                'error_message' => null,
            ])->save();

            return true;
        });

        if (! $shouldProcess) {
            return;
        }

        Log::info('ProcessCutJob: started', [
            'job_id' => $jobId,
            'file_type' => $this->cutJob->file_type,
            'original_name' => $this->cutJob->original_name,
        ]);

        $sourcePath = Storage::path($this->cutJob->file_path);
        $workDir = Storage::path("users/{$userId}/jobs/{$jobId}/work");

        try {
            // ── Step 1: Preprocess ────────────────────────────────────────────────
            $this->recordStep(self::STEP_PREPROCESS);

            // PDF and AI uploads cannot be fed directly to ImageMagick — the
            // hardened storage/imagemagick/policy.xml blocks the PDF coder to
            // prevent ImageTragick-class RCE. Rasterise the first page with
            // Ghostscript (sandboxed via -dSAFER) and hand the PNG preview to
            // the raster pipeline. The final assemble() step still receives
            // the original PDF path so PdfService's PDF short-circuit runs.
            $rasterSource = $sourcePath;
            if (in_array($this->cutJob->file_type, ['pdf', 'ai'], true)) {
                $rasterSource = $this->rasterizePdfPreview($sourcePath, $workDir);
            }

            $preprocessed = $imageProcessor->preprocess(
                $rasterSource,
                $workDir,
                $this->targetWidthPx > 0 ? $this->targetWidthPx : null,
                $this->targetHeightPx > 0 ? $this->targetHeightPx : null,
            );

            // ── Step 2: Confidence check ──────────────────────────────────────────
            $this->recordStep(self::STEP_CONFIDENCE);
            $confidence = $confidenceService->evaluate($preprocessed['path'], $this->cutJob->file_type);
            $useAi = $confidence['useAi'];
            $aiFallback = false;

            Log::info('ProcessCutJob: confidence evaluated', [
                'job_id' => $jobId,
                'score' => $confidence['score'],
                'use_ai' => $useAi,
            ]);

            // ── Step 3: AI-Enhanced path (with automatic Fast Path fallback) ───────
            $maskPath = null;

            if ($useAi) {
                $this->recordStep(self::STEP_AI);
                $aiResult = $aiService->analyze($preprocessed['path'], $workDir);

                if ($aiResult !== null) {
                    $maskPath = match ($aiResult['type']) {
                        'svg' => $this->vectorizeSvgToMask($aiResult['path'], $workDir, $imageProcessor),
                        'mask' => $imageProcessor->normalizeMask($aiResult['path'], $workDir),
                    };
                } else {
                    $aiFallback = true;
                    $useAi = false;
                    Log::warning('ProcessCutJob: AI fallback activated', ['job_id' => $jobId]);
                }
            }

            // ── Step 4: Fast Path mask (no AI or AI fallback) ────────────────────
            $this->recordStep(self::STEP_MASK);
            if ($maskPath === null) {
                $maskPath = $imageProcessor->generateMask($preprocessed['path'], $workDir);
            }

            if ($this->offsetPx > 0) {
                $maskPath = $imageProcessor->applyOffset($maskPath, $workDir, $this->offsetPx);
            }

            // ── Step 5: Vectorise ─────────────────────────────────────────────────
            $this->recordStep(self::STEP_VECTORIZE);
            $svgPath = $vectorizer->vectorize($maskPath, $workDir);

            // ── Step 6: Assemble PDF ──────────────────────────────────────────────
            $this->recordStep(self::STEP_ASSEMBLE);
            $absoluteOutputPath = $pdfService->assemble(
                originalPath: $sourcePath,
                svgPath: $svgPath,
                outputDir: Storage::path("users/{$userId}/jobs/{$jobId}"),
                originalName: $this->cutJob->original_name,
                width: $preprocessed['width'],
                height: $preprocessed['height'],
            );

            $relativeOutputPath = ltrim(
                str_replace(Storage::path(''), '', $absoluteOutputPath),
                '/\\',
            );

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->cutJob->forceFill([
                'status' => 'completed',
                'output_path' => $relativeOutputPath,
                'width' => $preprocessed['width'],
                'height' => $preprocessed['height'],
                'ai_used' => $useAi && ! $aiFallback,
                'confidence_score' => $confidence['score'],
                'processing_duration_ms' => $durationMs,
                'pipeline_step' => self::STEP_ASSEMBLE,
            ])->save();

            Log::info('ProcessCutJob: completed', [
                'job_id' => $jobId,
                'duration_ms' => $durationMs,
                'ai_used' => $useAi && ! $aiFallback,
                'ai_fallback' => $aiFallback,
                'output' => $relativeOutputPath,
            ]);

            $this->cutJob->user->notify(new CutJobNotification($this->cutJob, 'completed'));
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            Log::error('ProcessCutJob: failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'trace_first' => $e->getFile().':'.$e->getLine(),
                'duration_ms' => $durationMs,
            ]);

            $this->cutJob->forceFill([
                'status' => 'failed',
                'error_message' => $this->userSafeMessage($e),
                'error_detail' => $e->getMessage(),
                'processing_duration_ms' => $durationMs,
            ])->saveQuietly();

            $this->cutJob->user->notify(new CutJobNotification($this->cutJob, 'failed'));
            $this->failureNotified = true;

            // Rethrow so the queue marks the job failed and invokes failed().
            throw $e;
        } finally {
            $this->cleanupWorkDir($workDir);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ProcessCutJob: queue failure hook', [
            'job_id' => $this->cutJob->id,
            'error' => $exception->getMessage(),
        ]);

        if ($this->failureNotified) {
            return;
        }

        // handle() never ran (worker crash, deserialization error) — ensure the
        // record is failed and the user is told exactly once.
        $this->cutJob->forceFill([
            'status' => 'failed',
            'error_message' => $this->userSafeMessage($exception),
            'error_detail' => $exception->getMessage(),
        ])->saveQuietly();

        $this->cutJob->user->notify(new CutJobNotification($this->cutJob, 'failed'));
    }

    /**
     * User-facing error message — never expose internal paths or tool output.
     * Full detail is captured in `error_detail` for admin inspection.
     */
    private function userSafeMessage(Throwable $e): string
    {
        if ($e instanceof RuntimeException) {
            $msg = $e->getMessage();

            if (str_contains($msg, 'password-protected') || str_contains($msg, 'encrypted')) {
                return 'This PDF is password-protected. Please remove the password and try again.';
            }

            if (str_contains($msg, 'AI ')) {
                return 'AI analysis was unavailable. Please try again or upload a different file.';
            }

            if (str_contains($msg, 'too large')) {
                return 'The file was too large to process. Please upload a smaller file.';
            }
        }

        return 'Processing failed — please try a different file. Our team has been notified.';
    }

    private function recordStep(int $step): void
    {
        $this->cutJob->forceFill(['pipeline_step' => $step])->saveQuietly();
    }

    private function cleanupWorkDir(string $workDir): void
    {
        if (is_dir($workDir)) {
            try {
                File::deleteDirectory($workDir);
            } catch (Throwable $e) {
                Log::warning('ProcessCutJob: failed to clean work dir', [
                    'work_dir' => $workDir,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Rasterise the first page of a PDF (or AI, which is really a PDF) to PNG
     * via Ghostscript. Ghostscript is invoked with -dSAFER so it is safe on
     * untrusted uploads — this is the sandboxed alternative to ImageMagick's
     * PDF coder which the hardened policy.xml blocks.
     *
     * @throws RuntimeException
     */
    protected function rasterizePdfPreview(string $sourcePath, string $workDir): string
    {
        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new RuntimeException("Failed to create work dir: {$workDir}");
        }

        $previewPath = $workDir.'/preview.png';
        $gs = config('cutjob.binaries.gs', 'gs');
        $dpi = $this->pickPreviewDpi($sourcePath, $gs);

        $output = [];
        $code = 0;

        exec(sprintf(
            '%s -dSAFER -dBATCH -dNOPAUSE -dPDFSTOPONERROR -dFirstPage=1 -dLastPage=1 -sDEVICE=png16m -r%d -o %s %s 2>&1',
            escapeshellarg($gs),
            $dpi,
            escapeshellarg($previewPath),
            escapeshellarg($sourcePath),
        ), $output, $code);

        if ($code !== 0) {
            $stderr = implode(' ', $output);

            // Recognise password-protected PDFs so userSafeMessage can map to
            // a specific user-facing hint instead of the generic "try a
            // different file" fallback.
            if (stripos($stderr, 'password') !== false || stripos($stderr, 'encrypted') !== false) {
                throw new RuntimeException('PDF preview rasterisation failed: file is password-protected or encrypted.');
            }

            throw new RuntimeException('PDF preview rasterisation failed: '.$stderr);
        }

        return $previewPath;
    }

    /**
     * Pick a Ghostscript render DPI that targets ~2000px on the longest edge.
     * Hardcoded 150 DPI over-rendered A0 posters (90 MB decoded, IM then
     * clamped to 2048) and starved business-card PDFs of mask detail.
     * Clamped 72..300 for sanity.
     */
    private function pickPreviewDpi(string $sourcePath, string $gs): int
    {
        $default = 150;

        $output = [];
        $code = 0;
        exec(sprintf(
            '%s -dSAFER -dBATCH -dNOPAUSE -dFirstPage=1 -dLastPage=1 -q -sDEVICE=bbox %s 2>&1',
            escapeshellarg($gs),
            escapeshellarg($sourcePath),
        ), $output, $code);

        if ($code !== 0) {
            return $default;
        }

        $text = implode("\n", $output);
        if (! preg_match('/%%HiResBoundingBox:\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)/', $text, $m)) {
            return $default;
        }

        $widthPt = (float) $m[3] - (float) $m[1];
        $heightPt = (float) $m[4] - (float) $m[2];
        $longestInches = max($widthPt, $heightPt) / 72.0;

        if ($longestInches <= 0) {
            return $default;
        }

        return (int) max(72, min(300, round(2000 / $longestInches)));
    }

    /**
     * When AI returns an SVG path, rasterise it to a PNG mask so Potrace can
     * re-vectorise it cleanly (normalises AI output geometry).
     */
    private function vectorizeSvgToMask(
        string $svgPath,
        string $workDir,
        ImageProcessingService $imageProcessor,
    ): string {
        $rasterPath = $workDir.'/ai_mask_raster.png';
        $output = [];
        $code = 0;

        $convert = config('cutjob.binaries.convert', 'convert');

        exec(sprintf(
            '%s -background black -fill white -density 300 %s %s 2>&1',
            escapeshellarg($convert),
            escapeshellarg($svgPath),
            escapeshellarg($rasterPath),
        ), $output, $code);

        if ($code !== 0) {
            throw new RuntimeException('AI SVG rasterisation failed: '.implode(' ', $output));
        }

        return $imageProcessor->normalizeMask($rasterPath, $workDir);
    }
}
