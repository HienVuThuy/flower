<?php

namespace App\Enums;

/** NHU CẦU của khách — trục thứ năm để duyệt hàng. */
enum ShoppingIntent: string
{
    case Gift = 'tang-nguoi-thuong';
    case Decor = 'trang-tri-khong-gian';
    case Event = 'khai-truong-su-kien';
    case Beginner = 'nguoi-moi-trong-cay';

    public function label(): string
    {
        return match ($this) {
            self::Gift => 'Tặng người thương',
            self::Decor => 'Trang trí không gian sống',
            self::Event => 'Khai trương & sự kiện',
            self::Beginner => 'Người mới bắt đầu trồng cây',
        };
    }

    public function tagline(): string
    {
        return match ($this) {
            self::Gift => 'Chọn hoa theo dịp và theo người nhận',
            self::Decor => 'Cây hợp từng góc trong nhà bạn',
            self::Event => 'Lẵng hoa, số lượng lớn, giao đúng giờ',
            self::Beginner => 'Bắt đầu bằng cây khó chết nhất',
        };
    }

    public function image(): string
    {
        return match ($this) {
            self::Gift => 'intent-tang-nguoi-thuong.jpg',
            self::Decor => 'intent-trang-tri.jpg',
            self::Event => 'intent-su-kien.jpg',
            self::Beginner => 'intent-nguoi-moi.jpg',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Gift => 'heart',
            self::Decor => 'flower2',
            self::Event => 'people',
            self::Beginner => 'droplet',
        };
    }

    public function heading(): string
    {
        return match ($this) {
            self::Gift => 'Tặng hoa cho người mình thương',
            self::Decor => 'Chọn cây cho không gian sống',
            self::Event => 'Hoa khai trương & sự kiện',
            self::Beginner => 'Bắt đầu trồng cây từ đâu?',
        };
    }

    public static function all(): array
    {
        return self::cases();
    }
}
