<?php

namespace App\Models;

use App\Enums\GiftReturnRule;
use App\Enums\GiftStockRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một món quà mặc định của một sản phẩm (hoặc một quy cách của nó).
 * Xem migration create_product_gifts_table và add_rules_to_product_gifts_table.
 *
 * `product_id`, `product_variant_id`, `gift_item_id` KHÔNG nằm trong
 * $fillable: gắn quà vào sản phẩm / quy cách nào do controller đặt sau khi
 * đã kiểm quy cách thuộc đúng sản phẩm — không phải ô biểu mẫu đổ thẳng vào.
 */
class ProductGift extends Model
{
    protected $fillable = [
        'per_quantity',
        'gift_quantity',
        'max_quantity',
        'khi_thieu_kho',
        'tra_hang',
        'cho_doi_hang',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'per_quantity' => 'integer',
            'gift_quantity' => 'integer',
            'max_quantity' => 'integer',
            'khi_thieu_kho' => GiftStockRule::class,
            'tra_hang' => GiftReturnRule::class,
            'cho_doi_hang' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function giftItem(): BelongsTo
    {
        return $this->belongsTo(GiftItem::class);
    }

    /**
     * Mua `$soLuongMua` món thì được bao nhiêu quà — ⌊mua ÷ N⌋ × M, kẹp theo
     * trần mỗi đơn. Chưa xét tồn kho quà (xem GiftResolver).
     */
    public function soQuaCho(int $soLuongMua): int
    {
        $n = intdiv(max(0, $soLuongMua), max(1, $this->per_quantity)) * max(1, $this->gift_quantity);

        return $this->max_quantity !== null ? min($n, $this->max_quantity) : $n;
    }

    /** Dòng hàng (sản phẩm, quy cách) này có kích hoạt quà không. */
    public function apDungCho(int $productId, ?int $variantId): bool
    {
        return (int) $this->product_id === $productId
            && ($this->product_variant_id === null || (int) $this->product_variant_id === (int) $variantId);
    }

    /** Mô tả luật cho người đọc: "Mua mỗi 2 → tặng 1, tối đa 3". */
    public function moTaLuat(): string
    {
        return 'Mua mỗi ' . $this->per_quantity . ' → tặng ' . $this->gift_quantity
            . ($this->max_quantity !== null ? ', tối đa ' . $this->max_quantity . ' mỗi đơn' : '');
    }
}
