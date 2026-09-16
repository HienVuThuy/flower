<?php

namespace App\Enums;

use App\Services\Installment\InstallmentSettings;

/** Hình thức thanh toán. */
enum PaymentMethod: string
{
    case Cod = 'cod';
    case Momo = 'momo';
    case TraGop = 'tra_gop';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Thanh toán khi nhận hàng (COD)',
            self::Momo => 'Ví MoMo',
            self::TraGop => 'Trả góp trước khi giao',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Cod => 'Trả tiền mặt cho nhân viên giao hàng khi nhận hoa.',
            self::Momo => 'Chuyển sang trang MoMo để trả bằng ví hoặc thẻ ATM nội địa.',
            self::TraGop => 'Cửa hàng giữ hàng cho bạn. Trả trước một phần, trả nốt theo kỳ qua MoMo hoặc tại cửa hàng; giao hàng khi đã trả đủ.',
        };
    }

    public function isOnline(): bool
    {
        return $this->gatewayKey() !== null;
    }

    public function gatewayKey(): ?string
    {
        return match ($this) {
            self::Cod, self::TraGop => null,
            self::Momo => 'momo',
        };
    }

    public function isConfigured(): bool
    {
        if ($this === self::TraGop) {
            return InstallmentSettings::bat();
        }

        $key = $this->gatewayKey();

        if ($key === null) {
            return true;
        }

        return (bool) config("payment.gateways.{$key}.enabled", false);
    }

    public static function available(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $m) => $m->isConfigured(),
        ));
    }

    public static function values(): array
    {
        return array_column(self::available(), 'value');
    }
}
