<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Các trang nội dung tĩnh: giới thiệu, liên hệ, chính sách.
 * ============================================================
 * MỘT CONTROLLER CHO CẢ NĂM TRANG, không phải năm controller.
 * Chúng không có nghiệp vụ nào — chỉ dựng một view và lấy thông tin cửa
 * hàng từ StoreProfile. Tách ra năm lớp là năm tệp gần như rỗng.
 *
 * NỘI DUNG NẰM TRONG BLADE, KHÔNG NẰM TRONG CƠ SỞ DỮ LIỆU.
 *
 * Đưa vào CSDL nghĩa là phải làm thêm trình soạn thảo trong trang quản
 * trị, phải lo lọc HTML người nhập (nếu không thì đó là lỗ hổng XSS), và
 * phải có bản sao lưu khi ai đó xoá nhầm. Đổi lấy gì? Mấy trang này thay
 * đổi vài lần một năm. Thứ thay đổi thường xuyên — hotline, email, địa
 * chỉ, số tài khoản — thì ĐÃ nằm trong CSDL và được nhúng vào đây.
 *
 * Khi nào cửa hàng thật cần tự sửa nội dung thì làm sau, và lúc đó phải
 * làm cho tử tế: soạn thảo có kiểm soát thẻ, lịch sử sửa, xem trước.
 */
class PageController extends Controller
{
    /** Slug hợp lệ → tên view + tiêu đề. Danh sách đóng, không mở. */
    private const PAGES = [
        'gioi-thieu' => ['about', 'Giới thiệu'],
        'lien-he' => ['contact', 'Liên hệ'],
        'chinh-sach-doi-tra' => ['returns', 'Chính sách đổi trả'],
        'dieu-khoan-su-dung' => ['terms', 'Điều khoản sử dụng'],
        'chinh-sach-bao-mat' => ['privacy', 'Chính sách bảo mật'],
    ];

    /**
     * MỘT route cho cả năm trang, khớp theo slug trong danh sách trên.
     *
     * DANH SÁCH ĐÓNG là điều bắt buộc: nếu ghép thẳng slug vào tên view
     * (`view('shop.pages.'.$slug)`) thì một slug như `../../admin/...`
     * biến thành đường dẫn tới bất kỳ tệp Blade nào trong dự án.
     */
    public function show(string $slug): View
    {
        abort_unless(isset(self::PAGES[$slug]), 404);

        [$view, $title] = self::PAGES[$slug];

        return view('shop.pages.'.$view, [
            'title' => $title,
            'slug' => $slug,
        ]);
    }

    /** Danh sách trang cho chân trang dựng menu — một nguồn duy nhất. */
    public static function all(): array
    {
        $out = [];

        foreach (self::PAGES as $slug => [$view, $title]) {
            $out[$slug] = $title;
        }

        return $out;
    }
}
