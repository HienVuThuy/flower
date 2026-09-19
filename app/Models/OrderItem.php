<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một dòng trong đơn hàng. */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'product_sku',
        'variant_name',
        'promotion_name',
        'unit_base_price',
        'unit_price',
        'quantity',
        'line_total',

        'discount_amount',
        'tax_rate',
        'tax_amount',
        'is_gift',
        'gift_promotion_id',
        'gift_item_id',
        'parent_item_id',
        'product_gift_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_base_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:5',
            'tax_amount' => 'decimal:2',
            'is_gift' => 'boolean',
        ];
    }

    public function scopeHangBan(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('order_items.is_gift', false);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function netTotal(): string
    {
        return bcsub(
            bcsub((string) $this->line_total, (string) $this->discount_amount, 2),
            (string) ($this->tax_amount ?? '0.00'),
            2,
        );
    }

    public function isTaxed(): bool
    {
        return $this->tax_rate !== null;
    }

    public function wasDiscounted(): bool
    {
        return bccomp((string) $this->unit_price, (string) $this->unit_base_price, 2) < 0;
    }
}
