<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\Coupon\CouponException;
use App\Services\Coupon\CouponWallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Trang voucher: xem mã đang mời, lưu về ví, xem ví của mình. */
class VoucherController extends Controller
{
    public function __construct(
        private readonly CouponWallet $wallet,
    ) {
    }

    public function index(): View
    {
        $user = Auth::user();

        $xemDaAn = request()->boolean('da-an');
        $xemHetHan = request()->boolean('het-han');

        $viDay = $user ? $this->wallet->forUser($user) : collect();

        [$conDung, $hetHieuLuc] = $viDay->partition(
            fn (array $row) => $this->wallet->conDungDuoc($row)
        );

        return view('shop.vouchers.index', [
            'mine' => $conDung->values(),
            'claimable' => $this->wallet->claimableFor($user),

            'hiddenCount' => $this->wallet->hiddenCount($user),
            'daAn' => $xemDaAn && $user ? $this->wallet->forUser($user, daAn: true) : collect(),
            'xemDaAn' => $xemDaAn,

            'hetHieuLucCount' => $hetHieuLuc->count(),
            'hetHieuLuc' => $xemHetHan ? $hetHieuLuc->values() : collect(),
            'xemHetHan' => $xemHetHan,
        ]);
    }

    public function show(Coupon $coupon): View
    {
        abort_unless($coupon->is_public, 404);

        $user = Auth::user();

        $row = $user
            ? $this->wallet->forUser($user)->firstWhere('coupon.id', $coupon->id)
            : null;

        return view('shop.vouchers.show', [
            'coupon' => $coupon->load('promotion'),
            'saved' => $row !== null,
            'usedCount' => (int) ($row['usedCount'] ?? 0),
        ]);
    }

    public function claim(Coupon $coupon): RedirectResponse
    {
        $user = Auth::user();

        try {
            $saved = $this->wallet->claim($user, $coupon);
        } catch (CouponException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $saved
            ? 'Đã lưu mã '.$coupon->code.' vào ví voucher.'
            : 'Mã '.$coupon->code.' vốn đã có trong ví của bạn.');
    }

    public function discard(Coupon $coupon): RedirectResponse
    {
        $ket = $this->wallet->discard(Auth::user(), $coupon);

        return match ($ket) {
            'deleted' => back()->with('success', 'Đã bỏ mã '.$coupon->code.' khỏi ví.'),
            'hidden' => back()->with(
                'success',
                'Đã ẩn mã '.$coupon->code.'. Mã đã dùng nên lượt sử dụng vẫn được giữ; '
                .'bạn xem lại được ở mục mã đã ẩn.',
            ),
            default => back()->with('error', 'Mã này không có trong ví của bạn.'),
        };
    }

    public function unhide(Coupon $coupon): RedirectResponse
    {
        $ok = $this->wallet->unhide(Auth::user(), $coupon);

        return back()->with($ok ? 'success' : 'error', $ok
            ? 'Đã đưa mã '.$coupon->code.' trở lại ví.'
            : 'Mã này không nằm trong mục đã ẩn.');
    }
}
