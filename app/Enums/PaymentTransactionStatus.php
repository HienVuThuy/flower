<?php

namespace App\Enums;

/**
 * Một lượt thanh toán đang ở đâu.
 *
 * Khác PaymentStatus: kia nói về TIỀN CỦA ĐƠN, đây nói về MỘT LẦN THỬ.
 * Đơn có thể còn "Chưa thanh toán" trong khi đã có ba lượt `failed`.
 */
enum PaymentTransactionStatus: string
{
    case Pending = 'pending';
    case Initiated = 'initiated';
    case Paid = 'paid';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ thanh toán',
            self::Initiated => 'Đã chuyển sang cổng',
            self::Paid => 'Đã thanh toán',
            self::Failed => 'Thất bại',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Initiated => 'warning',
            self::Paid => 'success',
            self::Failed => 'danger',
        };
    }
}
