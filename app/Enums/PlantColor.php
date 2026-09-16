<?php

namespace App\Enums;

/** MÀU CHỦ ĐẠO của sản phẩm. */
enum PlantColor: string
{
    case Red = 'red';
    case Pink = 'pink';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case White = 'white';
    case Purple = 'purple';
    case Blue = 'blue';
    case Green = 'green';
    case Brown = 'brown';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Red => 'Đỏ',
            self::Pink => 'Hồng',
            self::Orange => 'Cam',
            self::Yellow => 'Vàng',
            self::White => 'Trắng',
            self::Purple => 'Tím',
            self::Blue => 'Xanh dương',
            self::Green => 'Xanh lá',
            self::Brown => 'Nâu',
            self::Mixed => 'Nhiều màu',
        };
    }

    public function hex(): ?string
    {
        return match ($this) {
            self::Red => '#c62828',
            self::Pink => '#ec8ba7',
            self::Orange => '#ef7b2b',
            self::Yellow => '#f2c229',
            self::White => '#f6f4ef',
            self::Purple => '#8e6bb5',
            self::Blue => '#4a7fb5',
            self::Green => '#4c8b5b',
            self::Brown => '#8a6a4b',
            self::Mixed => null,
        };
    }

    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
