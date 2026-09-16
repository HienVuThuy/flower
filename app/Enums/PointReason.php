<?php

namespace App\Enums;

/** Vì sao điểm của khách thay đổi. */
enum PointReason: string
{
    case DangBai = 'dang_bai';

    case ChuoiNgay = 'chuoi_ngay';

    case DoiVoucher = 'doi_voucher';

    case MuaHang = 'mua_hang';

    case HoanTien = 'hoan_tien';

    case DanhGia = 'danh_gia';

    case DuocThich = 'duoc_thich';

    case DungDiem = 'dung_diem';

    case HoanDiem = 'hoan_diem';

    public function label(): string
    {
        return match ($this) {
            self::DuocThich => 'Bài Góc cây được thích',
            self::DungDiem => 'Dùng điểm cho đơn hàng',
            self::HoanDiem => 'Trả lại điểm đã dùng',
            self::DangBai => 'Bài Góc cây được duyệt',
            self::ChuoiNgay => 'Chuỗi ngày ghé thăm',
            self::DoiVoucher => 'Đổi voucher',
            self::MuaHang => 'Mua hàng',
            self::HoanTien => 'Trừ điểm do hoàn tiền',
            self::DanhGia => 'Viết đánh giá',
        };
    }
}
