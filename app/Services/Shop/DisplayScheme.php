<?php

namespace App\Services\Shop;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;

/**
 * Chế độ hiển thị SÁNG / TỐI — lựa chọn của từng người xem.
 * ============================================================
 * KHÁC HẲN VỚI THEME THEO MÙA:
 *
 *   ThemeRegistry  → Tết / Noel / Valentine. CỬA HÀNG chọn, áp cho mọi
 *                    khách. Là quyết định thương hiệu.
 *   DisplayScheme  → sáng / tối. TỪNG NGƯỜI chọn, chỉ cho chính họ. Là
 *                    tuỳ chọn dễ chịu cho mắt.
 *
 * Hai trục độc lập nhau: theme Tết vẫn phải đọc được ở chế độ tối.
 *
 * LƯU BẰNG COOKIE, KHÔNG LƯU VÀO TÀI KHOẢN. Ba lý do:
 *
 * 1. Khách chưa đăng nhập cũng phải dùng được — mà phần lớn người xem
 *    trang bán hàng là khách vãng lai.
 * 2. Đây là tuỳ chọn THEO THIẾT BỊ, không theo con người: cùng một
 *    người hoàn toàn có thể muốn nền tối trên điện thoại ban đêm và nền
 *    sáng trên máy tính ban ngày. Đồng bộ qua tài khoản là ép họ chọn
 *    một cái cho cả hai.
 * 3. Máy chủ đọc được cookie NGAY khi dựng HTML, nên thẻ <html> có sẵn
 *    thuộc tính đúng từ khung hình đầu tiên. Lưu ở localStorage thì
 *    trang luôn vẽ ra nền sáng trước rồi mới nháy sang tối.
 */
class DisplayScheme
{
    public const COOKIE = 'che_do_hien_thi';

    /** Sáng — lựa chọn tường minh. */
    public const SANG = 'sang';

    /** Tối — lựa chọn tường minh. */
    public const TOI = 'toi';

    /**
     * Theo cài đặt của hệ điều hành.
     *
     * BỎ KHỎI DANH SÁCH CHO NGƯỜI DÙNG CHỌN, nhưng GIỮ LẠI hằng số này.
     *
     * Vì sao bỏ: trên màn hình nó không nói được nó làm gì. Người dùng
     * thấy ba nút, bấm "Theo hệ thống" và kết quả trông y hệt "Nền sáng"
     * (hoặc y hệt "Nền tối") — không có gì phân biệt được, nên nó chỉ là
     * một lựa chọn thứ ba gây phân vân mà không thêm khả năng nào.
     *
     * Vì sao GIỮ hằng số: nó vẫn là giá trị MẶC ĐỊNH cho người chưa hề
     * chọn gì. Máy chủ không biết hệ điều hành của khách đang để sáng
     * hay tối, nên nó gửi "auto" xuống và đoạn script trong <head> quy
     * về giá trị cụ thể trước khi vẽ — nhờ vậy lần đầu vào trang, khách
     * thấy đúng chế độ máy họ đang dùng mà không phải chọn gì.
     *
     * Nói cách khác: "theo hệ thống" vẫn là hành vi mặc định, chỉ không
     * còn là một cái nút.
     */
    public const AUTO = 'auto';

    /**
     * Những giá trị người dùng CHỌN ĐƯỢC.
     *
     * KHÔNG gồm AUTO — xem chú thích ở hằng số đó.
     *
     * @return array<int, string>
     */
    public static function choices(): array
    {
        return [self::SANG, self::TOI];
    }

    /**
     * Mọi giá trị HỢP LỆ, gồm cả mặc định.
     *
     * Tách khỏi choices() vì hai câu hỏi khác nhau: "hiện nút nào" và
     * "chấp nhận giá trị nào". Gộp lại thì cookie `auto` của người đã
     * vào trang từ trước bị coi là rác và họ bị đẩy sang nền sáng, dù
     * máy họ đang để tối.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::AUTO, self::SANG, self::TOI];
    }

    public static function label(string $scheme): string
    {
        return match ($scheme) {
            self::TOI => 'Nền tối',
            default => 'Nền sáng',
        };
    }

    /**
     * Lựa chọn hiện tại, đọc từ cookie.
     *
     * Giá trị lạ — cookie do người dùng sửa được — quy về AUTO chứ không
     * ném lỗi: đây là tuỳ chọn hiển thị, không phải phép kiểm bảo mật, và
     * làm hỏng cả trang vì một cookie hỏng là phản ứng quá tay.
     */
    public static function current(): string
    {
        $value = (string) Request::cookie(self::COOKIE, self::AUTO);

        return in_array($value, self::all(), true) ? $value : self::AUTO;
    }

    /**
     * Cookie ghi lựa chọn, sống một năm.
     *
     * KHÔNG httpOnly: chính JavaScript ở trình duyệt cũng cần đọc để đổi
     * ngay mà không tải lại trang. Đây là tuỳ chọn giao diện, không có
     * gì bí mật — httpOnly ở đây chỉ cản trở mà không bảo vệ gì.
     */
    public static function cookie(string $scheme): \Symfony\Component\HttpFoundation\Cookie
    {
        $scheme = in_array($scheme, self::all(), true) ? $scheme : self::AUTO;

        return Cookie::make(
            name: self::COOKIE,
            value: $scheme,
            minutes: 60 * 24 * 365,
            httpOnly: false,
        );
    }
}
