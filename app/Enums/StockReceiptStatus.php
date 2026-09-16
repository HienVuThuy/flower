<?php

namespace App\Enums;

/** Phiếu nhập kho đã cộng vào kho hay chưa. */
enum StockReceiptStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Posted => 'Đã ghi sổ',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Posted => 'success',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Draft => 'Chưa cộng vào kho. Sửa được thoải mái.',
            self::Posted => 'Đã cộng vào kho. Không sửa được nữa — nhầm thì lập phiếu điều chỉnh.',
        };
    }
}
