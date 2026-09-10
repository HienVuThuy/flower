<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một dòng hàng trên phiếu nhập.
 *
 * `product_name` / `variant_name` là BẢN CHỤP lúc nhập: sản phẩm đổi tên
 * hay bị xoá thì phiếu cũ vẫn kể được câu chuyện của nó.
 */
class StockReceiptItem extends Model
{
    protected $fillable = [
        'stock_receipt_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** Thành tiền; null khi chưa điền giá — KHÁC 0. */
    public function lineCost(): ?float
    {
        return $this->unit_cost === null ? null : (float) $this->unit_cost * $this->quantity;
    }
}
