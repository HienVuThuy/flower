<?php

namespace App\Enums;

/** Vì sao phải trả hàng lại cho nhà cung cấp. */
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

    public function loiNhaCungCap(): bool
    {
        return $this !== self::Khac;
    }
}
