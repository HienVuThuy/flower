<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một món quà mặc định của một sản phẩm. Xem migration create_product_gifts_table.
 *
 * `product_id`, `gift_item_id` KHÔNG nằm trong $fillable: gắn quà vào sản
 * phẩm nào do đường dẫn quản trị quyết định, không phải ô biểu mẫu.
 */
class ProductGift extends Model
{
    protected $fillable = ['per_quantity', 'gift_quantity', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'per_quantity' => 'integer',
            'gift_quantity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function giftItem(): BelongsTo
    {
        return $this->belongsTo(GiftItem::class);
    }

    /** Mua `$soLuongMua` món thì được bao nhiêu quà (chưa xét tồn kho quà). */
    public function soQuaCho(int $soLuongMua): int
    {
        return intdiv(max(0, $soLuongMua), max(1, $this->per_quantity)) * max(1, $this->gift_quantity);
    }
}
