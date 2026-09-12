<?php

namespace App\Enums;

/**
 * Vì sao phải trả hàng lại cho nhà cung cấp.
 * ============================================================
 * KHÔNG PHẢI Ô GHI CHÚ TỰ DO, vì đây chính là số liệu để so sánh nhà
 * cung cấp ở phần phân tích thu mua: cùng một giá, nơi hay giao hàng dập
 * và nơi hiếm khi bị trả không phải hai lựa chọn ngang nhau.
 *
 * Để người lập phiếu tự gõ thì mỗi lần một cách diễn đạt, và không gom
 * nhóm được — đúng lỗi mà bảng nhà cung cấp sinh ra để dẹp.
 */
enum ReturnReason: string
{
    case HangHong = 'hang_hong';
    case GiaoSai = 'giao_sai';
    case KhongDatChatLuong = 'khong_dat';
    case ThuaHang = 'thua_hang';
    case Khac = 'khac';

    public function label(): string
    {
        return match ($this) {
            self::HangHong => 'Hàng hỏng, dập, héo',
            self::GiaoSai => 'Giao sai loại hoặc sai cỡ',
            self::KhongDatChatLuong => 'Không đạt chất lượng như thoả thuận',
            self::ThuaHang => 'Giao thừa so với đặt',
            self::Khac => 'Lý do khác',
        };
    }

    /** Lỗi thuộc về nhà cung cấp — dùng khi chấm điểm nguồn hàng. */
    public function loiNhaCungCap(): bool
    {
        return $this !== self::Khac;
    }
}
