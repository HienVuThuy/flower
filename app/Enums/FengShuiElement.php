<?php

namespace App\Enums;

/** NGŨ HÀNH — mệnh mà một cây được coi là hợp. */
enum FengShuiElement: string
{
    case Kim = 'kim';
    case Moc = 'moc';
    case Thuy = 'thuy';
    case Hoa = 'hoa';
    case Tho = 'tho';

    public function label(): string
    {
        return match ($this) {
            self::Kim => 'Mệnh Kim',
            self::Moc => 'Mệnh Mộc',
            self::Thuy => 'Mệnh Thuỷ',
            self::Hoa => 'Mệnh Hoả',
            self::Tho => 'Mệnh Thổ',
        };
    }

    public function colorHint(): string
    {
        return match ($this) {
            self::Kim => 'trắng, xám, ánh kim',
            self::Moc => 'xanh lá, xanh lục',
            self::Thuy => 'xanh dương, đen',
            self::Hoa => 'đỏ, hồng, cam, tím',
            self::Tho => 'vàng, nâu đất',
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
