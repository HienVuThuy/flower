<?php

namespace App\Models;

use App\Enums\CareTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một lịch nhắc chăm sóc: khách X, cây Y, việc Z. */
class CareReminder extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'kind',
        'interval_days',
        'next_due_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CareTask::class,
            'interval_days' => 'integer',
            'next_due_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('next_due_at', '<=', now());
    }

    public function daysUntilDue(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->next_due_at->startOfDay(), false);
    }

    public function isOverdue(): bool
    {
        return $this->daysUntilDue() < 0;
    }

    public function advance(): void
    {
        $this->forceFill([
            'last_sent_at' => now(),
            'next_due_at' => now()->addDays($this->interval_days),
        ])->save();
    }
}
