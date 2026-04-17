<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Determines whether a file should take the Fast or AI-Enhanced processing path.
 *
 * Confidence is computed from three signals (PRD §7):
 *   - Edge clarity: contrast ratio of detected edges (ImageMagick Canny output)
 *   - Background complexity: number of distinct colour regions
 *   - Format type: vector formats score higher by default
 */
class ConfidenceService
{
    /** Files below this score are routed to the AI-Enhanced path. */
    private float $threshold;

    public function __construct()
    {
        $this->threshold = (float) config('cutjob.confidence_threshold', 0.65);
    }

    /**
     * Compute a confidence score for the preprocessed file and decide the pipeline path.
     *
     * @return array{score: float, useAi: bool}
     */
    public function evaluate(string $preprocessedPath, string $fileType): array
    {
        $score = $this->computeScore($preprocessedPath, $fileType);
        $useAi = $score < $this->threshold;

        Log::debug('ConfidenceService evaluation', [
            'file_type' => $fileType,
            'score' => $score,
            'threshold' => $this->threshold,
            'use_ai' => $useAi,
        ]);

        return ['score' => $score, 'useAi' => $useAi];
    }

    private function computeScore(string $path, string $fileType): float
    {
        $formatBonus = $this->formatBonus($fileType);
        $edgeClarity = $this->measureEdgeClarity($path);
        $backgroundComplexity = $this->measureBackgroundComplexity($path);

        // Weighted composite: edges 50%, background 30%, format 20%
        $score = ($edgeClarity * 0.5) + ((1 - $backgroundComplexity) * 0.3) + ($formatBonus * 0.2);

        return round(min(1.0, max(0.0, $score)), 4);
    }

    /**
     * Measure edge clarity using ImageMagick's edge detection output.
     * Returns a value between 0 (no clear edges) and 1 (sharp, well-defined edges).
     */
    private function measureEdgeClarity(string $path): float
    {
        $output = [];
        $code = 0;

        // Use ImageMagick to get mean pixel value of the edge-detected image
        exec(
            sprintf(
                'convert %s -colorspace Gray -canny 0x1+10%%+30%% -format "%%[fx:mean]" info: 2>/dev/null',
                escapeshellarg($path),
            ),
            $output,
            $code,
        );

        if ($code !== 0 || empty($output)) {
            return 0.5; // Neutral fallback if ImageMagick unavailable
        }

        return min(1.0, max(0.0, (float) ($output[0] ?? 0.5) * 10));
    }

    /**
     * Estimate background complexity by counting dominant colours.
     * Returns a value between 0 (simple) and 1 (very complex).
     */
    private function measureBackgroundComplexity(string $path): float
    {
        $output = [];
        $code = 0;

        exec(
            sprintf(
                'convert %s -colors 256 -format "%%k" info: 2>/dev/null',
                escapeshellarg($path),
            ),
            $output,
            $code,
        );

        if ($code !== 0 || empty($output)) {
            return 0.5;
        }

        $distinctColors = (int) ($output[0] ?? 128);

        // Normalise: ≤8 colours = very simple (0.0), 256 = very complex (1.0)
        return min(1.0, max(0.0, $distinctColors / 256));
    }

    /** Vector and clean raster formats score higher out of the gate. */
    private function formatBonus(string $fileType): float
    {
        return match (strtolower($fileType)) {
            'svg' => 1.0,
            'ai' => 0.9,
            'pdf' => 0.8,
            'png' => 0.7,
            'jpg', 'jpeg' => 0.4,
            default => 0.5,
        };
    }
}
