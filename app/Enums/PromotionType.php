<?php

namespace App\Enums;

/**
 * Kiểu tính giảm giá của một chương trình khuyến mại.
 *
 * Ba kiểu đầu đã triển khai đầy đủ. `Combo` và `BuyXGetY` được khai
 * báo sẵn nhưng CHƯA tính giá được — chúng cần thêm dữ liệu (danh
 * sách sản phẩm trong combo, sản phẩm tặng kèm) mà scope hiện tại
 * chưa có. Khai sẵn ở đây để tên/giá trị lưu trong DB là ổn định,
 * khi làm sau này không phải migrate lại dữ liệu cũ.
 *
 * Xem docs/DOMAIN-DECISIONS.md (QĐ-03).
 * PricingService::resolve() chủ động bỏ qua hai kiểu chưa hỗ trợ
 * thay vì tính sai — xem isImplemented().
 */
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

    /** Đơn vị của discount_value, dùng làm hậu tố trong form admin. */
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

    /** Các kiểu admin được phép chọn ở thời điểm hiện tại. */
    public static function selectable(): array
    {
        return array_filter(self::cases(), fn (self $t) => $t->isImplemented());
    }
}
