<?php

namespace Database\Factories;

use App\Models\CutJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CutJob>
 */
class CutJobFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userId = User::factory();
        $ext = fake()->randomElement(['jpg', 'png', 'svg', 'pdf', 'ai']);
        $originalName = fake()->slug(3).'.'.$ext;
        $retentionDays = 90;
        $container = app();

        if ($container->bound('config')) {
            $retentionDays = (int) $container->make('config')->get('cutjob.retention_days', $retentionDays);
        }

        return [
            'user_id' => $userId,
            'original_name' => $originalName,
            'file_path' => 'users/1/jobs/'.Str::ulid().'/original.'.$ext,
            'output_path' => null,
            'file_type' => $ext,
            'width' => fake()->numberBetween(100, 5000),
            'height' => fake()->numberBetween(100, 5000),
            'status' => 'processing',
            'ai_used' => false,
            'confidence_score' => null,
            'processing_duration_ms' => null,
            'error_message' => null,
            'expires_at' => now()->addDays($retentionDays),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'completed',
            'output_path' => str_replace('original.'.$attributes['file_type'], 'output.pdf', $attributes['file_path']),
            'ai_used' => fake()->boolean(30),
            'confidence_score' => fake()->randomFloat(2, 0.5, 1.0),
            'processing_duration_ms' => fake()->numberBetween(500, 30000),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'error_message' => fake()->sentence(),
            'processing_duration_ms' => fake()->numberBetween(100, 5000),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => 'expired',
            'expires_at' => now()->subDay(),
            'output_path' => null,
            'file_path' => null,
        ]);
    }

    public function aiUsed(): static
    {
        return $this->state(fn (): array => [
            'ai_used' => true,
            'confidence_score' => fake()->randomFloat(2, 0.0, 0.64),
        ]);
    }
}
