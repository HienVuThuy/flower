<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một dòng đếm trong phiếu kiểm kê. */
class StockCountItem extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'system_quantity',
        'counted_quantity',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'integer',
            'counted_quantity' => 'integer',
            'applied_difference' => 'integer',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function chenhLech(): int
    {
        return $this->counted_quantity - $this->system_quantity;
    }
}
