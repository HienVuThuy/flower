<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\MemberTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Cấu hình hạng thành viên — sửa cả bộ trong một biểu mẫu. */
class MemberTierController extends Controller
{
    use LogsAdminActivity;

    private const TRUONG = ['name', 'min_spend', 'discount_percent', 'free_shipping_from', 'bonus_points_percent'];

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

            'hang.*.discount_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'hang.*.free_shipping_from' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'hang.*.bonus_points_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ], [], [
            'hang.*.name' => 'tên hạng',
            'hang.*.min_spend' => 'ngưỡng chi tiêu',
            'hang.*.discount_percent' => '% giảm tiền hàng',
            'hang.*.free_shipping_from' => 'ngưỡng miễn phí giao',
            'hang.*.bonus_points_percent' => '% điểm thưởng thêm',
        ]);

        $dong = collect($data['hang'])
            ->map(fn (array $h) => array_merge($h, [
                'min_spend' => bcadd(number_format((float) $h['min_spend'], 0, '.', ''), '0', 2),
                'discount_percent' => number_format((float) $h['discount_percent'], 2, '.', ''),
                'free_shipping_from' => ($h['free_shipping_from'] ?? null) === null || $h['free_shipping_from'] === ''
                    ? null
                    : bcadd(number_format((float) $h['free_shipping_from'], 0, '.', ''), '0', 2),
            ]))
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
            $truoc = $hang->only(self::TRUONG);

            $hang->update([
                'name' => trim($h['name']),
                'min_spend' => $h['min_spend'],
                'discount_percent' => $h['discount_percent'],
                'free_shipping_from' => $h['free_shipping_from'],
                'bonus_points_percent' => (int) $h['bonus_points_percent'],
            ]);

            if ($hang->wasChanged()) {
                $this->audit()->log('member-tier.updated', 'Sửa hạng thành viên ' . $hang->name, $hang, [
                    'truoc' => $truoc,
                    'sau' => $hang->only(self::TRUONG),
                ]);
            }
        }

        return redirect()->route('admin.member-tiers.index')->with('success', 'Đã lưu cấu hình hạng thành viên.');
    }
}
