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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Orchestrates the full CutContour processing pipeline for a single job (PRD §7, §10).
 *
 * Pipeline:
 *   1. Preprocess (ImageMagick)
 *   2. Confidence check → Fast or AI-Enhanced path
 *   3. [AI path] Subject isolation → normalise output
 *   4. Vectorise (Potrace)
 *   5. Assemble PDF (CutContour spot colour layer)
 *   6. Update CutJob record
 *
 * Failure behaviour: any unhandled exception marks the job as `failed` with the
 * error message stored for admin inspection. No silent failures.
 */
class ProcessCutJob implements ShouldQueue
{
    use Queueable;

    /** Abort the job if it runs longer than 5 minutes. */
    public int $timeout = 300;

    /** Do not retry — pipeline failures are deterministic and a second attempt wastes time. */
    public int $tries = 1;

    /** Fail the job immediately without re-queuing. */
    public int $maxExceptions = 1;

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

        Log::info('ProcessCutJob: started', [
            'job_id' => $jobId,
            'file_type' => $this->cutJob->file_type,
            'original_name' => $this->cutJob->original_name,
        ]);

        $sourcePath = Storage::path($this->cutJob->file_path);
        $workDir = Storage::path("users/{$userId}/jobs/{$jobId}/work");

        try {
            // ── Step 1: Preprocess ────────────────────────────────────────────────
            $preprocessed = $imageProcessor->preprocess(
                $sourcePath,
                $workDir,
                $this->targetWidthPx > 0 ? $this->targetWidthPx : null,
                $this->targetHeightPx > 0 ? $this->targetHeightPx : null,
            );

            // Update dimensions now that we know them
            $this->cutJob->update([
                'width' => $preprocessed['width'],
                'height' => $preprocessed['height'],
            ]);

            // ── Step 2: Confidence check ──────────────────────────────────────────
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
                $aiResult = $aiService->analyze($preprocessed['path'], $workDir);

                if ($aiResult !== null) {
                    $maskPath = match ($aiResult['type']) {
                        'svg' => $this->vectorizeSvgToMask($aiResult['path'], $workDir, $imageProcessor),
                        'mask' => $imageProcessor->normalizeMask($aiResult['path'], $workDir),
                    };
                } else {
                    // AI failed — fall back to Fast Path silently (PRD §9)
                    $aiFallback = true;
                    $useAi = false;
                    Log::warning('ProcessCutJob: AI fallback activated', ['job_id' => $jobId]);
                }
            }

            // ── Step 4: Fast Path mask (no AI or AI fallback) ────────────────────
            if ($maskPath === null) {
                $maskPath = $imageProcessor->generateMask($preprocessed['path'], $workDir);
            }

            // Apply contour offset dilation (outward expansion of subject boundary)
            if ($this->offsetPx > 0) {
                $maskPath = $imageProcessor->applyOffset($maskPath, $workDir, $this->offsetPx);
            }

            // ── Step 5: Vectorise ─────────────────────────────────────────────────
            $svgPath = $vectorizer->vectorize($maskPath, $workDir);

            // ── Step 6: Assemble PDF ──────────────────────────────────────────────
            $absoluteOutputPath = $pdfService->assemble(
                originalPath: $sourcePath,
                svgPath: $svgPath,
                outputDir: Storage::path("users/{$userId}/jobs/{$jobId}"),
                originalName: $this->cutJob->original_name,
                width: $preprocessed['width'],
                height: $preprocessed['height'],
            );

            // Derive the storage-relative path from the absolute path returned by assemble()
            $relativeOutputPath = ltrim(
                str_replace(Storage::path(''), '', $absoluteOutputPath),
                '/\\',
            );

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->cutJob->forceFill([
                'status' => 'completed',
                'output_path' => $relativeOutputPath,
                'ai_used' => $useAi && ! $aiFallback,
                'confidence_score' => $confidence['score'],
                'processing_duration_ms' => $durationMs,
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
                'duration_ms' => $durationMs,
            ]);

            $this->cutJob->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processing_duration_ms' => $durationMs,
            ])->saveQuietly();

            $this->cutJob->user->notify(new CutJobNotification($this->cutJob, 'failed'));

            // Do not rethrow — we handle failure ourselves to avoid "attempted too many times".
            $this->fail($e);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ProcessCutJob: exhausted all retries', [
            'job_id' => $this->cutJob->id,
            'error' => $exception->getMessage(),
        ]);

        // Ensure the job record reflects failure even if handle() update failed
        $this->cutJob->forceFill([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ])->saveQuietly();

        $this->cutJob->user->notify(new CutJobNotification($this->cutJob, 'failed'));
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
            throw new \RuntimeException('AI SVG rasterisation failed: '.implode(' ', $output));
        }

        return $imageProcessor->normalizeMask($rasterPath, $workDir);
    }
}
