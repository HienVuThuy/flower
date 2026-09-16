<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Các trang nội dung tĩnh: giới thiệu, liên hệ, chính sách. */
class PageController extends Controller
{
    private const PAGES = [
        'gioi-thieu' => 'Giới thiệu',
        'lien-he' => 'Liên hệ',
        'chinh-sach-doi-tra' => 'Chính sách đổi trả',
        'dieu-khoan-su-dung' => 'Điều khoản sử dụng',
        'chinh-sach-bao-mat' => 'Chính sách bảo mật',
    ];

    public function show(string $slug): View
    {
        abort_unless(isset(self::PAGES[$slug]), 404);

        $dichVu = app(\App\Services\Content\TrangNoiDung::class);

        $daSua = $dichVu->chuanHoa((string) \App\Models\Setting::get(\App\Http\Controllers\Admin\PageContentController::KHOA . $slug, ''));

        return view('shop.pages.custom', [
            'title' => self::PAGES[$slug],
            'slug' => $slug,
            'noiDung' => $daSua !== '' ? $daSua : $dichVu->banVietSan($slug),
        ]);
    }

    public static function all(): array
    {
        return self::PAGES;
    }
}
