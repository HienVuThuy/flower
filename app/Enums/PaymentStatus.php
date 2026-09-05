<?php

namespace App\Enums;

/**
 * Tình trạng thanh toán — TÁCH RIÊNG khỏi trạng thái đơn hàng.
 *
 * Hai thứ này độc lập: một đơn COD có thể "Đang giao" mà vẫn "Chưa
 * thanh toán"; một đơn chuyển khoản có thể "Đã thanh toán" từ lúc còn
 * "Chờ xác nhận". Gộp chung vào một cột là sai mô hình.
 */
/**
 * Tiền của đơn hàng đang ở đâu.
 * ============================================================
 * TÁCH HẲN khỏi OrderStatus, và đó là chủ ý: một đơn COD "Đang giao"
 * vẫn chưa trả tiền, còn một đơn chuyển khoản "Chờ xác nhận" thì đã trả
 * rồi. Gộp hai trục vào một cột là mất khả năng nói "đã huỷ nhưng khách
 * đã trả tiền" — đúng lúc cửa hàng cần biết mình đang nợ ai.
 *
 * BA TRẠNG THÁI, chuyển theo một chiều:
 *
 *     chưa trả  ──►  đã trả  ──►  đã hoàn tiền
 *         ▲             │
 *         └─────────────┘  (chỉ để sửa cú bấm nhầm)
 *
 * KHÔNG có đường từ "đã hoàn tiền" đi tiếp. Tiền đã trả lại khách thì
 * đơn đó khép lại; muốn bán tiếp là một đơn mới.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Chưa thanh toán',
            self::Paid => 'Đã thanh toán',
            self::Refunded => 'Đã hoàn tiền',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Paid => 'success',
            self::Refunded => 'secondary',
        };
    }

    /**
     * Từ trạng thái này chuyển sang trạng thái kia được không.
     *
     * KHAI Ở ĐÂY, không rải if/else trong controller. Cùng lý do với
     * OrderStatus::canTransitionTo(): mỗi nơi tự nhớ luật là mỗi nơi
     * nhớ thiếu một nhánh.
     *
     * @return list<self>
     */
    public function nextStates(): array
    {
        return match ($this) {
            // Chưa trả -> đã trả. Không nhảy thẳng sang hoàn tiền được:
            // chưa nhận thì không có gì để hoàn.
            self::Unpaid => [self::Paid],

            /*
             * Đã trả -> hoàn tiền (huỷ đơn sau khi khách đã chuyển), hoặc
             * quay về chưa trả (admin bấm nhầm đơn).
             *
             * Đường quay lui là CẦN THIẾT chứ không phải tiện tay: đánh
             * dấu nhầm một đơn là đã trả thì cửa hàng giao hàng mà không
             * bao giờ đòi tiền, và không có cách nào sửa.
             */
            self::Paid => [self::Refunded, self::Unpaid],

            // Đã hoàn tiền là điểm cuối.
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->nextStates(), strict: true);
    }
}
