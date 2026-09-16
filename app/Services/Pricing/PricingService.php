<?php

namespace App\Services\Pricing;

use App\Enums\PromotionType;
use App\Models\Product;
use App\Models\Promotion;

/** Nguồn sự thật DUY NHẤT để tính giá bán cuối cùng. */
class PricingService
{
    public function resolve(Product $product): ProductPrice
    {
        $base = $product->base_price;

        if ($base === null) {
            return new ProductPrice(null, null);
        }

        $promotion = $this->bestPromotionFor($product, $base);

        if (! $promotion) {
            return new ProductPrice($base, $base);
        }

        $final = $this->applyDiscount($base, $promotion, $product);

        if (bccomp($final, '0', 2) === -1) {
            $final = '0.00';
        }

        if (bccomp($final, $base, 2) !== -1) {
            return new ProductPrice($base, $base);
        }

        return new ProductPrice($base, $final, $promotion);
    }

    private function bestPromotionFor(Product $product, string $base): ?Promotion
    {
        $candidates = $product->promotions
            ->filter(fn (Promotion $p) => $p->isRunning() && $p->type->isImplemented());

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortBy(fn (Promotion $p) => [
                (float) $this->applyDiscount($base, $p, $product),
                -$p->priority,
                -$p->id,
            ])
            ->first();
    }

    private function applyDiscount(string $base, Promotion $promotion, Product $product): string
    {
        $pivot = $promotion->pivot;

        $type = $pivot?->discount_type
            ? PromotionType::tryFrom($pivot->discount_type)
            : $promotion->type;

        $value = $pivot?->discount_value ?? $promotion->discount_value;

        if ($type === null || $value === null) {
            return $base;
        }

        return match ($type) {
            PromotionType::Percent => bcsub(
                $base,
                bcdiv(bcmul($base, (string) $value, 4), '100', 2),
                2
            ),
            PromotionType::FixedAmount => bcsub($base, (string) $value, 2),
            PromotionType::FixedPrice => bcadd((string) $value, '0', 2),

            default => $base,
        };
    }

    public function preview(?string $basePrice, PromotionType $type, ?float $value): ?string
    {
        if ($basePrice === null || $value === null || ! $type->isImplemented()) {
            return null;
        }

        $final = match ($type) {
            PromotionType::Percent => bcsub($basePrice, bcdiv(bcmul($basePrice, (string) $value, 4), '100', 2), 2),
            PromotionType::FixedAmount => bcsub($basePrice, (string) $value, 2),
            PromotionType::FixedPrice => bcadd((string) $value, '0', 2),
            default => $basePrice,
        };

        return bccomp($final, '0', 2) === -1 ? '0.00' : $final;
    }
}
