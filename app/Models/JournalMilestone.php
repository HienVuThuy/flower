<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một mốc cần đạt trong sổ Mục tiêu.
 * ⚠️ DỮ LIỆU RIÊNG TƯ — xem QĐ-123.
 */
class JournalMilestone extends Model
{
    protected $fillable = ['title', 'due_date', 'sort_order'];

    protected function casts(): array
    {
        return [
            'done_at' => 'datetime',
            'due_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone()
            && $this->due_date !== null
            && $this->due_date->isPast();
    }
}
