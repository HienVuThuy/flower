<?php

namespace App\Enums;

/** Tình trạng thanh toán — TÁCH RIÊNG khỏi trạng thái đơn hàng. */
/** Tiền của đơn hàng đang ở đâu. */
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

    public function nextStates(): array
    {
        return match ($this) {
            self::Unpaid => [self::Paid],

            self::Paid => [self::Refunded, self::Unpaid],

            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->nextStates(), strict: true);
    }
}
