<?php

use App\Models\CutJob;

/**
 * Pure-conversion tests — no DB needed, no factory.
 * Covers each supported unit plus the trailing-zero trimming.
 */
test('pxToUnit converts pixels to inches at 300 dpi and trims trailing zeros', function () {
    expect(CutJob::pxToUnit(300, 'in'))->toBe('1')
        ->and(CutJob::pxToUnit(600, 'in'))->toBe('2')
        ->and(CutJob::pxToUnit(150, 'in'))->toBe('0.5');
});

test('pxToUnit converts pixels to cm', function () {
    expect(CutJob::pxToUnit(300, 'cm'))->toBe('2.54');
});

test('pxToUnit converts pixels to mm with one decimal', function () {
    expect(CutJob::pxToUnit(300, 'mm'))->toBe('25.4');
});

test('pxToUnit converts pixels to points', function () {
    expect(CutJob::pxToUnit(300, 'pt'))->toBe('72');
});

test('pxToUnit returns raw px unchanged', function () {
    expect(CutJob::pxToUnit(1234, 'px'))->toBe('1234');
});

test('pxToUnit handles unknown unit by falling back to inches', function () {
    expect(CutJob::pxToUnit(300, 'furlong'))->toBe('1');
});
