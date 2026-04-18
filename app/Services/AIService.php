<?php

namespace App\Services;

use App\Ai\Agents\SubjectIsolationAgent;
use Illuminate\Support\Facades\Log;
use Prism\Prism\ValueObjects\Media\Image;
use RuntimeException;
use Throwable;

/**
 * Calls the Laravel AI SDK (SubjectIsolationAgent) for subject isolation on complex images.
 *
 * The AI does NOT generate the final cut path — it improves the input to Potrace
 * by isolating the subject before vectorisation (PRD §9).
 *
 * Fallback: any failure (timeout, malformed response, empty output) returns null,
 * signalling the caller to use the Fast Path instead.
 */
class AIService
{
    /**
     * Analyse the image and return the best available output for mask generation.
     *
     * Priority (PRD §9):
     *   1. SVG path string → written to file
     *   2. null → caller falls back to Fast Path
     *
     * @return array{type: 'svg', path: string, ai_confidence: float}|null
     */
    public function analyze(string $preprocessedPath, string $outputDir): ?array
    {
        try {
            return $this->callAgent($preprocessedPath, $outputDir);
        } catch (Throwable $e) {
            Log::warning('AIService: analysis failed, falling back to Fast Path', [
                'error' => $e->getMessage(),
                'file' => $preprocessedPath,
            ]);

            return null;
        }
    }

    /**
     * @return array{type: 'svg', path: string, ai_confidence: float}|null
     *
     * @throws RuntimeException
     */
    private function callAgent(string $imagePath, string $outputDir): ?array
    {
        $maxMb = (int) config('cutjob.max_file_size_mb', 100);
        $maxBytes = $maxMb * 1024 * 1024;
        if (filesize($imagePath) > $maxBytes) {
            throw new RuntimeException("Image too large for AI analysis (max {$maxMb} MB).");
        }

        $response = (new SubjectIsolationAgent)->prompt(
            'Extract the main subject from this image for die-cut path generation.',
            [Image::fromLocalPath($imagePath)],
        );

        $svgPathData = $response['svg_path'] ?? '';
        $aiConfidence = (float) ($response['confidence'] ?? 0.0);

        if (empty($svgPathData)) {
            Log::warning('AIService: empty svg_path from agent');

            return null;
        }

        if (! $this->isValidSvgPathData($svgPathData)) {
            Log::warning('AIService: invalid svg_path format from agent', [
                'path_preview' => substr($svgPathData, 0, 100),
            ]);

            return null;
        }

        return $this->writeSvg($svgPathData, $outputDir, $aiConfidence);
    }

    /**
     * Validate that the string contains only valid SVG path data characters.
     */
    private function isValidSvgPathData(string $data): bool
    {
        // SVG path d attribute: commands (MLCSQTAZHVmlcsqtazhv), numbers, commas, spaces, dots, dashes, scientific notation
        return (bool) preg_match('/^[MLCSQTAZHVmlcsqtazhv0-9\s,.\-eE+]+$/', $data);
    }

    /**
     * Write the AI-generated SVG path to disk.
     *
     * @return array{type: 'svg', path: string, ai_confidence: float}
     */
    private function writeSvg(string $pathData, string $outputDir, float $aiConfidence): array
    {
        $svgPath = $outputDir.'/ai_cutpath.svg';
        $svg = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg">'
            .'<path d="'.htmlspecialchars($pathData, ENT_QUOTES | ENT_XML1).'" />'
            .'</svg>';

        file_put_contents($svgPath, $svg);

        Log::debug('AIService: extracted SVG path via agent', [
            'path' => $svgPath,
            'ai_confidence' => $aiConfidence,
        ]);

        return ['type' => 'svg', 'path' => $svgPath, 'ai_confidence' => $aiConfidence];
    }
}
