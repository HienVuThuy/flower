<?php

namespace App\Enums;

/** Hình thức bán của sản phẩm hoa - cây cảnh. */
enum SellingForm: string
{
    case Bouquet = 'bouquet';
    case Pot = 'pot';
    case Branch = 'branch';
    case Basket = 'basket';
    case Box = 'box';
    case Arrangement = 'arrangement';
    case Original = 'original';
    case Set = 'set';
    case Gift = 'gift';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bouquet => 'Bó hoa',
            self::Pot => 'Cây chậu',
            self::Branch => 'Cành',
            self::Basket => 'Giỏ hoa',
            self::Box => 'Hộp hoa',
            self::Arrangement => 'Lẵng / kệ hoa',
            self::Original => 'Cây nguyên bản',
            self::Set => 'Set',
            self::Gift => 'Quà tặng',
            self::Other => 'Khác',
        };
    }

    public function careProfile(): CareProfile
    {
        return match ($this) {
            self::Pot, self::Original => CareProfile::LivingPlant,
            self::Bouquet, self::Branch, self::Basket, self::Box, self::Arrangement => CareProfile::CutFlower,
            self::Set, self::Gift, self::Other => CareProfile::Minimal,
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

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
