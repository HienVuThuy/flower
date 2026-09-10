<?php

namespace App\Enums;

/**
 * Phiếu nhập kho đã cộng vào kho hay chưa.
 * ============================================================
 * HAI TRẠNG THÁI LÀ ĐỦ, và ranh giới giữa chúng là một việc THẬT đã xảy
 * ra: kho đã được cộng thêm hay chưa.
 *
 * `Draft` sửa được thoải mái vì nó chưa động tới kho. `Posted` thì
 * KHÔNG sửa — con số tồn kho đã đổi theo nó, và sửa một chứng từ sau khi
 * nó đã tác động là làm sổ sách không còn khớp với thực tế.
 *
 * Nhập nhầm thì lập một phiếu điều chỉnh (số lượng âm), y như cách kế
 * toán làm với hoá đơn đã phát hành.
 */
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
