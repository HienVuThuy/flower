<?php

namespace App\Enums;

/** Hình thức ưu đãi của một chương trình khuyến mại: giảm giá sản phẩm hoặc tặng quà. */
enum PromotionType: string
{
    case Percent = 'percent';
    case FixedAmount = 'fixed_amount';
    case FixedPrice = 'fixed_price';
    case TangQua = 'tang_qua';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Giảm theo phần trăm',
            self::FixedAmount => 'Giảm số tiền',
            self::FixedPrice => 'Giá cố định',
            self::TangQua => 'Tặng quà',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Percent => '%',
            self::FixedAmount, self::FixedPrice => '₫',
            self::TangQua => '',
        };
    }

    public function laGiamGia(): bool
    {
        return $this !== self::TangQua;
    }

    public static function kieuGiamGia(): array
    {
        return array_filter(self::cases(), fn (self $t) => $t->laGiamGia());
    }

    public static function selectable(): array
    {
        return self::cases();
    }
}
