<?php

namespace App\Enums;

/** Tiền phiếu chăm hộ được trả bằng cách nào. */
enum BoardingPaymentMethod: string
{
    case TienMat = 'tien_mat';
    case ChuyenKhoan = 'chuyen_khoan';
    case Momo = 'momo';

    public function label(): string
    {
        return match ($this) {
            self::TienMat => 'Tiền mặt',
            self::ChuyenKhoan => 'Chuyển khoản',
            self::Momo => 'MoMo (trả online)',
        };
    }

    /** Admin tự ghi được; MoMo chỉ do cổng thanh toán báo về. */
    public static function ghiTay(): array
    {
        return [self::TienMat, self::ChuyenKhoan];
    }
}
