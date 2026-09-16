<?php

namespace App\Enums;

/** Phiếu kiểm kê đã điều chỉnh kho hay chưa. */
enum StockCountStatus: string
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
            self::Draft => 'Chưa điều chỉnh kho. Xoá được nếu đếm nhầm.',
            self::Posted => 'Đã điều chỉnh kho theo chênh lệch. Không sửa được — đếm nhầm thì lập phiếu mới.',
        };
    }
}
