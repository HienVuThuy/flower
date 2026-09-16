<?php

namespace App\Enums;

/**
 * Hoá đơn đã đi tới đâu.
 * ⚠️ HỆ THỐNG HIỆN CHỈ TẠO RA `Draft`.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Chưa phát hành',
            self::Issued => 'Đã phát hành',
            self::Cancelled => 'Đã huỷ',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Issued => 'success',
            self::Cancelled => 'secondary',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Draft => 'Cửa hàng đã ghi nhận yêu cầu và thông tin xuất hoá đơn. '
                . 'Hoá đơn điện tử sẽ được phát hành và gửi tới email bạn cung cấp.',
            self::Issued => 'Hoá đơn điện tử đã được phát hành.',
            self::Cancelled => 'Yêu cầu xuất hoá đơn đã được huỷ.',
        };
    }
}
