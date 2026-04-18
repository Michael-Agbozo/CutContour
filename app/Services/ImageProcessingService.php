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
    private const MAX_DIMENSION = 2_048;

    private string $convert;

    private string $identify;

    public function __construct()
    {
        $this->convert = config('cutjob.binaries.convert', 'convert');
        $this->identify = config('cutjob.binaries.identify', 'identify');
    }

    /**
     * Preprocess the uploaded file into a normalised PNG ready for the pipeline.
     * If target dimensions are supplied the image is scaled to fit within them
     * (preserving aspect ratio) then centre-padded to the exact canvas size.
     *
     * @return array{path: string, width: int, height: int}
     *
     * @throws RuntimeException
     */
    public function preprocess(
        string $sourcePath,
        string $outputDir,
        ?int $targetWidthPx = null,
        ?int $targetHeightPx = null,
    ): array {
        $outputPath = $outputDir.'/preprocessed.png';

        $this->ensureDirectory($outputDir);

        // Clamp during the initial convert so ImageMagick never decodes the full
        // raster at 300 DPI before capping — critical for large photos/WhatsApp images.
        $this->exec(sprintf(
            '%s %s -auto-orient +profile "!exif,*" -colorspace sRGB -density 300 -resize %s %s',
            escapeshellarg($this->convert),
            escapeshellarg($sourcePath),
            escapeshellarg(self::MAX_DIMENSION.'x'.self::MAX_DIMENSION.'>'),
            escapeshellarg($outputPath),
        ), 'Preprocessing failed');

        $dimensions = $this->getDimensions($outputPath);

        if ($targetWidthPx !== null && $targetHeightPx !== null) {
            $background = $this->hasAlphaChannel($outputPath) ? 'none' : 'white';
            $this->exec(sprintf(
                '%s %s -resize %dx%d -gravity Center -background %s -extent %dx%d %s',
                escapeshellarg($this->convert),
                escapeshellarg($outputPath),
                $targetWidthPx,
                $targetHeightPx,
                $background,
                $targetWidthPx,
                $targetHeightPx,
                escapeshellarg($outputPath),
            ), 'Resize to target dimensions failed');
            $dimensions = ['width' => $targetWidthPx, 'height' => $targetHeightPx];
        }

        Log::debug('ImageProcessingService: preprocessed', [
            'output' => $outputPath,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
        ]);

        return ['path' => $outputPath, ...$dimensions];
    }

    /**
     * Generate a binary subject mask from the preprocessed image.
     *
     * For images with an alpha channel (e.g. transparent PNGs) the alpha is
     * extracted directly, giving a pixel-perfect subject boundary.
     * For opaque images Canny edge detection + multi-corner flood-fill is used
     * to isolate the subject from the background.
     *
     * @throws RuntimeException
     */
    public function generateMask(string $preprocessedPath, string $outputDir): string
    {
        $maskPath = $outputDir.'/mask.png';

        if ($this->hasAlphaChannel($preprocessedPath)) {
            $this->exec(sprintf(
                '%s %s -alpha extract -threshold 50%% %s',
                escapeshellarg($this->convert),
                escapeshellarg($preprocessedPath),
                escapeshellarg($maskPath),
            ), 'Alpha mask extraction failed');
        } else {
            $dims = $this->getDimensions($preprocessedPath);
            $w = max(0, $dims['width'] - 1);
            $h = max(0, $dims['height'] - 1);

            // Canny edges → close gaps → flood-fill background from all four
            // corners with white → negate so subject = white, background = black
            // → small dilation fills the thin edge ring left by Canny
            $this->exec(sprintf(
                '%s %s -colorspace Gray -blur 1x1 -canny 0x1+8%%+20%% -morphology Close Disk:4 -fill white -draw "color 0,0 floodfill" -draw "color %d,0 floodfill" -draw "color 0,%d floodfill" -draw "color %d,%d floodfill" -negate -morphology Dilate Disk:2 %s',
                escapeshellarg($this->convert),
                escapeshellarg($preprocessedPath),
                $w, $h, $w, $h,
                escapeshellarg($maskPath),
            ), 'Mask generation failed');
        }

        Log::debug('ImageProcessingService: mask generated', ['mask' => $maskPath]);

        return $maskPath;
    }

    /**
     * Dilate a binary mask outward by the given number of pixels (contour offset).
     *
     * @throws RuntimeException
     */
    public function applyOffset(string $maskPath, string $outputDir, int $offsetPx): string
    {
        if ($offsetPx <= 0) {
            return $maskPath;
        }

        $outputPath = $outputDir.'/mask_offset.png';

        $this->exec(sprintf(
            '%s %s -morphology Dilate Disk:%d %s',
            escapeshellarg($this->convert),
            escapeshellarg($maskPath),
            $offsetPx,
            escapeshellarg($outputPath),
        ), 'Offset dilation failed');

        return $outputPath;
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
            '%s %s -colorspace Gray -threshold 50%% %s',
            escapeshellarg($this->convert),
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
            '%s -format "%%w %%h" %s 2>/dev/null',
            escapeshellarg($this->identify),
            escapeshellarg($imagePath),
        ), $output);

        if (empty($output)) {
            return ['width' => 0, 'height' => 0];
        }

        [$width, $height] = explode(' ', trim($output[0]));

        return ['width' => (int) $width, 'height' => (int) $height];
    }

    private function hasAlphaChannel(string $imagePath): bool
    {
        $output = [];
        exec(sprintf(
            '%s -format "%%A" %s 2>/dev/null',
            escapeshellarg($this->identify),
            escapeshellarg($imagePath),
        ), $output);

        return isset($output[0]) && strtolower(trim($output[0])) === 'true';
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
