<?php

use App\Ai\Agents\SubjectIsolationAgent;
use App\Services\AIService;

test('analyze returns svg result when agent returns valid path', function () {
    SubjectIsolationAgent::fake([[
        'svg_path' => 'M 0 0 L 100 0 L 100 100 L 0 100 Z',
        'confidence' => 0.92,
    ]]);

    $outputDir = sys_get_temp_dir().'/ai-test-'.uniqid();
    mkdir($outputDir, 0755, true);

    // Create a temporary test image
    $imagePath = $outputDir.'/test.png';
    $img = imagecreatetruecolor(100, 100);
    imagepng($img, $imagePath);
    imagedestroy($img);

    $service = new AIService;
    $result = $service->analyze($imagePath, $outputDir);

    expect($result)->not->toBeNull()
        ->and($result['type'])->toBe('svg')
        ->and($result['path'])->toEndWith('/ai_cutpath.svg')
        ->and($result['ai_confidence'])->toBe(0.92)
        ->and(file_exists($result['path']))->toBeTrue();

    $svgContent = file_get_contents($result['path']);
    expect($svgContent)->toContain('M 0 0 L 100 0 L 100 100 L 0 100 Z');

    SubjectIsolationAgent::assertPrompted(fn ($prompt) => $prompt->contains('Extract the main subject'));

    // Cleanup
    array_map('unlink', glob($outputDir.'/*'));
    rmdir($outputDir);
});

test('analyze returns null when agent returns empty svg_path', function () {
    SubjectIsolationAgent::fake([[
        'svg_path' => '',
        'confidence' => 0.1,
    ]]);

    $outputDir = sys_get_temp_dir().'/ai-test-'.uniqid();
    mkdir($outputDir, 0755, true);

    $imagePath = $outputDir.'/test.png';
    $img = imagecreatetruecolor(100, 100);
    imagepng($img, $imagePath);
    imagedestroy($img);

    $service = new AIService;
    $result = $service->analyze($imagePath, $outputDir);

    expect($result)->toBeNull();

    // Cleanup
    array_map('unlink', glob($outputDir.'/*'));
    rmdir($outputDir);
});

test('analyze returns null when agent throws exception', function () {
    SubjectIsolationAgent::fake(function () {
        throw new RuntimeException('API timeout');
    });

    $outputDir = sys_get_temp_dir().'/ai-test-'.uniqid();
    mkdir($outputDir, 0755, true);

    $imagePath = $outputDir.'/test.png';
    $img = imagecreatetruecolor(100, 100);
    imagepng($img, $imagePath);
    imagedestroy($img);

    $service = new AIService;
    $result = $service->analyze($imagePath, $outputDir);

    expect($result)->toBeNull();

    // Cleanup
    array_map('unlink', glob($outputDir.'/*'));
    rmdir($outputDir);
});

test('analyze rejects images larger than 10 MB', function () {
    $outputDir = sys_get_temp_dir().'/ai-test-'.uniqid();
    mkdir($outputDir, 0755, true);

    // Create a file that's > 10 MB
    $imagePath = $outputDir.'/large.png';
    $fh = fopen($imagePath, 'w');
    fseek($fh, (10 * 1024 * 1024) + 1);
    fwrite($fh, "\0");
    fclose($fh);

    $service = new AIService;
    $result = $service->analyze($imagePath, $outputDir);

    // Should return null (graceful fallback), not throw
    expect($result)->toBeNull();

    // Cleanup
    unlink($imagePath);
    rmdir($outputDir);
});

test('subject isolation agent has correct instructions', function () {
    $agent = new SubjectIsolationAgent;

    expect((string) $agent->instructions())
        ->toContain('computer vision specialist')
        ->toContain('die-cut path generation');
});

test('svg output is properly XML-escaped', function () {
    SubjectIsolationAgent::fake([[
        'svg_path' => 'M 0 0 L 100&200 Z',
        'confidence' => 0.5,
    ]]);

    $outputDir = sys_get_temp_dir().'/ai-test-'.uniqid();
    mkdir($outputDir, 0755, true);

    $imagePath = $outputDir.'/test.png';
    $img = imagecreatetruecolor(100, 100);
    imagepng($img, $imagePath);
    imagedestroy($img);

    $service = new AIService;
    $result = $service->analyze($imagePath, $outputDir);

    $svgContent = file_get_contents($result['path']);
    // The & should be escaped to &amp; in XML
    expect($svgContent)->toContain('&amp;');

    // Cleanup
    array_map('unlink', glob($outputDir.'/*'));
    rmdir($outputDir);
});
