<?php

namespace App\Enums;

use Illuminate\Support\Facades\Log;

/** Cách trả tiền trên trang MoMo. */
enum MomoFlow: string
{
    case Wallet = 'vi';
    case Card = 'the';

    public function requestType(): string
    {
        return match ($this) {
            self::Wallet => 'captureWallet',
            self::Card => 'payWithCC',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Wallet => 'Quét mã QR bằng ứng dụng MoMo',
            self::Card => 'Thẻ quốc tế (Visa, Mastercard, JCB)',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Wallet => 'MoMo hiện mã QR để quét bằng điện thoại. Mở trên điện thoại thì vào thẳng ứng dụng.',
            self::Card => 'Nhập số thẻ ngay trên trang MoMo. Không cần cài ứng dụng.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Wallet => 'telephone',
            self::Card => 'bag',
        };
    }

    public static function macDinh(): self
    {
        $raw = (string) config('payment.gateways.momo.request_type', 'payWithCC');

        foreach (self::cases() as $flow) {
            if ($flow->requestType() === $raw) {
                return $flow;
            }
        }

        Log::warning('MOMO_REQUEST_TYPE trỏ tới một dịch vụ chưa hỗ trợ, lùi về thẻ quốc tế.', [
            'cau_hinh' => $raw,
            'ho_tro' => array_map(fn (self $f) => $f->requestType(), self::cases()),
        ]);

        return self::Card;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
