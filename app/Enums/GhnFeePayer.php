<?php

namespace App\Enums;

/** Ai trả cước cho GHN trên một vận đơn. */
enum GhnFeePayer: string
{
    case Shop = 'shop';
    case Buyer = 'buyer';

    public function ghnCode(): int
    {
        return match ($this) {
            self::Shop => 1,
            self::Buyer => 2,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Shop => 'Cửa hàng trả cước',
            self::Buyer => 'Người nhận trả cước',
        };
    }
}
