<?php

namespace App\Services\Checkout;

use App\Models\Product;
use App\Models\ProductVariant;

/** Một dòng hàng sắp được thanh toán. */
final readonly class CheckoutLine
{
    public function __construct(
        public Product $product,
        public ?ProductVariant $variant,
        public int $quantity,
    ) {
    }

    public function hasVariantPrice(): bool
    {
        return $this->variant !== null && $this->variant->price !== null;
    }

    public function unitBasePrice(): string
    {
        return $this->hasVariantPrice()
            ? (string) $this->variant->price
            : $this->product->price()->basePrice;
    }

    public function unitPrice(): string
    {
        return $this->hasVariantPrice()
            ? (string) $this->variant->price
            : $this->product->price()->finalPrice;
    }

    public function lineTotal(): string
    {
        return bcmul($this->unitPrice(), (string) $this->quantity, 2);
    }

    public function promotionName(): ?string
    {
        return $this->hasVariantPrice()
            ? null
            : $this->product->price()->promotion?->name;
    }

    public function availableStock(): ?int
    {
        if ($this->variant) {
            return $this->variant->track_inventory ? (int) $this->variant->stock_quantity : null;
        }

        return $this->product->track_inventory ? (int) $this->product->stock_quantity : null;
    }
}
