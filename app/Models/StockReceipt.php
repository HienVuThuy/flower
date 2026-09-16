<?php

namespace App\Models;

use App\Enums\StockReceiptKind;
use App\Enums\StockReceiptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Một phiếu nhập kho. */
class StockReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'supplier_id',
        'supplier',
        'note',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'kind' => StockReceiptKind::class,
            'return_reason' => \App\Enums\ReturnReason::class,
            'settlement' => \App\Enums\ReturnSettlement::class,
            'settlement_amount' => 'decimal:2',
            'status' => StockReceiptStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class)->orderBy('id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function tenNhaCungCap(): ?string
    {
        return $this->supplier ?: $this->supplier()->first()?->name;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function laPhieuTra(): bool
    {
        return $this->kind === StockReceiptKind::TraNcc;
    }

    public function phieuGoc(): BelongsTo
    {
        return $this->belongsTo(self::class, 'return_of_id');
    }

    public function laTonDauKy(): bool
    {
        return $this->kind === StockReceiptKind::TonDauKy;
    }

    public function isPosted(): bool
    {
        return $this->status === StockReceiptStatus::Posted;
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function totalCost(): float
    {
        return (float) $this->items
            ->filter(fn (StockReceiptItem $i) => $i->unit_cost !== null)
            ->sum(fn (StockReceiptItem $i) => (float) $i->unit_cost * $i->quantity);
    }

    public function hasUnpricedItems(): bool
    {
        return $this->items->contains(fn (StockReceiptItem $i) => $i->unit_cost === null);
    }

    public function actorLabel(): string
    {
        return $this->createdBy?->name ?? $this->created_by_name ?? 'Không rõ';
    }
}
