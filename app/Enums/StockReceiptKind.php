<?php

namespace App\Enums;

/** Phiếu nhập này là hàng mới về, hay là khai tồn có sẵn. */
enum StockReceiptKind: string
{
    case NhapMoi = 'nhap_moi';
    case TonDauKy = 'ton_dau_ky';
    case TraNcc = 'tra_ncc';

    public function label(): string
    {
        return match ($this) {
            self::NhapMoi => 'Nhập hàng mới',
            self::TonDauKy => 'Khai tồn đầu kỳ',
            self::TraNcc => 'Trả hàng cho nhà cung cấp',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::NhapMoi => 'Hàng vừa về kho. Ghi sổ thì cộng vào tồn.',
            self::TonDauKy => 'Khai số đã có sẵn trên kệ và giá vốn ước tính. KHÔNG cộng vào tồn.',
            self::TraNcc => 'Hàng trả lại vựa. Ghi sổ thì TRỪ khỏi tồn và khỏi nền giá vốn.',
        };
    }

    public function congVaoKho(): bool
    {
        return $this === self::NhapMoi || $this === self::TraNcc;
    }

    public function giaVonUocTinh(): bool
    {
        return $this === self::TonDauKy;
    }
}
