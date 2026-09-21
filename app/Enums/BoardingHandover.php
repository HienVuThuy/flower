<?php

namespace App\Enums;

/** Cây đến và rời cửa hàng bằng cách nào. */
enum BoardingHandover: string
{
    case TuMang = 'tu_mang';
    case CuaHangLay = 'cua_hang_lay';

    public function label(): string
    {
        return match ($this) {
            self::TuMang => 'Tôi tự mang cây đến và tự nhận lại',
            self::CuaHangLay => 'Cửa hàng đến lấy và mang trả tận nơi',
        };
    }
}
