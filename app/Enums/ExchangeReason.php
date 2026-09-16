<?php

namespace App\Enums;

/** Vì sao đổi — và ai chịu phí ship chiều đổi. */
enum ExchangeReason: string
{
    case GiaoSai = 'giao_sai';
    case HangHong = 'hang_hong';
    case KhachDoiY = 'khach_doi_y';

    public function label(): string
    {
        return match ($this) {
            self::GiaoSai => 'Cửa hàng giao sai hàng',
            self::HangHong => 'Hàng hỏng khi tới nơi',
            self::KhachDoiY => 'Khách đổi ý (đổi loại, đổi cỡ)',
        };
    }

    public function cuaHangChiuPhiShip(): bool
    {
        return $this !== self::KhachDoiY;
    }

    public function hint(): string
    {
        return $this->cuaHangChiuPhiShip()
            ? 'Cửa hàng chịu phí ship chiều đổi.'
            : 'Khách trả phí ship chiều đổi.';
    }
}
