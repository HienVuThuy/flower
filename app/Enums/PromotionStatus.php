<?php

namespace App\Enums;

/**
 * Trạng thái vòng đời của chương trình khuyến mại.
 *
 * Lưu ý: status thể hiện Ý ĐỊNH của admin, còn việc chương trình có
 * thực sự đang áp dụng hay không còn phụ thuộc khoảng thời gian
 * (starts_at/ends_at). Xem Promotion::scopeActiveNow().
 *
 * Nhờ tách đôi như vậy, admin có thể tạm dừng (Paused) một chương
 * trình ngay giữa thời gian hiệu lực mà không cần sửa ngày.
 */
enum PromotionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Scheduled => 'Đã lên lịch',
            self::Active => 'Đang diễn ra',
            self::Paused => 'Tạm dừng',
            self::Ended => 'Đã kết thúc',
        };
    }

    public function chipClass(): string
    {
        return match ($this) {
            self::Draft => 'status-chip--neutral',
            self::Scheduled => 'status-chip--warning',
            self::Active => 'status-chip--success',
            self::Paused => 'status-chip--warning',
            self::Ended => 'status-chip--neutral',
        };
    }
}
