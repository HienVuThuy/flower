<?php

namespace App\Enums;

/**
 * Kiểu giảm giá của mã coupon.
 *
 * Cố ý CHỈ có hai kiểu, ít hơn PromotionType. Coupon giảm trên TỔNG
 * TIỀN HÀNG của đơn, nên "giá cố định" (fixed_price) vô nghĩa: không
 * thể ấn định cả đơn hàng thành một con số. Combo và mua-X-tặng-Y cũng
 * là chuyện của khuyến mại theo sản phẩm, không phải của mã giảm giá.
 */
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

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
