<?php

namespace App\Enums;

/** Tình trạng một kế hoạch trả góp. */
enum InstallmentStatus: string
{
    case DangTra = 'dang_tra';
    case HoanTat = 'hoan_tat';
    case VoNo = 'vo_no';
    case DaHuy = 'da_huy';

    public function label(): string
    {
        return match ($this) {
            self::DangTra => 'Đang trả',
            self::HoanTat => 'Đã trả đủ',
            self::VoNo => 'Quá hạn — đã huỷ',
            self::DaHuy => 'Đã huỷ',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::DangTra => 'warning',
            self::HoanTat => 'success',
            self::VoNo => 'danger',
            self::DaHuy => 'secondary',
        };
    }
}
