<?php

namespace App\Enums;

/** Trả hàng cho vựa rồi thì tiền được xử lý thế nào. */
enum ReturnSettlement: string
{
    case HoanTien = 'hoan_tien';
    case TruCongNo = 'tru_cong_no';
    case DoiHang = 'doi_hang';
    case KhongDuocGi = 'khong_duoc_gi';

    public function label(): string
    {
        return match ($this) {
            self::HoanTien => 'Vựa hoàn tiền',
            self::TruCongNo => 'Trừ vào lần lấy sau',
            self::DoiHang => 'Vựa đổi hàng khác',
            self::KhongDuocGi => 'Không được gì, cửa hàng chịu',
        };
    }

    public function tienQuayVe(): bool
    {
        return $this === self::HoanTien || $this === self::TruCongNo;
    }

    public function hint(): string
    {
        return $this->tienQuayVe()
            ? 'Giá vốn giảm đúng phần đã trả lại.'
            : 'Giá vốn giữ nguyên: hoặc đã nhận đủ hàng, hoặc cửa hàng chịu mất.';
    }
}
