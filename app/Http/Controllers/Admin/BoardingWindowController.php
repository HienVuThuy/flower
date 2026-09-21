<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\BoardingWindow;
use App\Services\Boarding\BoardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Lịch trả cây theo dịp. Admin nhập ngày THẬT mỗi năm (Tết theo âm lịch nên không tự đoán).
 * Thêm đợt mới thì các cây đang chờ lịch cùng nhóm dịp được mở kỳ sau ngay.
 */
class BoardingWindowController extends Controller
{
    use LogsAdminActivity;

    public function __construct(
        private readonly BoardingService $dichVu,
    ) {
    }

    public function index(): View
    {
        return view('admin.boarding.windows', [
            'cacDip' => BoardingWindow::query()->orderByDesc('return_on')->get(),
            'nhom' => BoardingWindow::query()->select('group_key', 'name')->orderBy('group_key')->get()->unique('group_key'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dip = BoardingWindow::create($this->duLieu($request) + ['is_active' => true]);
        $moi = $this->dichVu->moKyChoLich($dip);

        $this->audit()->log('boarding-window.created', 'Thêm lịch trả cây "' . $dip->name . '"', $dip);

        return back()->with('success', 'Đã thêm lịch.' . ($moi->isNotEmpty() ? ' Mở kỳ sau cho ' . $moi->count() . ' cây đang chờ lịch.' : ''));
    }

    public function update(Request $request, BoardingWindow $window): RedirectResponse
    {
        $window->update($this->duLieu($request) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Đã lưu lịch. Phiếu đã gửi giữ ngày hẹn cũ; báo khách nếu cần dời.');
    }

    private function duLieu(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'group_key' => ['nullable', 'string', 'max:40'],
            'return_on' => ['required', 'date'],
            'take_back_on' => ['nullable', 'date', 'after:return_on'],
        ], ['take_back_on.after' => 'Ngày nhận cây lại phải sau ngày mang cây về.'], [
            'name' => 'tên dịp', 'return_on' => 'ngày mang cây về cho khách',
        ]);

        $d['group_key'] = Str::slug($d['group_key'] ?: preg_replace('/\s*\d{4}\s*$/', '', $d['name']));

        return $d;
    }
}
