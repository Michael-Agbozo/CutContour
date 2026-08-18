<?php

use App\Jobs\ProcessCutJob;
use App\Models\CutJob;
use App\Models\User;
use App\Services\AIService;
use App\Services\ConfidenceService;
use App\Services\ImageProcessingService;
use App\Services\PdfService;
use App\Services\VectorizationService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

function makeMockServices(
    int $width = 800,
    int $height = 600,
    float $score = 0.85,
    bool $useAi = false,
    ?array $aiResult = null,
    string $pdfFilename = 'artwork_800x600.pdf',
): array {
    $workDir = sys_get_temp_dir().'/cutcontour-test-'.uniqid();

    $imageProcessor = Mockery::mock(ImageProcessingService::class);
    $imageProcessor->shouldReceive('preprocess')
        ->once()
        ->andReturn(['path' => $workDir.'/preprocessed.png', 'width' => $width, 'height' => $height]);

    $confidence = Mockery::mock(ConfidenceService::class);
    $confidence->shouldReceive('evaluate')
        ->once()
        ->andReturn(['score' => $score, 'useAi' => $useAi]);

    $ai = Mockery::mock(AIService::class);
    $vectorizer = Mockery::mock(VectorizationService::class);
    $vectorizer->shouldReceive('vectorize')->once()->andReturn($workDir.'/cutpath.svg');

    $pdf = Mockery::mock(PdfService::class);
    $pdf->shouldReceive('assemble')->once()->andReturn('/tmp/'.$pdfFilename);
    $pdf->shouldReceive('buildFilename')->andReturn($pdfFilename);

    return compact('imageProcessor', 'confidence', 'ai', 'vectorizer', 'pdf', 'workDir');
}

test('job marks cut_job as completed on fast path', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'file_type' => 'png',
        'original_name' => 'artwork.png',
    ]);

    Storage::put($job->file_path, 'fake-image-content');

    [
        'imageProcessor' => $imageProcessor,
        'confidence' => $confidence,
        'ai' => $ai,
        'vectorizer' => $vectorizer,
        'pdf' => $pdf,
        'workDir' => $workDir,
    ] = makeMockServices(width: 800, height: 600, score: 0.85, useAi: false);

    $imageProcessor->shouldReceive('generateMask')->once()->andReturn($workDir.'/mask.png');
    $ai->shouldNotReceive('analyze');

    (new ProcessCutJob($job))->handle($imageProcessor, $confidence, $ai, $vectorizer, $pdf);

    $job->refresh();

    expect($job->status)->toBe('completed')
        ->and($job->width)->toBe(800)
        ->and($job->height)->toBe(600)
        ->and($job->ai_used)->toBeFalse()
        ->and($job->confidence_score)->toBe(0.85)
        ->and($job->processing_duration_ms)->toBeInt();
});

test('job takes AI path when confidence is low', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'file_type' => 'jpg',
        'original_name' => 'complex.jpg',
    ]);

    Storage::put($job->file_path, 'fake-image-content');

    $workDir = sys_get_temp_dir().'/cutcontour-test-'.uniqid();

    $imageProcessor = Mockery::mock(ImageProcessingService::class);
    $imageProcessor->shouldReceive('preprocess')
        ->once()
        ->andReturn(['path' => $workDir.'/preprocessed.png', 'width' => 1200, 'height' => 900]);
    $imageProcessor->shouldReceive('normalizeMask')
        ->once()
        ->andReturn($workDir.'/mask_normalized.png');

    $confidence = Mockery::mock(ConfidenceService::class);
    $confidence->shouldReceive('evaluate')
        ->once()
        ->andReturn(['score' => 0.40, 'useAi' => true]);

    $ai = Mockery::mock(AIService::class);
    $ai->shouldReceive('analyze')
        ->once()
        ->andReturn(['type' => 'mask', 'path' => $workDir.'/ai_mask.png']);

    $vectorizer = Mockery::mock(VectorizationService::class);
    $vectorizer->shouldReceive('vectorize')->once()->andReturn($workDir.'/cutpath.svg');

    $pdf = Mockery::mock(PdfService::class);
    $pdf->shouldReceive('assemble')->once()->andReturn('/tmp/complex_1200x900.pdf');
    $pdf->shouldReceive('buildFilename')->andReturn('complex_1200x900.pdf');

    (new ProcessCutJob($job))->handle($imageProcessor, $confidence, $ai, $vectorizer, $pdf);

    $job->refresh();

    expect($job->status)->toBe('completed')
        ->and($job->ai_used)->toBeTrue();
});

