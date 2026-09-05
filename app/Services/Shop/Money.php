<?php

namespace App\Services\Shop;

use App\Models\Setting;

/**
 * NƠI DUY NHẤT quyết định một số tiền hiện ra trông như thế nào.
 * ============================================================
 * TRƯỚC KHI CÓ TỆP NÀY: 46 chỗ trong 28 tệp giao diện tự gọi
 * `number_format($x, 0, ',', '.')` rồi tự nối thêm ký hiệu tiền — mỗi
 * chỗ một kiểu. Đếm được ba biến thể chỉ riêng phần ký hiệu:
 * `₫`, `&#8363;`, `đ`, có chỗ có dấu cách trước, có chỗ không.
 *
 * Hậu quả không phải là "trông hơi lệch nhau", mà là: **đơn vị tiền tệ
 * không sửa được**. Admin muốn đổi sang một ký hiệu khác thì phải sửa 46
 * chỗ, và chỗ thứ 47 thêm vào tuần sau sẽ lại viết cứng.
 *
 * ============================================================
 * BỐN THAM SỐ, TẤT CẢ SỬA ĐƯỢC Ở TRANG CẤU HÌNH:
 *
 *   - mã tiền tệ   (VND)  — dùng cho dữ liệu máy đọc, không hiện ra
 *   - ký hiệu      (₫)    — thứ khách nhìn thấy
 *   - vị trí       (sau)  — "260.000₫" hay "₫260.000"
 *   - số lẻ        (0)    — VND không có xu; USD thì cần 2
 *
 * Dấu phân cách thì SUY RA từ số lẻ, không phải một ô nhập nữa: kiểu
 * Việt/Âu dùng `.` cho nghìn và `,` cho thập phân, kiểu Anh/Mỹ thì ngược
 * lại. Cho admin tự chọn từng dấu là mở đường cho những tổ hợp không tồn
 * tại ở đâu cả (`1.234.56`).
 */
class Money
{
    /** @var array<string, string> Giá trị mặc định khi cửa hàng chưa đặt gì. */
    public const FIELDS = [
        'currency_code' => 'VND',
        'currency_symbol' => '₫',
        'currency_position' => 'after',
        'currency_decimals' => '0',
    ];

    public static function code(): string
    {
        return self::get('currency_code');
    }

    public static function symbol(): string
    {
        return self::get('currency_symbol');
    }

    /** 'after' = 260.000₫ | 'before' = ₫260.000 */
    public static function position(): string
    {
        return self::get('currency_position') === 'before' ? 'before' : 'after';
    }

    public static function decimals(): int
    {
        return max(0, min(4, (int) self::get('currency_decimals')));
    }

    /**
     * Số tiền hoàn chỉnh kèm ký hiệu: "260.000₫".
     *
     * NHẬN CẢ CHUỖI LẪN SỐ. Mọi cột tiền trong dự án là `decimal` và
     * Eloquent trả về chuỗi ('260000.00'); ép kiểu ở đây một lần để nơi
     * gọi không phải nhớ.
     */
    public static function format(string|int|float|null $amount): string
    {
        $so = self::number($amount);
        $kyHieu = self::symbol();

        if ($kyHieu === '') {
            return $so;
        }

        /*
         * Ký hiệu đứng SAU thì dính liền số (kiểu Việt Nam: 260.000₫);
         * đứng TRƯỚC thì cũng dính liền (kiểu Mỹ: $260,000). Không chèn
         * dấu cách ở cả hai — thêm khoảng trắng vào giữa làm con số bị
         * ngắt dòng giữa chừng ở màn hình hẹp, và "260.000" rơi xuống
         * một dòng còn "₫" nằm lại dòng trên.
         */
        return self::position() === 'before'
            ? $kyHieu . $so
            : $so . $kyHieu;
    }

    /** Chỉ phần SỐ, không có ký hiệu: "260.000". */
    public static function number(string|int|float|null $amount): string
    {
        $le = self::decimals();

        /*
         * Kiểu Việt/Âu: `.` ngăn nghìn, `,` ngăn thập phân.
         *
         * Suy ra từ mã tiền tệ chứ không phải một ô nhập riêng — xem
         * chú thích đầu tệp.
         */
        return self::code() === 'USD'
            ? number_format((float) $amount, $le, '.', ',')
            : number_format((float) $amount, $le, ',', '.');
    }

    /**
     * Đọc một tham số, rơi về mặc định khi chưa đặt hoặc để trống.
     *
     * Chuỗi rỗng trong CSDL cũng coi như chưa đặt: một ô nhập bị xoá
     * trắng KHÔNG được biến ký hiệu tiền thành rỗng trên toàn bộ trang.
     */
    private static function get(string $key): string
    {
        $value = Setting::get($key, self::FIELDS[$key] ?? '');

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : (self::FIELDS[$key] ?? '');
    }
}
