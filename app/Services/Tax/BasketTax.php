<?php

namespace App\Services\Tax;

use App\Services\Checkout\CheckoutBasket;
use App\Services\Checkout\CheckoutLine;

/** Phần thuế GTGT nằm trong một lần thanh toán, TÁCH THEO TỪNG DÒNG. */
final readonly class BasketTax
{
    private function __construct(
        public array $lines,
        public bool $enabled,
        public string $shippingGross,
        public ?string $shippingRate,
        public ?string $shippingTax,
    ) {
    }

    public static function for(CheckoutBasket $basket): self
    {
        $may = app(TaxCalculator::class);

        if (! $may->enabled()) {
            return new self([], false, '0.00', null, null);
        }

        $phanBo = self::phanBoMaGiamGia($basket);

        $lines = [];

        foreach ($basket->lines->values() as $i => $line) {
            $giam = $phanBo[$i] ?? '0.00';
            $chiuThue = bcsub($line->lineTotal(), $giam, 2);
            $muc = $may->rateFor($line->product);

            $lines[] = [
                'line' => $line,
                'discount' => $giam,
                'taxable' => $chiuThue,
                'rate' => $muc,
                'tax' => $muc === null ? null : $may->extract($chiuThue, $muc),
            ];
        }

        $phi = $basket->shippingFee();
        $mucPhi = $may->rate();

        return new self(
            $lines,
            true,
            $phi,
            $mucPhi,
            $may->extract($phi, $mucPhi),
        );
    }

    private static function phanBoMaGiamGia(CheckoutBasket $basket): array
    {
        $tong = $basket->itemsTotal();

        $giamGia = $basket->orderDiscountTotal();
        $soDong = $basket->lines->count();

        if ($soDong === 0) {
            return [];
        }

        if (bccomp($giamGia, '0', 2) <= 0 || bccomp($tong, '0', 2) <= 0) {
            return array_fill(0, $soDong, '0.00');
        }

        $ket = [];
        $daChia = '0.00';

        foreach ($basket->lines->values() as $i => $line) {
            if ($i === $soDong - 1) {
                $ket[] = bcsub($giamGia, $daChia, 2);

                break;
            }

            $phan = bcdiv(bcmul($giamGia, $line->lineTotal(), 6), $tong, 2);
            $ket[] = $phan;
            $daChia = bcadd($daChia, $phan, 2);
        }

        return $ket;
    }

    public function itemsTax(): ?string
    {
        if (! $this->enabled) {
            return null;
        }

        $tong = '0.00';

        foreach ($this->lines as $dong) {
            $tong = bcadd($tong, $dong['tax'] ?? '0.00', 2);
        }

        return $tong;
    }

    public function total(): ?string
    {
        if (! $this->enabled) {
            return null;
        }

        return bcadd($this->itemsTax() ?? '0.00', $this->shippingTax ?? '0.00', 2);
    }

    public function hasTax(): bool
    {
        return $this->enabled && bccomp($this->total() ?? '0', '0', 2) > 0;
    }

    public function byRate(): array
    {
        if (! $this->enabled) {
            return [];
        }

        $nhom = [];

        foreach ($this->lines as $dong) {
            $this->gop($nhom, $dong['rate'], $dong['taxable'], $dong['tax']);
        }

        if (bccomp($this->shippingGross, '0', 2) > 0) {
            $this->gop($nhom, $this->shippingRate, $this->shippingGross, $this->shippingTax);
        }

        $rows = array_values($nhom);

        usort($rows, function (array $a, array $b): int {
            if ($a['rate'] === null) {
                return $b['rate'] === null ? 0 : 1;
            }

            if ($b['rate'] === null) {
                return -1;
            }

            return bccomp($b['rate'], $a['rate'], 6);
        });

        return $rows;
    }

    private function gop(array &$nhom, ?string $rate, string $gross, ?string $tax): void
    {
        $khoa = $rate ?? '';

        $nhom[$khoa] ??= ['rate' => $rate, 'net' => '0.00', 'tax' => '0.00'];

        $nhom[$khoa]['tax'] = bcadd($nhom[$khoa]['tax'], $tax ?? '0.00', 2);
        $nhom[$khoa]['net'] = bcadd($nhom[$khoa]['net'], bcsub($gross, $tax ?? '0.00', 2), 2);
    }

    public function netTotal(?string $grandTotal = null): ?string
    {
        if (! $this->enabled || $grandTotal === null) {
            return null;
        }

        return bcsub($grandTotal, $this->total() ?? '0.00', 2);
    }
}
