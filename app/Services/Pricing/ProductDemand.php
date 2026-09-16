<?php

namespace App\Services\Pricing;

use App\Models\Product;
use Illuminate\Support\Carbon;

/** Số liệu nhu cầu của MỘT sản phẩm — chỉ là số, chưa có ý kiến nào. */
final readonly class ProductDemand
{
    public function __construct(
        public Product $product,
        public int $views,
        public int $addToCarts,
        public int $unitsSold,
        public int $ordersWith,
        public ?Carbon $lastSoldAt,
        public int $windowDays,
    ) {
    }

    public function conversionRate(): ?float
    {
        if ($this->views <= 0) {
            return null;
        }

        return $this->ordersWith / $this->views * 100;
    }

    public function cartRate(): ?float
    {
        if ($this->views <= 0) {
            return null;
        }

        return $this->addToCarts / $this->views * 100;
    }

    public function daysSinceLastSale(): ?int
    {
        return $this->lastSoldAt?->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function neverSold(): bool
    {
        return $this->lastSoldAt === null;
    }

    public function ageInDays(): int
    {
        return (int) $this->product->created_at?->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function stock(): ?int
    {
        return $this->product->track_inventory ? (int) $this->product->stock_quantity : null;
    }

    public function isDiscounted(): bool
    {
        if (! $this->product->relationLoaded('promotions')) {
            return false;
        }

        return $this->product->price()->isDiscounted();
    }
}
