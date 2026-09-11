<?php

namespace App\Enums;

/**
 * Vì sao hoàn tiền.
 * ============================================================
 * KHÔNG PHẢI Ô CHỮ TỰ DO. Lý do là thứ cửa hàng cần ĐẾM: tháng này hoàn
 * bao nhiêu vì hoa héo, bao nhiêu vì giao nhầm. Gõ tay thì "hoa héo",
 * "héo", "cây bị úng" là ba dòng khác nhau và không cộng lại được. Chi
 * tiết từng vụ nằm ở ô ghi chú đi kèm.
 *
 * Mỗi lý do khai rõ đi với đơn ở trạng thái nào — hoàn "vì hàng hỏng"
 * cho một đơn chưa giao là một câu vô nghĩa.
 */
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

    /**
     * Lý do hợp với trạng thái đơn.
     *
     * @return list<self>
     */
    public static function choTrangThai(OrderStatus $trangThai): array
    {
        return match ($trangThai) {
            OrderStatus::Cancelled => [self::OrderCancelled, self::Other],
            OrderStatus::Completed => [self::Damaged, self::WrongItem, self::Returned, self::Other],
            default => [],
        };
    }
}