test('job falls back to fast path when AI returns null', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'file_type' => 'jpg',
        'original_name' => 'artwork.jpg',
    ]);

    Storage::put($job->file_path, 'fake-image-content');

    $workDir = sys_get_temp_dir().'/cutcontour-test-'.uniqid();

    $imageProcessor = Mockery::mock(ImageProcessingService::class);
    $imageProcessor->shouldReceive('preprocess')
        ->once()
        ->andReturn(['path' => $workDir.'/preprocessed.png', 'width' => 600, 'height' => 400]);
    $imageProcessor->shouldReceive('generateMask')
        ->once()
        ->andReturn($workDir.'/mask.png');

    $confidence = Mockery::mock(ConfidenceService::class);
    $confidence->shouldReceive('evaluate')
        ->once()
        ->andReturn(['score' => 0.30, 'useAi' => true]);

    $ai = Mockery::mock(AIService::class);
    $ai->shouldReceive('analyze')->once()->andReturn(null);

    $vectorizer = Mockery::mock(VectorizationService::class);
    $vectorizer->shouldReceive('vectorize')->once()->andReturn($workDir.'/cutpath.svg');

    $pdf = Mockery::mock(PdfService::class);
    $pdf->shouldReceive('assemble')->once()->andReturn('/tmp/artwork_600x400.pdf');
    $pdf->shouldReceive('buildFilename')->andReturn('artwork_600x400.pdf');

    (new ProcessCutJob($job))->handle($imageProcessor, $confidence, $ai, $vectorizer, $pdf);

    $job->refresh();

    expect($job->status)->toBe('completed')
        ->and($job->ai_used)->toBeFalse();
});

test('job applies offset dilation when offsetPx is set', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'file_type' => 'png',
        'original_name' => 'artwork.png',
    ]);

    Storage::put($job->file_path, 'fake-image-content');

    [
        'imageProcessor' => $imageProcessor,
        'confidence' => $confidence,
        'ai' => $ai,
        'vectorizer' => $vectorizer,
        'pdf' => $pdf,
        'workDir' => $workDir,
    ] = makeMockServices(width: 800, height: 600, score: 0.85, useAi: false);

    $imageProcessor->shouldReceive('generateMask')->once()->andReturn($workDir.'/mask.png');
    $imageProcessor->shouldReceive('applyOffset')
        ->once()
        ->with($workDir.'/mask.png', Mockery::any(), 12)
        ->andReturn($workDir.'/mask_offset.png');
    $ai->shouldNotReceive('analyze');

    (new ProcessCutJob($job, 0, 0, 12))->handle($imageProcessor, $confidence, $ai, $vectorizer, $pdf);

    $job->refresh();

    expect($job->status)->toBe('completed');
});

test('job passes target dimensions to preprocess', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'file_type' => 'png',
        'original_name' => 'artwork.png',
    ]);

    Storage::put($job->file_path, 'fake-image-content');

    $workDir = sys_get_temp_dir().'/cutcontour-test-'.uniqid();

    $imageProcessor = Mockery::mock(ImageProcessingService::class);
    $imageProcessor->shouldReceive('preprocess')
        ->once()
        ->with(Mockery::any(), Mockery::any(), 384, 576)
        ->andReturn(['path' => $workDir.'/preprocessed.png', 'width' => 384, 'height' => 576]);
    $imageProcessor->shouldReceive('generateMask')->once()->andReturn($workDir.'/mask.png');

    $confidence = Mockery::mock(ConfidenceService::class);
    $confidence->shouldReceive('evaluate')->once()->andReturn(['score' => 0.90, 'useAi' => false]);

    $ai = Mockery::mock(AIService::class);
    $ai->shouldNotReceive('analyze');

    $vectorizer = Mockery::mock(VectorizationService::class);
    $vectorizer->shouldReceive('vectorize')->once()->andReturn($workDir.'/cutpath.svg');

    $pdf = Mockery::mock(PdfService::class);
    $pdf->shouldReceive('assemble')->once()->andReturn('/tmp/artwork_384x576.pdf');
    $pdf->shouldReceive('buildFilename')->andReturn('artwork_384x576.pdf');

    (new ProcessCutJob($job, 384, 576, 0))->handle($imageProcessor, $confidence, $ai, $vectorizer, $pdf);

    $job->refresh();

    expect($job->status)->toBe('completed')
        ->and($job->width)->toBe(384)
        ->and($job->height)->toBe(576);
});

