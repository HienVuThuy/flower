<?php

namespace App\Services\Points;

use App\Enums\OrderStatus;
use App\Enums\PointReason;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\Refund;
use App\Models\Review;
use App\Models\User;

/** Luật kiếm điểm từ mua hàng và đánh giá — một chỗ khai, một chỗ tính. */
class PointEarning
{
    public const DONG_MOI_DIEM = 10000;

    public const DANH_GIA_NHAN_XET = 10;

    public const DANH_GIA_CHI_SAO = 3;

    public const NHAN_XET_TOI_THIEU = 30;

    public function __construct(
        private readonly PointLedger $so,
    ) {
    }

    public static function diemChoTien(string $tien): int
    {
        return max(0, (int) bcdiv($tien, (string) self::DONG_MOI_DIEM, 0));
    }

    public function donHoanTat(Order $order): int
    {
        if ($order->user_id === null || $order->status !== OrderStatus::Completed) {
            return 0;
        }

        $user = User::find($order->user_id);

        if ($user === null) {
            return 0;
        }

        $diem = self::diemChoTien(\App\Services\Loyalty\QualifiedSpending::tienHangCuaDon($order));

        if ($diem === 0) {
            return 0;
        }

        $hang = app(\App\Services\Loyalty\MemberTierResolver::class)->cua($user)['hang'];
        $them = $hang ? intdiv($diem * (int) $hang->bonus_points_percent, 100) : 0;

        $ghiChu = 'Đơn ' . $order->order_number
            . ($them > 0 ? ' (gồm +' . $hang->bonus_points_percent . '% hạng ' . $hang->name . ')' : '');

        return $this->so->cong($user, $diem + $them, PointReason::MuaHang, 'don:' . $order->id, $ghiChu)
            ? $diem + $them
            : 0;
    }

    public function hoanTien(Refund $refund): int
    {
        $order = $refund->order;

        if ($order?->user_id === null || ($user = User::find($order->user_id)) === null) {
            return 0;
        }

        $daCong = (int) PointTransaction::where('user_id', $user->id)
            ->where('source_key', 'don:' . $order->id)
            ->value('amount');

        $daTru = -(int) PointTransaction::where('user_id', $user->id)
            ->where('source_key', 'like', 'hoan:' . $order->id . ':%')
            ->sum('amount');

        $tru = min(self::diemChoTien((string) $refund->amount), $daCong - $daTru);

        if ($tru <= 0) {
            return 0;
        }

        return $this->so->tru(
            $user,
            $tru,
            PointReason::HoanTien,
            'hoan:' . $order->id . ':' . $refund->id,
            'Hoàn tiền ' . $refund->code . ' — đơn ' . $order->order_number,
        ) ? $tru : 0;
    }

    public function danhGia(Review $review): int
    {
        $user = User::find($review->user_id);

        if ($user === null || $review->order_id === null) {
            return 0;
        }

        $diem = mb_strlen(trim((string) $review->comment)) >= self::NHAN_XET_TOI_THIEU
            ? self::DANH_GIA_NHAN_XET
            : self::DANH_GIA_CHI_SAO;

        return $this->so->cong(
            $user,
            $diem,
            PointReason::DanhGia,
            'danh-gia:' . $review->order_id . ':' . $review->product_id,
            'Đánh giá sản phẩm',
        ) ? $diem : 0;
    }
}
