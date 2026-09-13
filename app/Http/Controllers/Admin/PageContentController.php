<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\PageController;
use App\Models\Setting;
use App\Services\Content\TrangNoiDung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sửa nội dung các trang giới thiệu / chính sách.
 * ============================================================
 * Ô SOẠN ĐIỀN SẴN NỘI DUNG ĐANG HIỆN — bản đã sửa, hoặc bản viết sẵn nếu
 * chưa sửa. Sửa một câu thì sửa đúng câu đó, không phải chép lại cả trang.
 *
 * LƯU Y NGUYÊN BẢN VIẾT SẴN = KHÔNG LƯU GÌ. Bấm Lưu mà không đổi chữ nào
 * thì trang vẫn đi theo bản viết sẵn; nếu ghi chép đè vào cài đặt, lần sau
 * bản viết sẵn được cập nhật thì trang này đứng yên ở bản cũ mà không ai
 * biết vì sao. Xoá trắng ô cũng quay về bản viết sẵn.
 *
 * Định dạng và cách chống XSS: xem App\Services\Content\TrangNoiDung.
 */
class PageContentController extends Controller
{
    public const KHOA = 'trang_noi_dung.';

    public const DAI_TOI_DA = 20000;

    public function __construct(
        private readonly TrangNoiDung $noiDung,
    ) {
    }

    public function edit(): View
    {
        $trang = [];

        foreach (PageController::all() as $slug => $tieuDe) {
            $daSua = $this->noiDung->chuanHoa((string) Setting::get(self::KHOA . $slug, ''));

            $trang[$slug] = [
                'tieu_de' => $tieuDe,
                'noi_dung' => $daSua !== '' ? $daSua : $this->noiDung->banVietSan($slug),
                'da_sua' => $daSua !== '',
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

        // Chỉ ghi đúng những khoá đã biết: ô gửi lên tuỳ tiện không tạo được
        // khoá cài đặt lạ trong bảng `settings`.
        foreach ($slugs as $slug) {
            $giaTri = $this->noiDung->chuanHoa((string) ($data['noi_dung'][$slug] ?? ''));
            $giongBanGoc = $giaTri === $this->noiDung->banVietSan($slug);

            Setting::set(self::KHOA . $slug, ($giaTri === '' || $giongBanGoc) ? null : $giaTri);
        }

        return redirect()
            ->route('admin.page-contents.edit')
            ->with('success', 'Đã lưu nội dung các trang.');
    }
}
