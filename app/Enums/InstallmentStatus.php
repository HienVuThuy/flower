<?php

namespace App\Enums;

/**
 * Tình trạng một kế hoạch trả góp.
 *
 *     đang trả ──► đã trả đủ          (trả hết các kỳ: đơn "đã thanh toán", được giao)
 *         ├──────► vỡ                  (quá hạn một kỳ vượt số ngày ân hạn: huỷ đơn, hoàn tiền đã trả)
 *         └──────► đã huỷ              (đơn bị huỷ vì lý do khác: khách hoặc cửa hàng huỷ)
 *
 * Ba trạng thái sau là điểm cuối. Chỉ "vỡ" làm giảm điểm tín dụng.
 */
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
