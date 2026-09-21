<?php

namespace App\Enums;

/** Một yêu cầu thêm trong lúc chăm: báo giá → khách đồng ý → làm. */
enum BoardingExtraStatus: string
{
    case ChoBaoGia = 'cho_bao_gia';
    case ChoKhach = 'cho_khach';
    case DaDongY = 'da_dong_y';
    case DaLam = 'da_lam';
    case KhachTuChoi = 'khach_tu_choi';
    case CuaHangTuChoi = 'cua_hang_tu_choi';

    public function label(): string
    {
        return match ($this) {
            self::ChoBaoGia => 'Chờ cửa hàng báo giá',
            self::ChoKhach => 'Chờ khách đồng ý',
            self::DaDongY => 'Khách đã đồng ý, chờ làm',
            self::DaLam => 'Đã làm',
            self::KhachTuChoi => 'Khách không làm',
            self::CuaHangTuChoi => 'Cửa hàng không nhận',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::ChoBaoGia, self::ChoKhach => 'warning',
            self::DaDongY => 'info',
            self::DaLam => 'success',
            self::KhachTuChoi, self::CuaHangTuChoi => 'secondary',
        };
    }

    /** Chỉ việc khách đã đồng ý (hoặc đã làm) mới cộng vào tiền phiếu. */
    public function tinhTien(): bool
    {
        return in_array($this, [self::DaDongY, self::DaLam], true);
    }
}
