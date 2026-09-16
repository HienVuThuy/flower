<?php

namespace App\Services\Shipping;

use App\Services\Checkout\CheckoutBasket;
use Illuminate\Support\Facades\Log;

/** Hỏi GHN "giao tới đây tốn bao nhiêu" — và tự tính lại ở phía máy chủ. */
class ShippingQuote
{
    public function __construct(
        private readonly GHNService $ghn,
        private readonly ShippingRates $rates,
    ) {
    }

    public function feeFor(CheckoutBasket $basket, ?int $districtId, ?string $wardCode): string
    {
        $fee = $this->ghnFee($basket, $districtId, $wardCode);

        if ($fee !== null) {
            return number_format($fee, 2, '.', '');
        }

        return $this->rates->feeFor($basket->province);
    }

    public function ghnFee(CheckoutBasket $basket, ?int $districtId, ?string $wardCode): ?int
    {
        if (! $districtId || ! $wardCode) {
            return null;
        }

        $from = (int) config('services.ghn.from_district_id');

        if ($from <= 0) {
            Log::warning('Chưa khai GHN_FROM_DISTRICT_ID nên không tính được phí GHN.');

            return null;
        }

        $response = $this->ghn->calculateFee(array_merge([
            'from_district_id' => $from,
            'to_district_id' => $districtId,
            'to_ward_code' => $wardCode,
        ], $this->ghn->packageParameters($this->weightOf($basket))));

        if (($response['code'] ?? null) !== 200) {
            return null;
        }

        $total = $response['data']['total'] ?? null;

        return is_numeric($total) ? (int) $total : null;
    }

    public function weightOf(CheckoutBasket $basket): int
    {
        $tong = 0;

        foreach ($basket->lines as $line) {
            $tong += $line->product->shippingWeight($line->variant) * $line->quantity;
        }

        return max($tong, (int) config('services.ghn.default_weight', 200));
    }
}
