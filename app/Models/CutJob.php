<?php

namespace App\Models;

use Database\Factories\CutJobFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single file-processing job submitted by a user.
 *
 * Status lifecycle: pending → processing → completed | failed | expired.
 */
class CutJob extends Model
{
    /** @use HasFactory<CutJobFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'original_name',
        'job_name',
        'file_path',
        'output_path',
        'file_type',
        'width',
        'height',
        'unit',
        'status',
        'expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ai_used' => 'boolean',
            'confidence_score' => 'float',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Whether the job's download window has elapsed. */
    public function isExpired(): Attribute
    {
        return Attribute::get(fn (): bool => $this->expires_at->isPast());
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /** All jobs that haven't been marked as expired (i.e. still visible to the user). */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNotIn('status', ['expired']);
    }

    /**
     * Build the download filename: {name}_{height}{unit}h_{width}{unit}w.pdf
     *
     * If no job name was set, generates Cut{N}-untitled where N is the count
     * of unnamed jobs for this user.
     */
    public function downloadFilename(): string
    {
        $unit = $this->unit ?? 'in';
        $w = $this->pxToUnit($this->width ?? 0, $unit);
        $h = $this->pxToUnit($this->height ?? 0, $unit);

        if ($this->job_name) {
            $name = pathinfo($this->job_name, PATHINFO_FILENAME);
        } else {
            $position = static::where('user_id', $this->user_id)
                ->whereNull('job_name')
                ->where('id', '<=', $this->id)
                ->count();
            $name = "Cut{$position}-untitled";
        }

        return "{$name}_{$h}{$unit}h_{$w}{$unit}w.pdf";
    }

    /**
     * Convert pixels to the given unit at the configured DPI.
     */
    public static function pxToUnit(int $px, string $unit): string
    {
        $dpi = config('cutjob.dpi', 300);

        $value = match ($unit) {
            'cm' => round($px / $dpi * 2.54, 2),
            'mm' => round($px / $dpi * 25.4, 1),
            'pt' => round($px / $dpi * 72, 1),
            'px' => $px,
            default => round($px / $dpi, 2), // in
        };

        // Remove unnecessary trailing decimal zeros: 100.00 → 100, 10.50 → 10.5
        $formatted = (string) $value;

        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted;
    }
}
