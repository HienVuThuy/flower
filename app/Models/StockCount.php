<?php

namespace App\Models;

use App\Enums\StockCountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Phiếu kiểm kê kho. */
class StockCount extends Model
{
    protected $fillable = [
        'code',
        'note',
        'counted_at',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'counted_at' => 'date',
            'status' => StockCountStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class)->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPosted(): bool
    {
        return $this->status === StockCountStatus::Posted;
    }

    public function actorLabel(): string
    {
        return $this->createdBy?->name ?? $this->created_by_name ?? 'Không rõ';
    }

    public function soDongLech(): int
    {
        return $this->items->filter(fn (StockCountItem $i) => $i->chenhLech() !== 0)->count();
    }
}
