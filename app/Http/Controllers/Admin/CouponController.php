<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\SortsAdminList;
use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Enums\CouponType;
use App\Enums\PromotionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CouponController extends Controller
{
    use SortsAdminList;

    use LogsAdminActivity;

    public function index(Request $request): View
    {
        return view('admin.coupons.index', [
            'coupons' => Coupon::query()
                ->when($request->filled('q'), function ($query) use ($request) {
                    $tu = trim((string) $request->query('q'));

                    $query->where(function ($q) use ($tu) {
                        $q->where('code', 'like', '%'.$tu.'%')
                            ->orWhere('name', 'like', '%'.$tu.'%');
                    });
                })

                ->when(
                    $request->filled('status'),
                    fn ($q) => $q->where('status', $request->string('status'))
                )

                ->when($request->query('dung_duoc') === 'co', fn ($q) => $q->usableNow())

                ->tap(fn ($q) => $this->applySort($q, $request, [
                    'ma' => 'code',
                    'luot-dung' => 'used_count',
                    'het-han' => 'ends_at',
                    'trang-thai' => 'status',
                ], fn ($q) => $q->latest()))

                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Coupon(['status' => PromotionStatus::Draft]));
    }

    public function store(CouponRequest $request): RedirectResponse
    {
        $coupon = Coupon::create($request->validated());

        $this->logCrud('coupon.created', $coupon, 'mã giảm giá', $coupon->code);

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', 'Đã tạo mã giảm giá.');
    }

    public function edit(Coupon $coupon): View
    {
        return $this->form($coupon);
    }

    public function update(CouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($request->validated());

        $this->logCrud('coupon.updated', $coupon, 'mã giảm giá', $coupon->code);

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', 'Đã cập nhật mã giảm giá.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->used_count > 0) {
            return back()->with(
                'error',
                'Mã đã được sử dụng nên không xoá được. Hãy chuyển trạng thái sang "Kết thúc".',
            );
        }

        $this->logCrud('coupon.deleted', $coupon, 'mã giảm giá', $coupon->code);

        $coupon->delete();

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', 'Đã xoá mã giảm giá.');
    }

    private function form(Coupon $coupon): View
    {
        return view('admin.coupons.form', [
            'coupon' => $coupon,
            'types' => CouponType::cases(),
            'statuses' => PromotionStatus::cases(),
        ]);
    }
}
