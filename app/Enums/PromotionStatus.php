<?php

namespace App\Enums;

/** Trạng thái vòng đời của chương trình khuyến mại. */
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
