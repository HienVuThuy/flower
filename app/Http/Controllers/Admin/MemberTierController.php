<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\MemberTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Cấu hình hạng thành viên — sửa cả bộ trong một biểu mẫu.
 *
 * KHÔNG THÊM / XOÁ HẠNG ở đây: số hạng và mã hạng là thứ code và giao
 * diện dựa vào. Cửa hàng chỉnh ngưỡng, tên và quyền lợi.
 *
 * SỬA CẢ BỘ, không từng dòng: luật "ngưỡng tăng dần, hạng thấp nhất từ 0"
 * chỉ kiểm được trên cả bộ — sửa từng dòng thì có lúc giữa chừng hai hạng
 * cùng ngưỡng.
 */
class MemberTierController extends Controller
{
    use LogsAdminActivity;

    public function index(): View
    {
        return view('admin.member-tiers.index', [
            'cacHang' => MemberTier::query()->orderBy('min_spend')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $hienCo = MemberTier::query()->orderBy('min_spend')->get()->keyBy('id');

        $data = $request->validate([
            'hang' => ['required', 'array', 'size:' . $hienCo->count()],
            'hang.*.id' => ['required', 'integer', 'distinct', 'in:' . $hienCo->keys()->implode(',')],
            'hang.*.name' => ['required', 'string', 'max:50'],
            'hang.*.min_spend' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'hang.*.bonus_points_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ], [], [
            'hang.*.name' => 'tên hạng',
            'hang.*.min_spend' => 'ngưỡng chi tiêu',
            'hang.*.bonus_points_percent' => '% điểm thưởng thêm',
        ]);

        $dong = collect($data['hang'])
            ->map(fn (array $h) => $h + ['min_spend' => bcadd(number_format((float) $h['min_spend'], 0, '.', ''), '0', 2)])
            ->sortBy(fn (array $h) => (float) $h['min_spend'])
            ->values();

        if (bccomp($dong->first()['min_spend'], '0', 2) !== 0) {
            throw ValidationException::withMessages([
                'hang' => 'Hạng thấp nhất phải có ngưỡng 0đ — nếu không, khách mới không thuộc hạng nào.',
            ]);
        }

        if ($dong->pluck('min_spend')->unique()->count() !== $dong->count()) {
            throw ValidationException::withMessages([
                'hang' => 'Hai hạng không được cùng ngưỡng chi tiêu — khách đạt ngưỡng đó sẽ thuộc hạng nào?',
            ]);
        }

        foreach ($dong as $h) {
            $hang = $hienCo[$h['id']];
            $truoc = $hang->only(['name', 'min_spend', 'bonus_points_percent']);

            $hang->update([
                'name' => trim($h['name']),
                'min_spend' => $h['min_spend'],
                'bonus_points_percent' => (int) $h['bonus_points_percent'],
            ]);

            if ($hang->wasChanged()) {
                $this->audit()->log('member-tier.updated', 'Sửa hạng thành viên ' . $hang->name, $hang, [
                    'truoc' => $truoc,
                    'sau' => $hang->only(['name', 'min_spend', 'bonus_points_percent']),
                ]);
            }
        }

        return redirect()->route('admin.member-tiers.index')->with('success', 'Đã lưu cấu hình hạng thành viên.');
    }
}
