<?php

namespace App\Enums;

/** Vòng đời một đơn hàng. */
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

    public function vizColor(): string
    {
        return match ($this) {
            self::Pending => 'var(--viz-step-1)',
            self::Confirmed => 'var(--viz-step-2)',
            self::Preparing => 'var(--viz-step-3)',
            self::Shipping => 'var(--viz-step-4)',
            self::Completed => 'var(--viz-step-5)',
            self::Cancelled => 'var(--viz-huy)',
        };
    }

    public function nextStates(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Shipping, self::Cancelled],
            self::Shipping => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->nextStates(), strict: true);
    }

    public function notifiesCustomer(): bool
    {
        return in_array($this, [
            self::Confirmed,
            self::Shipping,
            self::Completed,
            self::Cancelled,
        ], strict: true);
    }

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

    public function customerMessage(): string
    {
        return match ($this) {
            self::Confirmed => 'Chúng tôi đã kiểm tra và nhận đơn của bạn. '
                . 'Cửa hàng sẽ chuẩn bị hoa và liên hệ trước khi giao.',

            self::Shipping => 'Đơn của bạn đã rời cửa hàng. '
                . 'Vui lòng để ý điện thoại để người giao hàng liên hệ được.',

            self::Completed => 'Cảm ơn bạn đã tin tưởng ' . \App\Services\Shop\StoreProfile::name() . '. '
                . 'Nếu hài lòng, hãy dành ít phút đánh giá sản phẩm để người mua sau tham khảo.',

            self::Cancelled => 'Đơn hàng này đã được huỷ và cửa hàng sẽ không giao nữa. '
                . 'Nếu bạn không yêu cầu huỷ, vui lòng liên hệ ngay với cửa hàng.',

            default => 'Trạng thái đơn hàng của bạn vừa được cập nhật.',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], strict: true);
    }

    public function holdsStock(): bool
    {
        return ! $this->isFinal();
    }
}
