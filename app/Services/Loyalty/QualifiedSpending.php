<?php

namespace App\Services\Loyalty;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;

/**
 * Chi tiêu hợp lệ — NƠI DUY NHẤT định nghĩa "khách đã thật sự mua bao nhiêu".
 * ============================================================
 * = tổng đơn ĐÃ GIAO − phí vận chuyển − phần đã hoàn tiền xong.
 *
 *   - Đơn huỷ, đơn chưa giao: không tính. Đặt rồi huỷ không phải mua.
 *   - Phí vận chuyển: không tính. Đó là tiền trả cho GHN, không phải hàng.
 *   - Hoàn tiền: trừ ra. Không trừ thì "mua rồi trả lại" là cách lên hạng.
 *
 * Điểm mua hàng (PointEarning) và hạng thành viên đọc cùng một định nghĩa:
 * hai nơi hai cách tính thì khách thấy "đơn này 32 điểm" nhưng hạng lại
 * tính đơn đó theo con số khác.
 *
 * Tiền khách bù khi đổi hàng KHÔNG tính: nó gắn với phiếu đổi chứ không với
 * giá trị hàng của đơn.
 */
final class QualifiedSpending
{
    /** Tiền hàng hợp lệ của MỘT đơn (chưa xét trạng thái). */
    public static function tienHangCuaDon(Order $order): string
    {
        return bcsub(
            bcsub((string) $order->grand_total, (string) ($order->shipping_fee ?? '0'), 2),
            (string) $order->refundedAmount(),
            2,
        );
    }

    public function cua(User $user): string
    {
        $daGiao = Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Completed->value);

        $goc = (clone $daGiao)
            ->selectRaw('COALESCE(SUM(grand_total), 0) AS tong, COALESCE(SUM(shipping_fee), 0) AS ship')
            ->first();

        $hoan = Refund::query()
            ->where('status', RefundStatus::Completed->value)
            ->whereIn('order_id', (clone $daGiao)->select('id'))
            ->sum('amount');

        $tien = bcsub(
            bcsub(number_format((float) $goc->tong, 2, '.', ''), number_format((float) $goc->ship, 2, '.', ''), 2),
            number_format((float) $hoan, 2, '.', ''),
            2,
        );

        return bccomp($tien, '0', 2) < 0 ? '0.00' : $tien;
    }
}
