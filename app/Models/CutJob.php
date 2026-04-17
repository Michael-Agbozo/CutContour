<?php

namespace App\Models;

use Database\Factories\CutJobFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CutJob extends Model
{
    /** @use HasFactory<CutJobFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'original_name',
        'file_path',
        'output_path',
        'file_type',
        'width',
        'height',
        'status',
        'ai_used',
        'confidence_score',
        'processing_duration_ms',
        'error_message',
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

    public function isExpired(): Attribute
    {
        return Attribute::get(fn (): bool => $this->expires_at->isPast());
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNotIn('status', ['expired']);
    }
}
