<?php

namespace App\Enums;

/** Giao diện của một quyển sổ — màu giấy, màu nhấn, hoa văn. */
enum JournalTheme: string
{
    case Paper = 'paper';
    case Leaf = 'leaf';
    case Terracotta = 'terracotta';
    case Dusk = 'dusk';
    case Sand = 'sand';
    case Moss = 'moss';

    public function label(): string
    {
        return match ($this) {
            self::Paper => 'Giấy trắng',
            self::Leaf => 'Lá non',
            self::Terracotta => 'Gốm đỏ',
            self::Dusk => 'Chiều tím',
            self::Sand => 'Cát ấm',
            self::Moss => 'Rêu đá',
        };
    }

    public function token(): string
    {
        return 'journal-theme--' . $this->value;
    }

    public function pattern(): ?string
    {
        return match ($this) {
            self::Paper => null,
            self::Leaf => 'leaves',
            self::Terracotta => 'arches',
            self::Dusk => 'dots',
            self::Sand => 'grain',
            self::Moss => 'pebbles',
        };
    }

    public static function default(): self
    {
        return self::Paper;
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
