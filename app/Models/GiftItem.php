<?php

namespace App\Models;

use App\Enums\GiftKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Thứ được tặng: một sản phẩm đang có, hoặc một vật phẩm tặng riêng.
 * Xem migration create_gift_tables.
 */
class GiftItem extends Model
{
    protected $fillable = [
        'name',
        'kind',
        'product_id',
        'product_variant_id',
        'stock_quantity',
        'value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => GiftKind::class,
            'stock_quantity' => 'integer',
            'value' => 'decimal:2',
            'is_active' => 'boolean',
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

    public function campaigns(): HasMany
    {
        return $this->hasMany(GiftCampaign::class);
    }

    public function laSanPham(): bool
    {
        return $this->product_id !== null;
    }

    /**
     * Số lượng còn tặng được; null = không giới hạn (sản phẩm tắt quản lý kho).
     *
     * Trỏ sản phẩm thì đọc ĐÚNG tồn kho của sản phẩm / quy cách — một món
     * không có hai con số tồn.
     */
    public function tonKhoCon(): ?int
    {
        if (! $this->laSanPham()) {
            return (int) $this->stock_quantity;
        }

        if ($this->product_variant_id !== null) {
            $v = $this->variant;

            return $v === null ? 0 : ($v->track_inventory ? (int) $v->stock_quantity : null);
        }

        $p = $this->product;

        return $p === null ? 0 : ($p->track_inventory ? (int) $p->stock_quantity : null);
    }
}