test('job rasterises pdf uploads via Ghostscript before ImageMagick preprocessing (REG-1)', function () {
    // Front-end accepts pdf/ai uploads. Feeding them to `convert` fails now
    // that the hardened ImageMagick policy blocks the PDF coder — the job
    // must rasterise the first page with Ghostscript (sandboxed via -dSAFER)
    // and hand the PNG preview to the raster pipeline. The final assemble()
    // call still receives the original PDF path (PdfService short-circuits).
    config(['cutjob.binaries.gs' => '/bin/true']); // no-op stand-in for gs

    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'file_type' => 'pdf',
        'original_name' => 'artwork.pdf',
    ]);

    Storage::put($job->file_path, 'fake-pdf-content');
    $sourcePath = Storage::path($job->file_path);

    $workDir = Storage::path("users/{$user->id}/jobs/{$job->id}/work");

    $imageProcessor = Mockery::mock(ImageProcessingService::class);
    // The path fed into preprocess MUST be the rasterised preview, not the PDF.
    $imageProcessor->shouldReceive('preprocess')
        ->once()
        ->with($workDir.'/preview.png', $workDir, null, null)
        ->andReturn(['path' => $workDir.'/preprocessed.png', 'width' => 800, 'height' => 600]);
    $imageProcessor->shouldReceive('generateMask')->once()->andReturn($workDir.'/mask.png');

    $confidence = Mockery::mock(ConfidenceService::class);
    $confidence->shouldReceive('evaluate')->once()->andReturn(['score' => 0.9, 'useAi' => false]);

    $ai = Mockery::mock(AIService::class);
    $ai->shouldNotReceive('analyze');

    $vectorizer = Mockery::mock(VectorizationService::class);
    $vectorizer->shouldReceive('vectorize')->once()->andReturn($workDir.'/cutpath.svg');

    // assemble() must still receive the ORIGINAL PDF path so its own PDF
    // short-circuit fires (no re-conversion of an already-vector artwork).
    $pdf = Mockery::mock(PdfService::class);
    $pdf->shouldReceive('assemble')
        ->once()
        ->with(Mockery::on(fn ($args) => true), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->andReturnUsing(function ($originalPath) use ($sourcePath) {
            expect($originalPath)->toBe($sourcePath);

            return '/tmp/artwork_800x600.pdf';
        });
    $pdf->shouldReceive('buildFilename')->andReturn('artwork_800x600.pdf');

    (new ProcessCutJob($job))->handle($imageProcessor, $confidence, $ai, $vectorizer, $pdf);

    $job->refresh();
    expect($job->status)->toBe('completed');
});

test('idempotency guard blocks re-run while another worker still holds a fresh lease (REG-2)', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'file_type' => 'png',
        'original_name' => 'artwork.png',
        'status' => 'processing', // simulate another worker mid-flight
    ]);
    // Simulate worker A having taken the lease and started processing:
    // pipeline_step >= 1 marks that handle() actually ran, and updated_at is
    // fresh from A's ongoing work.
    $job->forceFill([
        'pipeline_step' => ProcessCutJob::STEP_PREPROCESS,
        'updated_at' => now(),
    ])->saveQuietly();

    Storage::put($job->file_path, 'fake-image-content');

    // If the guard works, none of these services should be touched.
    $imageProcessor = Mockery::mock(ImageProcessingService::class);
    $imageProcessor->shouldNotReceive('preprocess');
    $confidence = Mockery::mock(ConfidenceService::class);
    $ai = Mockery::mock(AIService::class);
    $vectorizer = Mockery::mock(VectorizationService::class);
    $pdf = Mockery::mock(PdfService::class);

    (new ProcessCutJob($job))->handle($imageProcessor, $confidence, $ai, $vectorizer, $pdf);

    // Row must be untouched — no output, still processing under the other lease.
    $job->refresh();
    expect($job->output_path)->toBeNull()
        ->and($job->status)->toBe('processing');
});

