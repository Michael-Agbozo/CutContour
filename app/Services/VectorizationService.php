<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Runs Potrace on a binary mask to produce a clean SVG vector path (PRD §7A, §7B).
 */
class VectorizationService
{
    /**
     * Vectorise a binary mask PNG into an SVG file using Potrace.
     *
     * @throws RuntimeException
     */
    public function vectorize(string $maskPath, string $outputDir): string
    {
        $svgPath = $outputDir.'/cutpath.svg';
        $bmpPath = $outputDir.'/mask.bmp';

        // Potrace requires BMP input — convert from PNG
        $this->exec(sprintf(
            'convert %s %s',
            escapeshellarg($maskPath),
            escapeshellarg($bmpPath),
        ), 'BMP conversion failed');

        // Run Potrace: output SVG with smooth curves, no fill, stroke only
        $this->exec(sprintf(
            'potrace --svg --output %s --turdsize 2 --alphamax 1 --opttolerance 0.2 %s',
            escapeshellarg($svgPath),
            escapeshellarg($bmpPath),
        ), 'Potrace vectorization failed');

        if (! file_exists($svgPath) || filesize($svgPath) === 0) {
            throw new RuntimeException('Potrace produced an empty SVG output.');
        }

        Log::debug('VectorizationService: vectorized', ['svg' => $svgPath]);

        return $svgPath;
    }

    /**
     * Normalise an AI-provided SVG path for use as the CutContour path.
     * Strips fill, sets stroke, and ensures the path is clean.
     */
    public function normalizeSvgPath(string $svgPath): string
    {
        $svg = file_get_contents($svgPath);

        if ($svg === false) {
            throw new RuntimeException("Cannot read SVG file: {$svgPath}");
        }

        // Strip fills, ensure stroke is applied — the CutContour path must be stroke-only
        $svg = preg_replace('/fill="[^"]*"/', 'fill="none"', $svg) ?? $svg;
        $svg = preg_replace('/stroke="[^"]*"/', '', $svg) ?? $svg;

        file_put_contents($svgPath, $svg);

        return $svgPath;
    }

    /** @throws RuntimeException */
    private function exec(string $command, string $errorContext): void
    {
        $output = [];
        $code = 0;

        exec("{$command} 2>&1", $output, $code);

        if ($code !== 0) {
            $detail = implode(' ', $output);
            Log::error("VectorizationService: {$errorContext}", ['output' => $detail]);
            throw new RuntimeException("{$errorContext}: {$detail}");
        }
    }
}
