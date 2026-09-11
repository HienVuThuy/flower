<?php

namespace App\Enums;

/**
 * Tiền hoàn đi đường nào.
 * ============================================================
 * HOÀN QUA MOMO là cách DUY NHẤT hệ thống tự chuyển tiền: gọi API hoàn
 * tiền của MoMo trên chính giao dịch khách đã trả, và tiền về đúng ví
 * đã trả. Chỉ dùng được cho đơn có giao dịch MoMo thành công.
 *
 * CHUYỂN KHOẢN và TIỀN MẶT thì người của cửa hàng làm ngoài hệ thống;
 * phần mềm chỉ GHI LẠI việc đã làm. Chuyển khoản bắt buộc có mã giao
 * dịch ngân hàng: không có nó thì khi khách báo "chưa nhận được" không
 * còn gì để tra.
 */
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

    /** Phần mềm tự chuyển tiền, hay người thật đã làm rồi ghi lại. */
    public function tuDong(): bool
    {
        return $this === self::Momo;
    }

    public function batBuocMaGiaoDich(): bool
    {
        return $this === self::BankTransfer;
    }
}
