<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Calls the Laravel AI SDK (gpt-4.1-mini) for subject isolation on complex images.
 *
 * The AI does NOT generate the final cut path — it improves the input to Potrace
 * by isolating the subject before vectorisation (PRD §9).
 *
 * Fallback: any failure (timeout, malformed response, empty output) returns null,
 * signalling the caller to use the Fast Path instead.
 */
class AIService
{
    private string $model;

    public function __construct()
    {
        $this->model = config('ai.default_model', 'gpt-4.1-mini');
    }

    /**
     * Analyse the image and return the best available output for mask generation.
     *
     * Priority (PRD §9):
     *   1. Binary segmentation mask path (PNG file written to $outputDir)
     *   2. SVG path string
     *   3. null → caller falls back to Fast Path
     *
     * @return array{type: 'mask'|'svg', path: string}|null
     */
    public function analyze(string $preprocessedPath, string $outputDir): ?array
    {
        try {
            $response = $this->callApi($preprocessedPath);

            return $this->parseResponse($response, $outputDir);
        } catch (Throwable $e) {
            Log::warning('AIService: analysis failed, falling back to Fast Path', [
                'error' => $e->getMessage(),
                'file' => $preprocessedPath,
            ]);

            return null;
        }
    }

    /**
     * @throws RuntimeException
     */
    private function callApi(string $imagePath): string
    {
        $imageData = base64_encode((string) file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath) ?: 'image/png';

        // Laravel AI SDK — requires `laravel/ai` package (composer require laravel/ai)
        // Using Http facade as the underlying transport to keep the service testable
        // without the package installed in dev.
        $apiKey = config('ai.api_key') ?? config('services.openai.api_key');

        if (! $apiKey) {
            throw new RuntimeException('AI API key not configured.');
        }

        $result = Http::withToken($apiKey)
            ->timeout(45)
            ->connectTimeout(10)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'max_tokens' => 1024,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Extract the main subject from this image for die-cut path generation. Return a simplified SVG path element (just the <path> tag with d attribute) outlining the subject boundary. Ignore background elements. Return only the SVG path element, no other text.',
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageData}",
                                    'detail' => 'high',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        if (! $result->successful()) {
            throw new RuntimeException('AI API returned error: '.$result->status());
        }

        return (string) ($result->json('choices.0.message.content') ?? '');
    }

    /**
     * Parse the AI response and write a usable artefact to disk.
     *
     * @return array{type: 'mask'|'svg', path: string}|null
     */
    private function parseResponse(string $content, string $outputDir): ?array
    {
        $content = trim($content);

        if (empty($content)) {
            Log::warning('AIService: empty response from model');

            return null;
        }

        // Check for SVG path element in the response
        if (str_contains($content, '<path') && str_contains($content, 'd="')) {
            $svgPath = $outputDir.'/ai_cutpath.svg';
            $svg = '<?xml version="1.0" encoding="UTF-8"?>'
                .'<svg xmlns="http://www.w3.org/2000/svg">'
                .$content
                .'</svg>';
            file_put_contents($svgPath, $svg);

            Log::debug('AIService: extracted SVG path', ['path' => $svgPath]);

            return ['type' => 'svg', 'path' => $svgPath];
        }

        Log::warning('AIService: unrecognised response format, falling back', [
            'content_preview' => substr($content, 0, 200),
        ]);

        return null;
    }
}
