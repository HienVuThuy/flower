<?php

namespace App\Enums;

/** Phiếu đổi hàng đang ở bước nào. */
enum ExchangeStatus: string
{
    case ChoNhan = 'cho_nhan';
    case DaNhan = 'da_nhan';
    case HoanTat = 'hoan_tat';
    case Huy = 'huy';

    public function label(): string
    {
        return match ($this) {
            self::ChoNhan => 'Chờ nhận hàng đổi',
            self::DaNhan => 'Đã nhận hàng trả',
            self::HoanTat => 'Hoàn tất',
            self::Huy => 'Đã huỷ',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::ChoNhan => 'Đã giữ hàng mới trong kho, đang chờ khách gửi hàng cũ về.',
            self::DaNhan => 'Hàng cũ đã về kho. Còn gửi hàng mới và thu nốt chênh lệch.',
            self::HoanTat => 'Hàng mới đã gửi, tiền đã xong.',
            self::Huy => 'Đã huỷ, hàng giữ trong kho đã được trả lại.',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::ChoNhan => 'warning',
            self::DaNhan => 'info',
            self::HoanTat => 'success',
            self::Huy => 'secondary',
        };
    }

    public function daXong(): bool
    {
        return $this === self::HoanTat || $this === self::Huy;
    }
}
