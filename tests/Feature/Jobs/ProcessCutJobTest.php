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

test('job marks cut_job as failed when pipeline throws', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'file_type' => 'png',
        'original_name' => 'bad.png',
    ]);

    Storage::put($job->file_path, 'fake');

    $imageProcessor = Mockery::mock(ImageProcessingService::class);
    $imageProcessor->shouldReceive('preprocess')
        ->andThrow(new RuntimeException('ImageMagick not found'));

    $confidence = Mockery::mock(ConfidenceService::class);
    $ai = Mockery::mock(AIService::class);
    $vectorizer = Mockery::mock(VectorizationService::class);
    $pdf = Mockery::mock(PdfService::class);

    // handle() no longer throws — it calls $this->fail() internally
    (new ProcessCutJob($job))->handle($imageProcessor, $confidence, $ai, $vectorizer, $pdf);

    $job->refresh();

    // Job is marked as failed immediately in the catch block
    expect($job->status)->toBe('failed')
        ->and($job->error_message)->toContain('ImageMagick not found');
});
