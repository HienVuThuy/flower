<?php

namespace App\Enums;

/**
 * Vì sao điểm của khách thay đổi.
 *
 * Mỗi dòng trong sổ điểm mang đúng một lý do. Không có lý do "khác": một
 * lần cộng điểm không nói được vì sao là một lần cộng điểm không kiểm lại
 * được.
 */
enum PointReason: string
{
    /** Bài Góc cây được duyệt. */
    case DangBai = 'dang_bai';

    /** Ghé cửa hàng nhiều ngày liền. */
    case ChuoiNgay = 'chuoi_ngay';

    /** Đổi điểm lấy voucher — dòng ÂM. */
    case DoiVoucher = 'doi_voucher';

    public function label(): string
    {
        return match ($this) {
            self::DangBai => 'Bài Góc cây được duyệt',
            self::ChuoiNgay => 'Chuỗi ngày ghé thăm',
            self::DoiVoucher => 'Đổi voucher',
        };
    }
}
