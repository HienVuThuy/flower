<?php

namespace App\Services\Shop;

use App\Models\Setting;

/**
 * Thông tin nhận diện và thông tin thanh toán của cửa hàng.
 * ============================================================
 * NƠI DUY NHẤT trả lời "cửa hàng này là ai, ở đâu, liên hệ thế nào".
 *
 * VÌ SAO KHÔNG GỌI THẲNG Setting::get() Ở KHẮP NƠI:
 * Chân trang, trang liên hệ, trang thanh toán, thư xác nhận đơn và trang
 * quản trị đều cần đúng mấy giá trị này. Mỗi chỗ tự gõ tên khoá là năm
 * cơ hội gõ sai một chữ và nhận về chuỗi rỗng — không lỗi, không cảnh
 * báo, chỉ là số tài khoản biến mất khỏi thư gửi khách.
 *
 * GIÁ TRỊ MẶC ĐỊNH NẰM Ở ĐÂY, không nằm rải rác trong Blade. Cửa hàng
 * chưa điền thì mọi nơi hiện cùng một thứ.
 *
 * KHÔNG lưu số tài khoản trong .env: đây là dữ liệu NGHIỆP VỤ mà chủ cửa
 * hàng phải tự đổi được từ trang quản trị, không phải cấu hình hạ tầng.
 * Nó cũng không phải bí mật — số tài khoản vốn để đưa cho người khác.
 */
class StoreProfile
{
    /** Khoá trong bảng `settings` và giá trị mặc định của từng khoá. */
    public const FIELDS = [
        /*
         * TÊN CỬA HÀNG — trước đây viết cứng ở 24 chỗ.
         *
         * Header, footer, sáu mẫu thư, tiêu đề mọi trang, trang giới
         * thiệu và cả layout quản trị đều gõ tay "Flower & Plant". Đổi
         * tên cửa hàng nghĩa là sửa 24 tệp và chắc chắn bỏ sót một chỗ —
         * thường là một mẫu thư, tức là chỗ khách nhìn thấy mà chủ cửa
         * hàng thì không.
         */
        'site_name' => 'Angevil',

        /*
         * Dòng chữ nhỏ dưới tên, ở logo và chân trang.
         */
        'site_tagline' => 'Hoa tươi & Cây cảnh',

        /*
         * LOGO DO ADMIN TẢI LÊN — để trống thì dùng hình vẽ sẵn.
         *
         * Không bắt buộc: <x-site.brand-mark> là một SVG theo màu chữ
         * xung quanh, chạy được trên nền sáng lẫn nền tối và không bao
         * giờ vỡ nét. Một cửa hàng chưa có logo riêng vẫn có nhận diện
         * tử tế thay vì một ô ảnh vỡ.
         */
        'site_logo' => null,

        'site_hotline' => '0912345678',
        'site_email' => 'anaorin229@gmail.com',
        'site_address' => 'Trường Đại học Tài nguyên và Môi trường Hà Nội, '
            .'41A đường Phú Diễn, phường Phú Diễn, quận Bắc Từ Liêm, Hà Nội',

        /*
         * TỈNH/THÀNH của cửa hàng — tách riêng khỏi địa chỉ đầy đủ.
         *
         * Phí giao tính theo TỈNH (xem ShippingRates), nên không thể bắt
         * hệ thống tự dò tên tỉnh trong một chuỗi địa chỉ do người gõ
         * tay. Tách ra một ô riêng, chọn từ danh sách có sẵn.
         */
        'site_province' => 'Thành phố Hà Nội',
    ];

    public static function get(string $key): ?string
    {
        $value = Setting::get($key, self::FIELDS[$key] ?? null);

        // Chuỗi rỗng trong CSDL cũng coi như chưa điền, để nơi gọi chỉ
        // phải kiểm null thay vì kiểm cả hai.
        return is_string($value) && trim($value) === ''
            ? (self::FIELDS[$key] ?? null)
            : $value;
    }

    public static function name(): string
    {
        return self::get('site_name') ?: (self::FIELDS['site_name'] ?? 'Cửa hàng');
    }

    public static function tagline(): ?string
    {
        return self::get('site_tagline');
    }

    /**
     * Đường dẫn công khai của logo, hoặc null nếu chưa tải lên.
     *
     * Trả về null chứ không trả chuỗi rỗng: nơi gọi cần phân biệt "chưa
     * có logo" (thì vẽ hình mặc định) với "có nhưng rỗng" — cái thứ hai
     * cho ra một thẻ <img src=""> mà trình duyệt hiểu là tải lại chính
     * trang hiện tại.
     */
    public static function logoUrl(): ?string
    {
        $path = self::get('site_logo');

        return $path ? \Illuminate\Support\Facades\Storage::url($path) : null;
    }

    /**
     * Số hotline — CHỈ KHI NÓ LÀ MỘT SỐ ĐIỆN THOẠI.
     *
     * Cơ sở dữ liệu đang lưu `site_hotline = "demo"`. Trả nguyên chuỗi đó
     * thì mọi email gửi khách in "Gọi demo hoặc trả lời email này", chân
     * trang in "demo" cạnh biểu tượng điện thoại, và trang theo dõi đơn nói
     * "liên hệ cửa hàng theo số demo". Một số không gọi được còn tệ hơn
     * không có số: khách thử gọi, rồi nghĩ cửa hàng không có thật.
     *
     * Không phải số thì trả null — mọi nơi hiển thị đã có nhánh "chưa có
     * hotline" (trả lời email này), và giờ nhánh đó được dùng đúng lúc.
     */
    public static function hotline(): ?string
    {
        $so = trim((string) self::get('site_hotline'));

        return self::laSoDienThoai($so) ? $so : null;
    }

    /**
     * 8-15 chữ số, cho phép dấu +, khoảng trắng, chấm, gạch và ngoặc để
     * viết cho dễ đọc ("0912 345 678", "(024) 3838 1234", "+84 912...").
     */
    public static function laSoDienThoai(string $chuoi): bool
    {
        if (! preg_match('/^\+?[\d\s.\-()]+$/', $chuoi)) {
            return false;
        }

        $chuSo = strlen(preg_replace('/\D/', '', $chuoi) ?? '');

        return $chuSo >= 8 && $chuSo <= 15;
    }

    public static function email(): ?string
    {
        return self::get('site_email');
    }

    public static function address(): ?string
    {
        return self::get('site_address');
    }

    public static function province(): ?string
    {
        return self::get('site_province');
    }
}
