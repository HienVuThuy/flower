<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\PageController;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sửa nội dung các trang chính sách (giới thiệu, đổi trả, bảo mật…).
 * ============================================================
 * VÌ SAO CẦN: năm trang này viết cứng trong Blade — muốn sửa một câu
 * trong chính sách đổi trả là phải sửa mã nguồn.
 *
 * ============================================================
 * VĂN BẢN THUẦN, KHÔNG PHẢI HTML.
 *
 * Cho gõ HTML là mở một lỗ XSS ngay trên trang công khai, chỉ cần một
 * tài khoản quản trị bị lộ. Văn bản thuần được escape khi hiện ra; dòng
 * bắt đầu bằng "## " thành tiêu đề, dòng trống tách đoạn — đủ cho một
 * trang chính sách.
 *
 * ĐỂ TRỐNG = DÙNG BẢN VIẾT SẴN. Không bắt cửa hàng chép lại năm trang chỉ
 * để sửa một trang.
 *
 * Lưu ở bảng `settings` đã có: năm đoạn văn bản, không cần một bảng mới.
 */
class PageContentController extends Controller
{
    public const KHOA = 'trang_noi_dung.';

    public const DAI_TOI_DA = 20000;

    public function edit(): View
    {
        $trang = [];

        foreach (PageController::all() as $slug => $tieuDe) {
            $trang[$slug] = [
                'tieu_de' => $tieuDe,
                'noi_dung' => (string) Setting::get(self::KHOA . $slug, ''),
            ];
        }

        return view('admin.pages.edit', ['trang' => $trang]);
    }

    public function update(Request $request): RedirectResponse
    {
        $slugs = array_keys(PageController::all());

        $quyTac = [];
        foreach ($slugs as $slug) {
            $quyTac['noi_dung.' . $slug] = ['nullable', 'string', 'max:' . self::DAI_TOI_DA];
        }

        $data = $request->validate($quyTac);

        // Chỉ ghi đúng năm khoá đã biết: không để một ô gửi lên tuỳ tiện
        // tạo ra khoá cài đặt lạ trong bảng `settings`.
        foreach ($slugs as $slug) {
            $giaTri = trim((string) ($data['noi_dung'][$slug] ?? ''));
            Setting::set(self::KHOA . $slug, $giaTri === '' ? null : $giaTri);
        }

        return redirect()
            ->route('admin.page-contents.edit')
            ->with('success', 'Đã lưu nội dung các trang.');
    }
}
