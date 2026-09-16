<?php

namespace App\Enums;

/** HÀNG CHÍNH hay HÀNG PHỤ TRỢ. */
enum CategoryKind: string
{
    case Plant = 'plant';
    case Supply = 'supply';

    public function label(): string
    {
        return match ($this) {
            self::Plant => 'Hoa & cây cảnh',
            self::Supply => 'Phụ kiện & vật tư',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Plant => 'Hàng chính của cửa hàng',
            self::Supply => 'Đồ dùng và vật tư mua kèm',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
