<?php

namespace App\Enums;

/** Vì sao hoàn tiền. */
enum RefundReason: string
{
    case OrderCancelled = 'don_huy';
    case Damaged = 'hang_hong';
    case WrongItem = 'giao_sai';
    case Returned = 'tra_hang';
    case Other = 'khac';

    public function label(): string
    {
        return match ($this) {
            self::OrderCancelled => 'Đơn đã huỷ',
            self::Damaged => 'Hàng hỏng, héo, dập khi tới tay khách',
            self::WrongItem => 'Giao sai hoặc thiếu hàng',
            self::Returned => 'Khách trả hàng',
            self::Other => 'Lý do khác',
        };
    }

    public static function choTrangThai(OrderStatus $trangThai): array
    {
        return match ($trangThai) {
            OrderStatus::Cancelled => [self::OrderCancelled, self::Other],
            OrderStatus::Completed => [self::Damaged, self::WrongItem, self::Returned, self::Other],
            default => [],
        };
    }
}
