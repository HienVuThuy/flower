<?php

namespace App\Enums;

/**
 * Vòng đời một đơn hàng.
 *
 * Chỉ đi tiến, không nhảy lung tung: mỗi trạng thái khai rõ những
 * trạng thái kế tiếp hợp lệ ở canTransitionTo(). Nhờ vậy admin không
 * thể bấm nhầm từ "Đã giao" về "Chờ xác nhận".
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Shipping = 'shipping';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::Preparing => 'Đang chuẩn bị',
            self::Shipping => 'Đang giao',
            self::Completed => 'Đã giao',
            self::Cancelled => 'Đã huỷ',
        };
    }

    /** Màu badge — dùng chung cho cả admin lẫn trang khách. */
    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed, self::Preparing => 'info',
            self::Shipping => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** @return array<int, self> */
    public function nextStates(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Shipping, self::Cancelled],
            self::Shipping => [self::Completed, self::Cancelled],
            // Hai trạng thái kết thúc: không đi tiếp được nữa.
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->nextStates(), strict: true);
    }

    /**
     * Trạng thái này có đáng gửi email cho khách không.
     *
     * KHÔNG gửi đủ sáu trạng thái. "Chờ xác nhận" đã có email lúc đặt
     * hàng rồi; "Đang chuẩn bị" là việc nội bộ của cửa hàng, khách không
     * làm gì với thông tin đó. Gửi mọi bước là biến hộp thư của khách
     * thành nơi nhận thông báo rác, và rác thì người ta bỏ qua — kể cả
     * cái quan trọng.
     *
     * Bốn mốc còn lại đều có việc để khách làm hoặc cần biết:
     *   Đã xác nhận  — yên tâm là cửa hàng đã nhận đơn
     *   Đang giao    — cần có mặt để nhận hàng
     *   Đã giao      — đối chiếu, và là lúc mời đánh giá
     *   Đã huỷ       — biết ngay, nhất là khi không phải họ huỷ
     */
    public function notifiesCustomer(): bool
    {
        return in_array($this, [
            self::Confirmed,
            self::Shipping,
            self::Completed,
            self::Cancelled,
        ], strict: true);
    }

    /** Câu tiêu đề trong email báo đổi trạng thái. */
    public function customerHeadline(): string
    {
        return match ($this) {
            self::Confirmed => 'Cửa hàng đã xác nhận đơn của bạn',
            self::Shipping => 'Đơn hàng đang trên đường tới bạn',
            self::Completed => 'Đơn hàng đã giao thành công',
            self::Cancelled => 'Đơn hàng đã được huỷ',
            default => 'Đơn hàng có cập nhật mới',
        };
    }

    /** Phần giải thích, cho khách biết tiếp theo là gì. */
    public function customerMessage(): string
    {
        return match ($this) {
            self::Confirmed => 'Chúng tôi đã kiểm tra và nhận đơn của bạn. '
                . 'Cửa hàng sẽ chuẩn bị hoa và liên hệ trước khi giao.',

            self::Shipping => 'Đơn của bạn đã rời cửa hàng. '
                . 'Vui lòng để ý điện thoại để người giao hàng liên hệ được.',

            self::Completed => 'Cảm ơn bạn đã tin tưởng Flower & Plant. '
                . 'Nếu hài lòng, hãy dành ít phút đánh giá sản phẩm để người mua sau tham khảo.',

            self::Cancelled => 'Đơn hàng này đã được huỷ và cửa hàng sẽ không giao nữa. '
                . 'Nếu bạn không yêu cầu huỷ, vui lòng liên hệ ngay với cửa hàng.',

            default => 'Trạng thái đơn hàng của bạn vừa được cập nhật.',
        };
    }

    /** Đơn đã chốt xong hay chưa — dùng để khoá sửa và để thống kê. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], strict: true);
    }

    /**
     * Đơn còn "sống": đã trừ kho và vẫn đang chờ xử lý.
     * Huỷ đơn ở các trạng thái này thì phải HOÀN kho.
     */
    public function holdsStock(): bool
    {
        return ! $this->isFinal();
    }
}
