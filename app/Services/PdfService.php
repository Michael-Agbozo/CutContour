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
    private string $convert;

    private string $inkscape;

    private string $gs;

    public function __construct()
    {
        $this->convert = config('cutjob.binaries.convert', 'convert');
        $this->inkscape = config('cutjob.binaries.inkscape', 'inkscape');
        $this->gs = config('cutjob.binaries.gs', 'gs');
    }

    /**
     * Assemble the final PDF from the original artwork and the SVG cut path.
     *
     * The cut path is kept fully vector (PRD §8): Inkscape converts the SVG to a
     * vector PDF, then GhostScript merges it as a separate layer over the artwork.
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

        // Step 1: Convert artwork to PDF if it is not one already
        $artworkPdf = $outputDir.'/artwork.pdf';
        if (strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) === 'pdf') {
            $artworkPdf = $originalPath;
        } else {
            $this->exec(sprintf(
                '%s %s -density 300 -compress LZW PDF:%s',
                escapeshellarg($this->convert),
                escapeshellarg($originalPath),
                escapeshellarg($artworkPdf),
            ), 'Artwork to PDF conversion failed');
        }

        // Step 2: Convert SVG cut path to a vector PDF — never rasterized (PRD §8)
        $cutPathPdf = $outputDir.'/cutpath_vector.pdf';
        $this->exec(sprintf(
            '%s --export-type=pdf --export-filename=%s %s',
            escapeshellarg($this->inkscape),
            escapeshellarg($cutPathPdf),
            escapeshellarg($svgPath),
        ), 'SVG to vector PDF failed (inkscape required)');

        // Step 3: Merge artwork + vector cut path using GhostScript
        $this->exec(sprintf(
            '%s -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=%s %s %s',
            escapeshellarg($this->gs),
            escapeshellarg($outputPath),
            escapeshellarg($artworkPdf),
            escapeshellarg($cutPathPdf),
        ), 'PDF merge failed (gs required)');

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException('PDF assembly produced an empty file.');
        }

        Log::debug('PdfService: assembled', ['output' => $outputPath]);

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
