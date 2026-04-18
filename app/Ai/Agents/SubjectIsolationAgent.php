<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Analyses an image via vision and returns a simplified SVG path
 * outlining the main subject boundary for die-cut path generation (PRD §9).
 *
 * AI does NOT generate the final cut path — it improves the input to Potrace
 * by isolating the subject before vectorisation.
 */
#[Provider(Lab::Gemini)]
#[Model('gemini-2.0-flash')]
#[Timeout(45)]
class SubjectIsolationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        You are a computer vision specialist for die-cut path generation.

        When given an image, identify the main subject and return a simplified SVG path
        element (the `d` attribute value only) that outlines the subject boundary.

        Rules:
        - Focus on the primary subject, ignoring background elements.
        - The path should be a closed shape suitable for die-cutting.
        - Use simple curves and lines — avoid excessive detail.
        - Coordinates should match the image dimensions.
        - Return ONLY the structured output, no explanations.
        INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'svg_path' => $schema->string()
                ->description('The SVG path d attribute value outlining the subject boundary')
                ->required(),
            'confidence' => $schema->number()
                ->min(0)->max(1)
                ->description('How confident the model is in the subject isolation (0-1)')
                ->required(),
        ];
    }
}
