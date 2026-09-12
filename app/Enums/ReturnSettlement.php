<?php

namespace App\Enums;

/**
 * Trả hàng cho vựa rồi thì tiền được xử lý thế nào.
 * ============================================================
 * ĐÂY LÀ CHỖ DỄ LÀM SAI NHẤT CỦA CẢ TÍNH NĂNG, vì nó quyết định giá vốn
 * có giảm hay không:
 *
 *   - Hoàn tiền / trừ công nợ: tiền quay về cửa hàng -> giá vốn GIẢM.
 *   - Đổi hàng khác:           vẫn nhận đủ hàng      -> giá vốn GIỮ NGUYÊN.
 *   - Không được gì:           cửa hàng chịu         -> giá vốn GIỮ NGUYÊN.
 *
 * Trừ giá vốn trong hai trường hợp sau là tự tặng cho cửa hàng một khoản
 * lãi không có thật — và nó rất dễ xảy ra, vì "đã trả hàng rồi thì trừ
 * tiền đi" nghe rất thuận tai.
 */
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

    /** Tiền có quay về cửa hàng không — quyết định giá vốn có giảm không. */
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
