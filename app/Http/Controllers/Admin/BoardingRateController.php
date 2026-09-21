<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CareDifficulty;
use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\BoardingRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Bảng giá chăm cây hộ — cửa hàng tự định giá theo loại cây, độ khó, cỡ cây. */
class BoardingRateController extends Controller
{
    use LogsAdminActivity;

    public function index(): View
    {
        return view('admin.boarding.rates', [
            'cacGia' => BoardingRate::query()->withCount('bookings')->orderBy('sort_order')->orderBy('monthly_price')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $gia = BoardingRate::create($this->duLieu($request));
        $this->audit()->log('boarding-rate.created', 'Thêm giá chăm hộ "' . $gia->name . '"', $gia);

        return back()->with('success', 'Đã thêm dòng giá.');
    }

    public function update(Request $request, BoardingRate $rate): RedirectResponse
    {
        $rate->update($this->duLieu($request));
        $this->audit()->log('boarding-rate.updated', 'Sửa giá chăm hộ "' . $rate->name . '"', $rate);

        return back()->with('success', 'Đã lưu. Phiếu đã gửi giữ nguyên giá lúc gửi.');
    }

    public function destroy(BoardingRate $rate): RedirectResponse
    {
        if ($rate->bookings()->exists()) {
            $rate->update(['is_active' => false]);

            return back()->with('success', 'Dòng giá đã có phiếu dùng nên chỉ tắt, không xoá.');
        }

        $this->audit()->log('boarding-rate.deleted', 'Xoá giá chăm hộ "' . $rate->name . '"');
        $rate->delete();

        return back()->with('success', 'Đã xoá dòng giá.');
    }

    private function duLieu(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'care_difficulty' => ['nullable', Rule::enum(CareDifficulty::class)],
            'monthly_price' => ['required', 'numeric', 'min:1000', 'max:100000000'],
            'yearly_price' => ['nullable', 'numeric', 'min:1000', 'max:1000000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ], [], [
            'name' => 'tên', 'monthly_price' => 'giá tháng', 'yearly_price' => 'giá năm',
        ]);

        return $d + ['is_active' => $request->boolean('is_active'), 'needs_quote' => $request->boolean('needs_quote'), 'sort_order' => (int) ($d['sort_order'] ?? 0)];
    }
}
