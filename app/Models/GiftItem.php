<?php

namespace App\Models;

use App\Enums\GiftKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Thứ được tặng: một sản phẩm đang có, hoặc một vật phẩm tặng riêng. */
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

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    /** Vật phẩm quà ứng với một sản phẩm / quy cách đang bán — dùng lại nếu đã có. */
    public static function tuSanPham(Product $sp, ?int $variantId = null, mixed $giaTri = null): self
    {
        $qc = $variantId ? ProductVariant::find($variantId) : null;

        return self::query()
            ->where('product_id', $sp->id)
            ->when($variantId !== null, fn ($q) => $q->where('product_variant_id', $variantId), fn ($q) => $q->whereNull('product_variant_id'))
            ->first()
            ?? self::create([
                'name' => $sp->name . ($qc ? ' — ' . $qc->name : ''),
                'kind' => ($sp->product_type?->value ?? null) === 'plant' ? GiftKind::Cay : GiftKind::DoVat,
                'product_id' => $sp->id,
                'product_variant_id' => $variantId,
                'value' => $giaTri,
                'is_active' => true,
            ]);
    }

    public function laSanPham(): bool
    {
        return $this->product_id !== null;
    }

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
