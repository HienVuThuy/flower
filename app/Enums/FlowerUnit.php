<?php

namespace App\Enums;

/** Đơn vị mua hoa. */
enum FlowerUnit: string
{
    case Canh = 'canh';
    case Bo = 'bo';
    case Chuc = 'chuc';
    case Kg = 'kg';
    case Thung = 'thung';

    public function label(): string
    {
        return match ($this) {
            self::Canh => 'cành',
            self::Bo => 'bó',
            self::Chuc => 'chục',
            self::Kg => 'kg',
            self::Thung => 'thùng',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
