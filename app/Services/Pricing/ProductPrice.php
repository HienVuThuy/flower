<?php

namespace App\Services\Pricing;

use App\Models\Promotion;

/** Kết quả tính giá của một sản phẩm tại thời điểm hiện tại. */
final readonly class ProductPrice
{
    public function __construct(
        public ?string $basePrice,
        public ?string $finalPrice,
        public ?Promotion $promotion = null,
    ) {}

    public function isDiscounted(): bool
    {
        return $this->promotion !== null
            && $this->basePrice !== null
            && $this->finalPrice !== null
            && bccomp($this->finalPrice, $this->basePrice, 2) === -1;
    }

    public function discountAmount(): ?string
    {
        if (! $this->isDiscounted()) {
            return null;
        }

        return bcsub($this->basePrice, $this->finalPrice, 2);
    }

    public function discountPercent(): ?int
    {
        if (! $this->isDiscounted() || bccomp($this->basePrice, '0', 2) !== 1) {
            return null;
        }

        return (int) round(((float) $this->discountAmount() / (float) $this->basePrice) * 100);
    }

    public function isContactForPrice(): bool
    {
        return $this->basePrice === null;
    }
}
