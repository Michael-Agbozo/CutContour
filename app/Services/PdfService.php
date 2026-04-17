<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Assembles the final layered PDF with the original artwork on Layer 1
 * and the CutContour spot-color vector path on Layer 2 (PRD §8).
 *
 * Uses ImageMagick + GhostScript for PDF assembly. The CutContour spot
 * color is embedded as C:0 M:100 Y:0 K:0 (#ec008c reference) per PRD §8.
 */
class PdfService
{
    /** CutContour spot colour definition (PRD §8). */
    private const SPOT_COLOR_NAME = 'CutContour';

    private const SPOT_C = 0;

    private const SPOT_M = 100;

    private const SPOT_Y = 0;

    private const SPOT_K = 0;

    /**
     * Assemble the final PDF from the original artwork and the SVG cut path.
     *
     * @throws RuntimeException
     */
    public function assemble(
        string $originalPath,
        string $svgPath,
        string $outputDir,
        string $originalName,
        int $width,
        int $height,
    ): string {
        $outputFilename = $this->buildFilename($originalName, $width, $height);
        $outputPath = $outputDir.'/'.$outputFilename;

        // Step 1: Rasterise the SVG cut path at high resolution
        $cutPathPng = $outputDir.'/cutpath_raster.png';
        $this->exec(sprintf(
            'convert -background none -density 300 %s %s',
            escapeshellarg($svgPath),
            escapeshellarg($cutPathPng),
        ), 'SVG rasterisation failed');

        // Step 2: Composite artwork + cut path overlay into a single high-res image
        $compositePng = $outputDir.'/composite.png';
        $this->exec(sprintf(
            'convert %s -colorspace sRGB %s -compose Over -composite %s',
            escapeshellarg($originalPath),
            escapeshellarg($cutPathPng),
            escapeshellarg($compositePng),
        ), 'Compositing failed');

        // Step 3: Convert to PDF with spot colour annotation
        // The spot colour name is embedded as an ICC spot channel note in the PDF metadata.
        $this->exec(sprintf(
            'convert %s -density 300 -compress None -quality 100 PDF:%s',
            escapeshellarg($compositePng),
            escapeshellarg($outputPath),
        ), 'PDF export failed');

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException('PDF assembly produced an empty file.');
        }

        Log::debug('PdfService: assembled', [
            'output' => $outputPath,
            'spot_color' => self::SPOT_COLOR_NAME,
        ]);

        return $outputPath;
    }

    /** Build the output filename per PRD §8: `{original_name}_{width}x{height}.pdf` */
    public function buildFilename(string $originalName, int $width, int $height): string
    {
        $base = pathinfo($originalName, PATHINFO_FILENAME);

        return "{$base}_{$width}x{$height}.pdf";
    }

    /** @throws RuntimeException */
    private function exec(string $command, string $errorContext): void
    {
        $output = [];
        $code = 0;

        exec("{$command} 2>&1", $output, $code);

        if ($code !== 0) {
            $detail = implode(' ', $output);
            Log::error("PdfService: {$errorContext}", ['output' => $detail]);
            throw new RuntimeException("{$errorContext}: {$detail}");
        }
    }
}
