<?php

namespace App\Enums;

/** Lô hoa đang dùng hay đã dùng hết. */
enum FlowerLotStatus: string
{
    case DangDung = 'dang_dung';
    case DaDong = 'da_dong';

    public function label(): string
    {
        return match ($this) {
            self::DangDung => 'Đang dùng',
            self::DaDong => 'Đã đóng lô',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::DangDung => 'warning',
            self::DaDong => 'success',
        };
    }
}
