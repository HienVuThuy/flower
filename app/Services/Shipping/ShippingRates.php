<?php

namespace App\Services\Shipping;

/** Phí giao hàng của một tỉnh/thành. */
class ShippingRates
{
    public function zoneOf(?string $province): string
    {
        $default = (string) config('shipping.default_zone', 'far');

        if ($province === null || trim($province) === '') {
            return $default;
        }

        $bang = config('shipping.zone_of');
        $zone = $bang[trim($province)] ?? $this->theoTenRutGon($bang, $province) ?? $default;

        return isset(config('shipping.zones')[$zone]) ? $zone : $default;
    }

    private function theoTenRutGon(array $bang, string $province): ?string
    {
        $goc = self::rutGon($province);

        foreach ($bang as $ten => $zone) {
            if (self::rutGon($ten) === $goc) {
                return $zone;
            }
        }

        return null;
    }

    private static function rutGon(string $ten): string
    {
        $ten = mb_strtolower(trim($ten));
        $ten = preg_replace('/^(thành phố|tỉnh|tp\.?)\s+/u', '', $ten) ?? $ten;

        return trim(preg_replace('/\s+/u', ' ', $ten) ?? $ten);
    }

    public function zoneLabel(?string $province): string
    {
        $zone = $this->zoneOf($province);

        return (string) (config("shipping.zones.{$zone}.label") ?? 'Không rõ vùng');
    }

    public function feeFor(?string $province): string
    {
        $zone = $this->zoneOf($province);
        $fee = config("shipping.zones.{$zone}.fee", 0);

        return number_format((float) $fee, 2, '.', '');
    }

    public function freeFrom(): string
    {
        return number_format((float) \App\Services\Shop\ThamSoKinhDoanh::giaTri('shipping.free_from'), 2, '.', '');
    }

    public function zones(): array
    {
        $out = [];

        foreach (config('shipping.zones', []) as $key => $zone) {
            $out[$key] = [
                'label' => $zone['label'],
                'fee' => (float) $zone['fee'],
                'provinces' => [],
            ];
        }

        foreach (config('shipping.zone_of', []) as $province => $zone) {
            if (isset($out[$zone])) {
                $out[$zone]['provinces'][] = $province;
            }
        }

        return $out;
    }
}
