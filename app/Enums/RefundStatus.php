<?php

namespace App\Enums;

/** Tiền của một lần hoàn đã tới tay khách chưa. */
enum RefundStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chưa rõ kết quả',
            self::Completed => 'Đã hoàn',
            self::Failed => 'Không thành công',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    public function giuChoTien(): bool
    {
        return $this !== self::Failed;
    }
}
