<?php

namespace App\Enums;

/** Danh sách hành vi được ghi nhận cho nền tảng Kinh tế số / gợi ý sản phẩm (Guide §9 và §25). */
enum UserEventType: string
{
    case ProductView = 'product_view';
    case CategoryView = 'category_view';
    case Search = 'search';
    case AddToCart = 'add_to_cart';

    case BuyNow = 'buy_now';
    case Wishlist = 'wishlist';
    case Purchase = 'purchase';

    public function label(): string
    {
        return match ($this) {
            self::ProductView => 'Xem sản phẩm',
            self::CategoryView => 'Xem danh mục',
            self::Search => 'Tìm kiếm',
            self::AddToCart => 'Thêm vào giỏ',
            self::BuyNow => 'Mua ngay',
            self::Wishlist => 'Thêm vào yêu thích',
            self::Purchase => 'Đặt hàng',
        };
    }
}
