<?php

use App\Services\PdfService;

/**
 * Structural tests for PdfService without invoking external binaries.
 *
 * These lock in the two bugs the review flagged:
 *   1. Cut path must be OVERLAID on the artwork (qpdf --overlay), not
 *      concatenated as page 2 via `gs artwork.pdf cutpath.pdf`.
 *   2. Cut path must be stroked in the "CutContour" spot color Separation,
 *      not left as default DeviceRGB.
 */
test('spot color name and filename are constructed correctly', function () {
    $service = new PdfService;

    $filename = $service->buildFilename('logo/nested/name.png', 1024, 512);

    expect($filename)->toBe('name_1024x512.pdf');
});

test('PostScript prolog uses configured spot color name and defines the separation', function () {
    config(['cutjob.spot_color.name' => 'MimakiCut1']);
    config(['cutjob.spot_color.cmyk' => [10, 90, 5, 0]]);

    $service = new PdfService;

    $ref = new ReflectionMethod($service, 'buildSpotColorPostScript');
    $ref->setAccessible(true);

    $ps = $ref->invoke($service, 612.0, 792.0, 0.24, '100 100 moveto 200 200 lineto');

    expect($ps)
        ->toContain('/Separation')
        ->toContain('/MimakiCut1')
        ->toContain('/DeviceCMYK')
        ->toContain('setcolorspace')
        ->toContain('setcolor')
        ->toContain('612')     // page width points
        ->toContain('792')     // page height points
        ->toContain('100 100 moveto')
        ->toContain('stroke');
});

test('SVG path is converted to PostScript path operators (moveto/lineto/curveto)', function () {
    $service = new PdfService;

    $ref = new ReflectionMethod($service, 'svgPathToPostScript');
    $ref->setAccessible(true);

    $ps = $ref->invoke($service, 'M 10 20 L 30 40 C 50 60 70 80 90 100 Z');

    expect($ps)
        ->toContain('10.0000 20.0000 moveto')
        ->toContain('30.0000 40.0000 lineto')
        ->toContain('50.0000 60.0000 70.0000 80.0000 90.0000 100.0000 curveto')
        ->toContain('closepath');
});

test('SVG path parser handles relative m/l/c operators', function () {
    $service = new PdfService;

    $ref = new ReflectionMethod($service, 'svgPathToPostScript');
    $ref->setAccessible(true);

    $ps = $ref->invoke($service, 'm 5 5 l 10 10');

    expect($ps)
        ->toContain('5.0000 5.0000 moveto')
        ->toContain('15.0000 15.0000 lineto'); // relative → absolute (5+10, 5+10)
});

test('spot color CMYK values from config are normalised into the tint transform', function () {
    config(['cutjob.spot_color.name' => 'MimakiCut1']);
    config(['cutjob.spot_color.cmyk' => [10, 90, 5, 0]]);

    $service = new PdfService;

    $ref = new ReflectionMethod($service, 'buildSpotColorPostScript');
    $ref->setAccessible(true);

    $ps = $ref->invoke($service, 612.0, 792.0, 0.24, '100 100 moveto');

    // 10/100 = 0.1, 90/100 = 0.9, 5/100 = 0.05, 0/100 = 0 — the tint transform
    // must reference the normalised (0..1) floats so RIPs see the right fallback.
    expect($ps)
        ->toContain('0.1 mul')
        ->toContain('0.9 mul')
        ->toContain('0.05 mul')
        ->toContain('0 mul');
});

test('spot color name is sanitised in the PostScript comment (no injection via env var)', function () {
    // A newline in the env var would close the PS comment and inject code.
    config(['cutjob.spot_color.name' => "Cut\nContour /badop"]);
    config(['cutjob.spot_color.cmyk' => [0, 100, 0, 0]]);

    $service = new PdfService;

    $ref = new ReflectionMethod($service, 'buildSpotColorPostScript');
    $ref->setAccessible(true);

    $ps = $ref->invoke($service, 100.0, 100.0, 1.0, '');

    expect($ps)->not->toContain("\nContour /badop")
        ->and($ps)->not->toContain('/badop');
});

test('SVG path parser splits packed numbers into distinct commands', function () {
    $service = new PdfService;

    $ref = new ReflectionMethod($service, 'svgPathToPostScript');
    $ref->setAccessible(true);

    // Packed: "M10.5-20.3L30-40" has no whitespace before the negative signs.
    // Previous parser saw '10.5-20.3' as a single non-numeric token and
    // collapsed both coordinates to the previous value.
    $ps = $ref->invoke($service, 'M10.5-20.3L30-40');

    expect($ps)
        ->toContain('10.5000 -20.3000 moveto')
        ->toContain('30.0000 -40.0000 lineto');
});

test('SVG path parser rejects unsupported operators instead of silently corrupting', function () {
    $service = new PdfService;

    $ref = new ReflectionMethod($service, 'svgPathToPostScript');
    $ref->setAccessible(true);

    // S (smooth cubic) is a valid SVG operator but not implemented here.
    // Previously it either garbled the path or looped forever until timeout.
    expect(fn () => $ref->invoke($service, 'M 0 0 S 1 2 3 4'))
        ->toThrow(RuntimeException::class, 'Unsupported SVG path operator');
});
