<?php

namespace App\Enums;

/** Báo cáo Góc cây: chờ cửa hàng xem → ẩn nội dung, hoặc kết luận không vi phạm. */
enum CommunityReportStatus: string
{
    case ChoXuLy = 'cho_xu_ly';
    case DaAn = 'da_an';
    case BoQua = 'bo_qua';

    public function label(): string
    {
        return match ($this) {
            self::ChoXuLy => 'Chờ xử lý',
            self::DaAn => 'Đã ẩn nội dung',
            self::BoQua => 'Không vi phạm',
        };
    }
}
