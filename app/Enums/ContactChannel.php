<?php

namespace App\Enums;

/** Cách khách muốn cửa hàng liên hệ lại. */
enum ContactChannel: string
{
    case Phone = 'phone';
    case Zalo = 'zalo';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Gọi điện',
            self::Zalo => 'Nhắn Zalo',
            self::Email => 'Gửi email',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Phone => 'Nhanh nhất, trong giờ hành chính',
            self::Zalo => 'Gửi được ảnh mẫu và bảng giá',
            self::Email => 'Có báo giá bằng văn bản để lưu',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
