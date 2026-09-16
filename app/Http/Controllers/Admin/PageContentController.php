<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\PageController;
use App\Models\Setting;
use App\Services\Content\TrangNoiDung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Sửa nội dung các trang giới thiệu / chính sách. */
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
