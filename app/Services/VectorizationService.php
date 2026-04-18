<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Runs Potrace on a binary mask to produce a clean SVG vector path (PRD §7A, §7B).
 */
class VectorizationService
{
    private string $convert;

    private string $potrace;

    public function __construct()
    {
        $this->convert = config('cutjob.binaries.convert', 'convert');
        $this->potrace = config('cutjob.binaries.potrace', 'potrace');
    }

    /**
     * Vectorise a binary mask PNG into an SVG file using Potrace.
     *
     * @throws RuntimeException
     */
    public function vectorize(string $maskPath, string $outputDir): string
    {
        $svgPath = $outputDir.'/cutpath.svg';
        $bmpPath = $outputDir.'/mask.bmp';

        $this->exec(sprintf(
            '%s %s %s',
            escapeshellarg($this->convert),
            escapeshellarg($maskPath),
            escapeshellarg($bmpPath),
        ), 'BMP conversion failed');

        $this->exec(sprintf(
            '%s --svg --output %s --turdsize 2 --alphamax 1 --opttolerance 0.2 %s',
            escapeshellarg($this->potrace),
            escapeshellarg($svgPath),
            escapeshellarg($bmpPath),
        ), 'Potrace vectorization failed');

        if (! file_exists($svgPath) || filesize($svgPath) === 0) {
            throw new RuntimeException('Potrace produced an empty SVG output.');
        }

        Log::debug('VectorizationService: vectorized', ['svg' => $svgPath]);

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
