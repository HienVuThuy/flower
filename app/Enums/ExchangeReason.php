<?php

namespace App\Enums;

/**
 * Vì sao đổi — và ai chịu phí ship chiều đổi.
 * ============================================================
 * LÝ DO QUYẾT ĐỊNH TIỀN, nên nó không phải một ô ghi chú tự do.
 *
 * "Giao sai" và "khách đổi ý" là hai câu chuyện khác hẳn nhau về việc ai
 * có lỗi, nên cũng khác nhau về việc ai trả phí vận chuyển. Để người lập
 * phiếu tự gõ lý do thì phí ship thành tuỳ hứng, và hai khách cùng cảnh
 * ngộ nhận hai câu trả lời khác nhau.
 */
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

    /**
     * Lỗi thuộc về cửa hàng thì cửa hàng chịu phí ship chiều đổi.
     *
     * Bắt khách trả phí cho lỗi của mình là cách nhanh nhất để mất khách
     * — và họ sẽ kể lại chuyện đó cho người khác.
     */
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
