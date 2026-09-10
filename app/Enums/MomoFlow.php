<?php

namespace App\Enums;

use Illuminate\Support\Facades\Log;

/**
 * Cách trả tiền trên trang MoMo.
 * ============================================================
 * KHÔNG PHẢI MỘT HÌNH THỨC THANH TOÁN MỚI — vẫn là MoMo, vẫn cùng một
 * cổng, cùng một cách đối soát. Nó chỉ quyết định trang MoMo mở ra cái
 * gì, và điều đó phụ thuộc khách đang cầm cái gì:
 *
 *     điện thoại có app MoMo  ->  mã QR / mở thẳng app
 *     máy tính, có thẻ         ->  ô nhập thẻ quốc tế
 *
 * Vì thế nó KHÔNG được là một case của PaymentMethod: đơn hàng ghi
 * `payment_method = momo` trong cả hai trường hợp, và kế toán không cần
 * phân biệt.
 *
 * ============================================================
 * CHỌN NHẦM THÌ KHÔNG CÓ LỖI NÀO BÁO.
 *
 * MoMo vẫn trả `resultCode: 0` và vẫn cấp `payUrl` — chỉ là trang mở ra
 * không có ô nhập nào khớp với thứ khách đang cầm. Đó là lý do phải để
 * khách tự chọn thay vì đoán hộ.
 */
enum MomoFlow: string
{
    case Wallet = 'vi';
    case Card = 'the';

    /** Giá trị `requestType` mà MoMo hiểu. */
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

    /**
     * Cách mặc định khi không ai chọn.
     *
     * Đọc từ `.env` để đổi được mà không sửa mã: môi trường thử của MoMo
     * có lúc từ chối dịch vụ này, nhận dịch vụ kia (xem QĐ-190).
     */
    public static function macDinh(): self
    {
        $raw = (string) config('payment.gateways.momo.request_type', 'payWithCC');

        foreach (self::cases() as $flow) {
            if ($flow->requestType() === $raw) {
                return $flow;
            }
        }

        /*
         * CẤU HÌNH TRỎ TỚI MỘT DỊCH VỤ KHÔNG CÓ Ở ĐÂY — PHẢI KÊU.
         *
         * `payWithATM` chẳng hạn: nó là một dịch vụ có thật của MoMo,
         * nhưng enum này chỉ có hai case. Lặng lẽ rơi về thẻ quốc tế thì
         * người cấu hình đặt ATM, thấy trang thẻ mở ra, và không hiểu vì
         * sao — MoMo cũng không báo lỗi gì vì yêu cầu vẫn hợp lệ.
         *
         * Ghi log để còn tìm ra được, và vẫn trả về một giá trị dùng
         * được: một cấu hình sai không đáng để cả luồng thanh toán chết.
         */
        Log::warning('MOMO_REQUEST_TYPE trỏ tới một dịch vụ chưa hỗ trợ, lùi về thẻ quốc tế.', [
            'cau_hinh' => $raw,
            'ho_tro' => array_map(fn (self $f) => $f->requestType(), self::cases()),
        ]);

        return self::Card;
    }

    /** Giá trị hợp lệ cho `Rule::in()`. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
