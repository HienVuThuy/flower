<?php

namespace App\Enums;

/** Loại quà — để khách và cửa hàng đọc được đây là thứ gì. */
enum GiftKind: string
{
    case Cay = 'cay';
    case DoVat = 'do_vat';
    case QuaTang = 'qua_tang';

    public function label(): string
    {
        return match ($this) {
            self::Cay => 'Cây',
            self::DoVat => 'Đồ vật',
            self::QuaTang => 'Quà tặng',
        };
    }
}
