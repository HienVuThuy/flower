<?php

namespace App\Enums;

/** Kiểu giảm giá của mã coupon. */
enum CouponType: string
{
    case Percent = 'percent';
    case FixedAmount = 'fixed_amount';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Giảm theo phần trăm',
            self::FixedAmount => 'Giảm số tiền cố định',
        };
    }

    public function unit(): string
    {
        return $this === self::Percent ? '%' : 'đ';
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
