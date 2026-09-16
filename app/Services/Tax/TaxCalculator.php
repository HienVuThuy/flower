<?php

namespace App\Services\Tax;

use App\Models\Product;
use App\Models\Setting;

/** NƠI DUY NHẤT tính thuế giá trị gia tăng. */
class TaxCalculator
{
    public const SETTING_KEY = 'tax_rate';

    public function rate(): string
    {
        if (! $this->enabled()) {
            return '0';
        }

        $luu = Setting::get(self::SETTING_KEY);

        if (is_numeric($luu) && (float) $luu >= 0 && (float) $luu < 1) {
            return (string) (float) $luu;
        }

        return (string) (float) config('tax.default_rate', 0.08);
    }

    public function rateFor(?Product $product): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $nhom = $product?->taxClass;

        if (! $nhom) {
            return $this->rate();
        }

        return $nhom->rateString();
    }

    public static function formatRate(?string $rate): string
    {
        if ($rate === null) {
            return 'Không chịu VAT';
        }

        return rtrim(rtrim(number_format((float) $rate * 100, 2, ',', '.'), '0'), ',') . '%';
    }

    public function enabled(): bool
    {
        return (bool) config('tax.enabled', true);
    }

    public function ratePercent(): string
    {
        return rtrim(rtrim(bcmul($this->rate(), '100', 2), '0'), '.') ?: '0';
    }

    public function extract(string $grossAmount, ?string $rate = null): string
    {
        $rate ??= $this->rate();

        if (bccomp($rate, '0', 6) <= 0) {
            return '0.00';
        }

        $scale = (int) config('tax.scale', 2);

        $net = bcdiv($grossAmount, bcadd('1', $rate, 6), $scale + 4);
        $tax = bcsub($grossAmount, $net, $scale + 4);

        return $this->lamTron($tax, $scale);
    }

    public function net(string $grossAmount, ?string $rate = null): string
    {
        return bcsub($grossAmount, $this->extract($grossAmount, $rate), (int) config('tax.scale', 2));
    }

    private function lamTron(string $so, int $scale): string
    {
        $nua = '0.' . str_repeat('0', $scale) . '5';

        return bcadd($so, bccomp($so, '0', $scale + 4) < 0 ? '-' . $nua : $nua, $scale);
    }
}
