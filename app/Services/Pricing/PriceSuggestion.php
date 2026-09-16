<?php

namespace App\Services\Pricing;

use App\Enums\PriceSignal;

/** Một đề xuất giá cho admin, kèm ĐÚNG những con số đã sinh ra nó. */
final readonly class PriceSuggestion
{
    public function __construct(
        public ProductDemand $demand,
        public PriceSignal $signal,
        public array $evidence,
        public ?string $anchor = null,
    ) {
    }

    public function product()
    {
        return $this->demand->product;
    }
}
