<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Points\PointException;
use App\Services\Points\PointLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Đổi điểm thưởng lấy voucher. Xem PointLedger.
 *
 * Chỉ nhận MÃ GÓI từ biểu mẫu — số điểm, số tiền giảm, hạn dùng đều đọc
 * từ PointLedger::GOI trên máy chủ. Nhận số tiền từ trình duyệt là cho
 * khách tự viết voucher cho mình.
 */
class PointController extends Controller
{
    public function redeem(Request $request, PointLedger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'goi' => ['required', 'string', Rule::in(array_keys(PointLedger::GOI))],
        ]);

        try {
            $coupon = $ledger->doiVoucher(Auth::user(), $data['goi']);
        } catch (PointException $e) {
            return redirect()
                ->route('shop.profile.edit', ['muc' => 'diem-thuong'])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('shop.profile.edit', ['muc' => 'diem-thuong'])
            ->with('success', 'Đã đổi voucher ' . $coupon->code . ' — mã nằm sẵn trong Ví voucher của bạn.');
    }
}
