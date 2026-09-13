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
    /*
     * Slug → tiêu đề. Nội dung KHÔNG còn nằm trong năm tệp Blade: bản viết
     * sẵn ở resources/content/trang/{slug}.txt, bản cửa hàng sửa ở bảng
     * settings — cùng một định dạng, xem App\Services\Content\TrangNoiDung.
     */
    private const PAGES = [
        'gioi-thieu' => 'Giới thiệu',
        'lien-he' => 'Liên hệ',
        'chinh-sach-doi-tra' => 'Chính sách đổi trả',
        'dieu-khoan-su-dung' => 'Điều khoản sử dụng',
        'chinh-sach-bao-mat' => 'Chính sách bảo mật',
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

        $dichVu = app(\App\Services\Content\TrangNoiDung::class);

        /*
         * BẢN CỬA HÀNG ĐÃ SỬA thì dùng nó; chưa sửa thì dùng bản viết sẵn.
         * Xem Admin\PageContentController.
         */
        $daSua = $dichVu->chuanHoa((string) \App\Models\Setting::get(\App\Http\Controllers\Admin\PageContentController::KHOA . $slug, ''));

        return view('shop.pages.custom', [
            'title' => self::PAGES[$slug],
            'slug' => $slug,
            'noiDung' => $daSua !== '' ? $daSua : $dichVu->banVietSan($slug),
        ]);
    }

    /** Danh sách trang cho chân trang dựng menu — một nguồn duy nhất. */
    public static function all(): array
    {
        return self::PAGES;
    }
}
