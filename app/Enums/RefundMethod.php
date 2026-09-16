<?php

namespace App\Enums;

/** Tiền hoàn đi đường nào. */
enum RefundMethod: string
{
    case Momo = 'momo';
    case BankTransfer = 'chuyen_khoan';
    case Cash = 'tien_mat';

    public function label(): string
    {
        return match ($this) {
            self::Momo => 'Hoàn qua MoMo (tự động)',
            self::BankTransfer => 'Chuyển khoản ngân hàng',
            self::Cash => 'Tiền mặt',
        };
    }

    public function tuDong(): bool
    {
        return $this === self::Momo;
    }

    public function batBuocMaGiaoDich(): bool
    {
        return $this === self::BankTransfer;
    }
}
