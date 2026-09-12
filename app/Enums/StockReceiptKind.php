<?php

namespace App\Enums;

/**
 * Phiếu nhập này là hàng mới về, hay là khai tồn có sẵn.
 * ============================================================
 * HAI LOẠI KHÁC NHAU Ở MỘT ĐIỂM SỐNG CÒN: phiếu tồn đầu kỳ KHÔNG cộng
 * vào kho. Hàng đã nằm trên kệ rồi; cộng thêm là nhân đôi tồn của cả
 * cửa hàng, và sai lệch chỉ lộ ra ở lần kiểm kê đầu tiên.
 *
 * Và khác ở độ tin: giá vốn hàng mới về có chứng từ; giá vốn tồn đầu kỳ
 * là con số người ta nhớ lại. Trộn hai thứ rồi gọi chung là "lãi gộp" là
 * làm mất đúng cái đáng tin của báo cáo.
 */
enum StockReceiptKind: string
{
    case NhapMoi = 'nhap_moi';
    case TonDauKy = 'ton_dau_ky';

    public function label(): string
    {
        return match ($this) {
            self::NhapMoi => 'Nhập hàng mới',
            self::TonDauKy => 'Khai tồn đầu kỳ',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::NhapMoi => 'Hàng vừa về kho. Ghi sổ thì cộng vào tồn.',
            self::TonDauKy => 'Khai số đã có sẵn trên kệ và giá vốn ước tính. KHÔNG cộng vào tồn.',
        };
    }

    /** Ghi sổ có cộng vào tồn kho không. */
    public function congVaoKho(): bool
    {
        return $this === self::NhapMoi;
    }

    /** Giá vốn của loại phiếu này là ước tính hay có chứng từ. */
    public function giaVonUocTinh(): bool
    {
        return $this === self::TonDauKy;
    }
}
