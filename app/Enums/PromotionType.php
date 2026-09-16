<?php

namespace App\Enums;

/** Kiểu tính giảm giá của một chương trình khuyến mại. */
enum PromotionType: string
{
    case Percent = 'percent';
    case FixedAmount = 'fixed_amount';
    case FixedPrice = 'fixed_price';
    case Combo = 'combo';
    case BuyXGetY = 'buy_x_get_y';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Giảm theo phần trăm',
            self::FixedAmount => 'Giảm số tiền',
            self::FixedPrice => 'Giá cố định',
            self::Combo => 'Combo (chưa hỗ trợ)',
            self::BuyXGetY => 'Mua X tặng Y (chưa hỗ trợ)',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Percent => '%',
            self::FixedAmount, self::FixedPrice => '₫',
            default => '',
        };
    }

    public function isImplemented(): bool
    {
        return in_array($this, [self::Percent, self::FixedAmount, self::FixedPrice], true);
    }

    public static function selectable(): array
    {
        return array_filter(self::cases(), fn (self $t) => $t->isImplemented());
    }
}
