<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một dòng của phiếu đổi hàng — đi về hoặc đi ra. */
class ExchangeItem extends Model
{
    public const TRA_VE = 'tra_ve';

    public const GUI_DI = 'gui_di';

    protected $fillable = [
        'chieu',
        'order_item_id',
        'product_id',
        'product_variant_id',
        'ten_hang',
        'quantity',
        'unit_price',
        'restock',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'restock' => 'boolean',
        ];
    }

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function laTraVe(): bool
    {
        return $this->chieu === self::TRA_VE;
    }

    public function thanhTien(): string
    {
        return bcmul((string) $this->unit_price, (string) $this->quantity, 2);
    }
}
