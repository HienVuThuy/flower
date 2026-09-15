<?php

namespace App\Enums;

use App\Services\Installment\InstallmentSettings;

/**
 * Hình thức thanh toán.
 * ============================================================
 * COD, MoMo và Trả góp. Enum này nói đúng những gì hệ thống LÀM ĐƯỢC,
 * không bày ra lựa chọn bấm vào không chạy: MoMo chỉ hiện khi `.env` có
 * đủ khoá, trả góp chỉ hiện khi cửa hàng bật — xem isConfigured().
 *
 * TRẢ GÓP không phải một cổng: tiền từng kỳ vẫn đi qua MoMo hoặc được thu
 * tại cửa hàng, và ghi vào cùng sổ payment_transactions. Luật "ai được trả
 * góp, mấy kỳ" nằm ở InstallmentPolicy, không ở đây.
 *
 * ĐÃ GỠ "Chuyển khoản ngân hàng": xác nhận một đơn chuyển khoản đòi hỏi
 * admin mở app ngân hàng, nhìn xem tiền về chưa rồi mới bấm "Đã thanh
 * toán" — một bước THỦ CÔNG do người làm. Tự động hoá nó cần API đối
 * soát của ngân hàng, thứ nằm ngoài phạm vi đồ án. Xem migration
 * 2026_09_17_010000_remove_bank_transfer_payment_method.
 *
 * ============================================================
 * THÊM MỘT CỔNG THANH TOÁN (MoMo, VNPay...) CẦN ĐÚNG BA VIỆC:
 *
 *   1. thêm một `case` ở đây, khai trong `gatewayKey()` nó đọc cấu hình
 *      nào trong `config/payment.php`;
 *   2. viết một lớp implements `App\Services\Payment\Contracts\PaymentGateway`;
 *   3. điền khoá bí mật vào `.env`.
 *
 * KHÔNG phải sửa giao diện, KHÔNG phải sửa kiểm tra dữ liệu đầu vào,
 * KHÔNG phải sửa trang tạo mã giảm giá — cả ba chỗ đó đều hỏi
 * `available()`, và `available()` tự biết cổng nào đã cấu hình xong.
 *
 * Đó là mục đích của `gatewayKey()` + `available()`: chỗ duy nhất cần
 * sửa khi thêm hình thức mới là chính tệp này.
 */
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

    /**
     * Tiền có đi qua một cổng thanh toán bên ngoài không.
     *
     * COD thì không: tiền đi từ tay khách sang tay shipper, phần mềm chỉ
     * ghi lại. Vì thế đơn COD được đánh dấu "đã thanh toán" bởi con
     * người là ĐÚNG QUY TRÌNH, không phải một bước thủ công thừa —
     * shipper là người duy nhất biết khách đã trả hay chưa.
     *
     * Cổng online thì ngược lại: chính cổng báo về, và phần mềm không
     * được để ai bấm tay thay nó.
     */
    public function isOnline(): bool
    {
        return $this->gatewayKey() !== null;
    }

    /**
     * Khoá cấu hình trong `config/payment.php` của hình thức này.
     *
     * `null` = không đi qua cổng nào.
     *
     * Ví dụ khi thêm MoMo: `self::Momo => 'momo'`, và cấu hình nằm ở
     * `config('payment.gateways.momo')`.
     */
    public function gatewayKey(): ?string
    {
        return match ($this) {
            self::Cod, self::TraGop => null,
            self::Momo => 'momo',
        };
    }

    /**
     * Hình thức này đã được cấu hình đủ để dùng thật chưa.
     *
     * ĐÂY LÀ CHỖ CHẶN "CHỨC NĂNG GIẢ". Một hình thức online mà thiếu
     * khoá bí mật trong `.env` thì bấm vào sẽ lỗi ở giữa đường — sau khi
     * khách đã điền hết địa chỉ. Thà không hiện ra còn hơn hiện rồi hỏng.
     *
     * COD không có cổng nên luôn dùng được. Trả góp dùng được khi cửa hàng
     * bật ở trang quản trị.
     */
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

    /**
     * Những hình thức KHÁCH THẬT SỰ CHỌN ĐƯỢC lúc này.
     *
     * TÁCH KHỎI `cases()`, và đây là điểm mấu chốt: `cases()` trả về mọi
     * hình thức hệ thống BIẾT, còn hàm này trả về những hình thức hệ
     * thống LÀM ĐƯỢC. Hai câu hỏi khác nhau.
     *
     * Mọi nơi dựng danh sách cho người dùng chọn — bước thanh toán, kiểm
     * tra dữ liệu gửi lên, ô "giới hạn hình thức" khi tạo mã giảm giá —
     * đều phải hỏi hàm này. Dùng `cases()` ở đó là mở đường cho khách
     * chọn một cổng chưa cấu hình.
     *
     * @return list<self>
     */
    public static function available(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $m) => $m->isConfigured(),
        ));
    }

    /**
     * Giá trị của những hình thức chọn được, cho `Rule::in()`.
     *
     * Cùng lý do với các enum khác trong dự án: liệt kê tay là chép danh
     * sách sang một chỗ thứ hai, và hai bản sẽ lệch nhau vào đúng ngày
     * thêm một hình thức mới.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::available(), 'value');
    }
}
