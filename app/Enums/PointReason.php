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

    /** Đơn hàng đã giao. */
    case MuaHang = 'mua_hang';

    /** Đơn đã được cộng điểm rồi được hoàn tiền — dòng ÂM. */
    case HoanTien = 'hoan_tien';

    /** Viết đánh giá cho sản phẩm đã mua. */
    case DanhGia = 'danh_gia';

    public function label(): string
    {
        return match ($this) {
            self::DangBai => 'Bài Góc cây được duyệt',
            self::ChuoiNgay => 'Chuỗi ngày ghé thăm',
            self::DoiVoucher => 'Đổi voucher',
            self::MuaHang => 'Mua hàng',
            self::HoanTien => 'Trừ điểm do hoàn tiền',
            self::DanhGia => 'Viết đánh giá',
        };
    }
}
