<?php

namespace App\Models;

use App\Enums\GiftReturnRule;
use App\Enums\GiftStockRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một món quà mặc định của một sản phẩm (hoặc một quy cách của nó). */
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

    public function soQuaCho(int $soLuongMua): int
    {
        $n = intdiv(max(0, $soLuongMua), max(1, $this->per_quantity)) * max(1, $this->gift_quantity);

        return $this->max_quantity !== null ? min($n, $this->max_quantity) : $n;
    }

    public function apDungCho(int $productId, ?int $variantId): bool
    {
        return (int) $this->product_id === $productId
            && ($this->product_variant_id === null || (int) $this->product_variant_id === (int) $variantId);
    }

    public function moTaLuat(): string
    {
        return 'Mua mỗi ' . $this->per_quantity . ' → tặng ' . $this->gift_quantity
            . ($this->max_quantity !== null ? ', tối đa ' . $this->max_quantity . ' mỗi đơn' : '');
    }
}