test('idempotency guard takes over a stale processing lease from a crashed worker (REG-2)', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'file_type' => 'png',
        'original_name' => 'artwork.png',
        'status' => 'processing',
    ]);
    // Worker A got to step 1 then crashed — pipeline_step >= 1 and
    // updated_at is older than timeout+30s grace.
    $job->forceFill([
        'pipeline_step' => ProcessCutJob::STEP_PREPROCESS,
        'updated_at' => now()->subSeconds(400),
    ])->saveQuietly();

    Storage::put($job->file_path, 'fake-image-content');

    [
        'imageProcessor' => $imageProcessor,
        'confidence' => $confidence,
        'ai' => $ai,
        'vectorizer' => $vectorizer,
        'pdf' => $pdf,
        'workDir' => $workDir,
    ] = makeMockServices(width: 800, height: 600, score: 0.9, useAi: false);

    $imageProcessor->shouldReceive('generateMask')->once()->andReturn($workDir.'/mask.png');
    $ai->shouldNotReceive('analyze');

    (new ProcessCutJob($job))->handle($imageProcessor, $confidence, $ai, $vectorizer, $pdf);

    $job->refresh();
    expect($job->status)->toBe('completed');
});

test('job is marked failed with a user-safe message and admin detail when pipeline throws', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'file_type' => 'png',
        'original_name' => 'bad.png',
    ]);

    Storage::put($job->file_path, 'fake');

    $imageProcessor = Mockery::mock(ImageProcessingService::class);
    $imageProcessor->shouldReceive('preprocess')
        ->andThrow(new RuntimeException('ImageMagick not found: /usr/bin/convert missing'));

    $confidence = Mockery::mock(ConfidenceService::class);
    $ai = Mockery::mock(AIService::class);
    $vectorizer = Mockery::mock(VectorizationService::class);
    $pdf = Mockery::mock(PdfService::class);

    try {
        (new ProcessCutJob($job))->handle($imageProcessor, $confidence, $ai, $vectorizer, $pdf);
    } catch (RuntimeException) {
        // Expected: the job rethrows so the queue records the failure.
    }

    $job->refresh();

    expect($job->status)->toBe('failed')
        ->and($job->error_message)->not->toContain('/usr/bin/convert') // internal path hidden
        ->and($job->error_detail)->toContain('ImageMagick not found');
});

test('password-protected PDF surfaces a dedicated user-safe error', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'file_type' => 'pdf',
        'original_name' => 'locked.pdf',
    ]);

    Storage::put($job->file_path, 'fake');

    $imageProcessor = Mockery::mock(ImageProcessingService::class);
    // Preprocess never runs — rasterizePdfPreview throws first with the
    // marker message the userSafeMessage sanitizer recognises.
    $imageProcessor->shouldNotReceive('preprocess');

    // Stub the private rasterizePdfPreview via a subclass so we don't need gs.
    $subject = new class($job) extends ProcessCutJob
    {
        protected function rasterizePdfPreview(string $sourcePath, string $workDir): string
        {
            throw new RuntimeException('PDF preview rasterisation failed: file is password-protected or encrypted.');
        }
    };

    try {
        $subject->handle(
            $imageProcessor,
            Mockery::mock(ConfidenceService::class),
            Mockery::mock(AIService::class),
            Mockery::mock(VectorizationService::class),
            Mockery::mock(PdfService::class),
        );
    } catch (RuntimeException) {
        // Expected — the job rethrows after recording state.
    }

    $job->refresh();

    expect($job->status)->toBe('failed')
        ->and($job->error_message)->toContain('password-protected')
        ->and($job->error_detail)->toContain('password-protected');
});
