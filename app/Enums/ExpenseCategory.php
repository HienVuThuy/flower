<?php

namespace App\Enums;

/** Loại chi phí vận hành. */
enum ExpenseCategory: string
{
    case Luong = 'luong';
    case MatBang = 'mat_bang';
    case DienNuoc = 'dien_nuoc';
    case MayChu = 'may_chu';
    case ThietBi = 'thiet_bi';
    case VatTu = 'vat_tu';
    case QuangCao = 'quang_cao';
    case VanChuyen = 'van_chuyen';
    case Khac = 'khac';

    public function label(): string
    {
        return match ($this) {
            self::Luong => 'Lương nhân viên',
            self::MatBang => 'Thuê mặt bằng',
            self::DienNuoc => 'Điện, nước, internet',
            self::MayChu => 'Server, tên miền, phần mềm',
            self::ThietBi => 'Thiết bị, dụng cụ',
            self::VatTu => 'Vật tư đóng gói',
            self::QuangCao => 'Quảng cáo',
            self::VanChuyen => 'Vận chuyển ngoài GHN',
            self::Khac => 'Khác',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::VatTu => 'Túi, giấy gói, ruy băng, xốp… KHÔNG ghi hàng để bán — đã ở phiếu nhập và lô hoa.',
            self::VanChuyen => 'Xe ôm, grab tự thuê. Cước GHN đã có trong đơn — không ghi lại.',
            self::ThietBi => 'Tủ lạnh giữ hoa, máy in, kéo… mua một lần.',
            default => '',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
