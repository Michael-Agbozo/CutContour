<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Handles all ImageMagick operations: format normalisation, resizing,
 * edge detection, and binary mask generation (PRD §7A, §7B).
 */
class ImageProcessingService
{
    /** Maximum dimension (px) before resizing kicks in. */
    private const MAX_DIMENSION = 10_000;

    /**
     * Preprocess the uploaded file into a normalised PNG ready for the pipeline.
     *
     * @return array{path: string, width: int, height: int}
     *
     * @throws RuntimeException
     */
    public function preprocess(string $sourcePath, string $outputDir): array
    {
        $outputPath = $outputDir.'/preprocessed.png';

        $this->ensureDirectory($outputDir);

        // Normalise to PNG, strip ICC profile quirks, auto-orient
        $this->exec(sprintf(
            'convert %s -auto-orient +profile "!exif,*" -colorspace sRGB -density 300 %s',
            escapeshellarg($sourcePath),
            escapeshellarg($outputPath),
        ), 'Preprocessing failed');

        // Resize if oversized, preserving aspect ratio
        $dimensions = $this->getDimensions($outputPath);
        if ($dimensions['width'] > self::MAX_DIMENSION || $dimensions['height'] > self::MAX_DIMENSION) {
            $this->exec(sprintf(
                'convert %s -resize %dx%d\> %s',
                escapeshellarg($outputPath),
                self::MAX_DIMENSION,
                self::MAX_DIMENSION,
                escapeshellarg($outputPath),
            ), 'Resize failed');
            $dimensions = $this->getDimensions($outputPath);
        }

        Log::debug('ImageProcessingService: preprocessed', [
            'output' => $outputPath,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
        ]);

        return ['path' => $outputPath, ...$dimensions];
    }

    /**
     * Generate a binary mask from the preprocessed image using Canny edge detection
     * followed by flood-fill to produce a solid subject mask.
     *
     * @throws RuntimeException
     */
    public function generateMask(string $preprocessedPath, string $outputDir): string
    {
        $maskPath = $outputDir.'/mask.png';

        $this->exec(sprintf(
            'convert %s -colorspace Gray -canny 0x1+10%%+30%% -negate -fill white -draw "color 0,0 floodfill" -alpha remove %s',
            escapeshellarg($preprocessedPath),
            escapeshellarg($maskPath),
        ), 'Mask generation failed');

        Log::debug('ImageProcessingService: mask generated', ['mask' => $maskPath]);

        return $maskPath;
    }

    /**
     * Produce a binary mask from an AI-provided mask image (normalise to B&W).
     *
     * @throws RuntimeException
     */
    public function normalizeMask(string $aiMaskPath, string $outputDir): string
    {
        $normalizedPath = $outputDir.'/mask_normalized.png';

        $this->exec(sprintf(
            'convert %s -colorspace Gray -threshold 50%% %s',
            escapeshellarg($aiMaskPath),
            escapeshellarg($normalizedPath),
        ), 'Mask normalization failed');

        return $normalizedPath;
    }

    /** @return array{width: int, height: int} */
    public function getDimensions(string $imagePath): array
    {
        $output = [];
        exec(sprintf(
            'identify -format "%%w %%h" %s 2>/dev/null',
            escapeshellarg($imagePath),
        ), $output);

        if (empty($output)) {
            return ['width' => 0, 'height' => 0];
        }

        [$width, $height] = explode(' ', trim($output[0]));

        return ['width' => (int) $width, 'height' => (int) $height];
    }

    private function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: {$dir}");
        }
    }

    /** @throws RuntimeException */
    private function exec(string $command, string $errorContext): void
    {
        $output = [];
        $code = 0;

        exec("{$command} 2>&1", $output, $code);

        if ($code !== 0) {
            $detail = implode(' ', $output);
            Log::error("ImageProcessingService: {$errorContext}", ['command' => $command, 'output' => $detail]);
            throw new RuntimeException("{$errorContext}: {$detail}");
        }
    }
}
