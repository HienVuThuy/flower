<?php

namespace App\Services\Credit;

use App\Enums\InstallmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\User;

/**
 * Điểm tín dụng — MỨC TIN CẬY THANH TOÁN, không phải tiền.
 * ============================================================
 * KHÔNG tiêu được, KHÔNG đổi quà, KHÔNG thay hạng thành viên (hạng theo chi
 * tiêu) hay điểm thưởng (tiêu được). Nó chỉ trả lời một câu: khách này có
 * trả tiền đúng như đã hứa không — và câu đó quyết định trả góp được mấy
 * kỳ, trả trước bao nhiêu (xem InstallmentPolicy).
 *
 * TÍNH LẠI MỖI LẦN TỪ LỊCH SỬ, không lưu một con số: không có cột nào để
 * sửa tay, và mọi thay đổi đều giải thích được bằng một sự việc đã xảy ra.
 *
 *   Cơ bản                                   50
 *   Đơn đã giao và đã thanh toán             +2 mỗi đơn, tối đa +20
 *   Kỳ trả góp trả đúng hạn                  +3 mỗi kỳ, tối đa +30
 *   Kỳ trả góp trả trễ                       −5 mỗi kỳ
 *   Kế hoạch trả góp vỡ (quá hạn bị huỷ)     −30 mỗi lần
 *   Đơn COD huỷ khi đang giao (không nhận)   −10 mỗi đơn
 *   Kẹp trong 0..100.
 *
 * Phần cộng có trần, phần trừ thì không: mua nhiều không được phép che một
 * lần bỏ trả góp.
 */
class CreditScore
{
    public const CO_BAN = 50;

    public const DON_TOT = 2;

    public const DON_TOT_TRAN = 20;

    public const KY_DUNG_HAN = 3;

    public const KY_DUNG_HAN_TRAN = 30;

    public const KY_TRE = -5;

    public const VO_NO = -30;

    public const TU_CHOI_NHAN = -10;

    /**
     * @return array{diem: int, yeu_to: list<array{ma: string, nhan: string, so_lan: int, diem: int}>}
     */
    public function cua(User $user): array
    {
        $donTot = Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Completed->value)
            ->where('payment_status', PaymentStatus::Paid->value)
            ->count();

        $kyDaTra = InstallmentPayment::query()
            ->whereNotNull('paid_at')
            ->whereIn('installment_plan_id', InstallmentPlan::query()->where('user_id', $user->id)->select('id'))
            ->get(['id', 'due_on', 'paid_at']);

        $kyTre = $kyDaTra->filter(fn (InstallmentPayment $k) => $k->traTre())->count();
        $kyDungHan = $kyDaTra->count() - $kyTre;

        $voNo = InstallmentPlan::query()
            ->where('user_id', $user->id)
            ->where('status', InstallmentStatus::VoNo->value)
            ->count();

        $tuChoiNhan = Order::query()
            ->where('user_id', $user->id)
            ->where('payment_method', PaymentMethod::Cod->value)
            ->where('status', OrderStatus::Cancelled->value)
            ->whereIn('id', OrderStatusEvent::query()->where('status', OrderStatus::Shipping->value)->select('order_id'))
            ->count();

        $yeuTo = [
            ['ma' => 'don_tot', 'nhan' => 'Đơn đã giao và đã thanh toán', 'so_lan' => $donTot, 'diem' => min($donTot * self::DON_TOT, self::DON_TOT_TRAN)],
            ['ma' => 'ky_dung_han', 'nhan' => 'Kỳ trả góp trả đúng hạn', 'so_lan' => $kyDungHan, 'diem' => min($kyDungHan * self::KY_DUNG_HAN, self::KY_DUNG_HAN_TRAN)],
            ['ma' => 'ky_tre', 'nhan' => 'Kỳ trả góp trả trễ', 'so_lan' => $kyTre, 'diem' => $kyTre * self::KY_TRE],
            ['ma' => 'vo_no', 'nhan' => 'Kế hoạch trả góp quá hạn bị huỷ', 'so_lan' => $voNo, 'diem' => $voNo * self::VO_NO],
            ['ma' => 'tu_choi_nhan', 'nhan' => 'Đơn thu tiền khi nhận bị huỷ lúc đang giao', 'so_lan' => $tuChoiNhan, 'diem' => $tuChoiNhan * self::TU_CHOI_NHAN],
        ];

        $diem = self::CO_BAN + array_sum(array_column($yeuTo, 'diem'));

        return [
            'diem' => max(0, min(100, $diem)),
            'yeu_to' => $yeuTo,
        ];
    }
}
