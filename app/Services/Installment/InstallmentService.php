<?php

namespace App\Services\Installment;

use App\Enums\InstallmentStatus;
use App\Enums\MomoFlow;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Order\OrderException;
use App\Services\Order\OrderService;
use App\Services\Order\PaidOrderFulfilment;
use App\Services\Payment\MomoGateway;
use App\Services\Shop\Money;
use App\Services\Time\Gio;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Trả góp TRƯỚC KHI GIAO — nơi duy nhất ghi kế hoạch và từng kỳ. */
class InstallmentService
{
    public const TAI_CUA_HANG = 'tai_cua_hang';

    public function __construct(
        private readonly InstallmentPolicy $policy,
        private readonly ActivityLogger $audit,
    ) {
    }

    public function duKien(?User $user, string $tong, int $soKy): array
    {
        $xet = $this->policy->xet($user, $tong);

        if (! $xet['duoc']) {
            throw new InstallmentException((string) $xet['ly_do']);
        }

        if ($soKy < 1 || $soKy > $xet['ky_toi_da']) {
            throw new InstallmentException(sprintf('Đơn này trả góp được từ 1 đến %d kỳ.', $xet['ky_toi_da']));
        }

        return [
            'xet' => $xet,
            'lich' => InstallmentPlanner::lich(
                $tong,
                $soKy,
                $xet['tra_truoc'],
                now(Gio::mui())->toDateString(),
                InstallmentSettings::soNguyen('tra_gop.so_ngay_moi_ky'),
            ),
        ];
    }

    public function taoKeHoach(Order $order, ?User $user, int $soKy): InstallmentPlan
    {
        if ($user === null) {
            throw new InstallmentException('Đăng nhập để trả góp — cửa hàng xét trả góp theo lịch sử thanh toán của tài khoản.');
        }

        User::whereKey($user->id)->lockForUpdate()->first();

        ['xet' => $xet, 'lich' => $lich] = $this->duKien($user, (string) $order->grand_total, $soKy);

        $plan = new InstallmentPlan();
        $plan->forceFill([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'total_amount' => $order->grand_total,
            'down_payment_percent' => $xet['tra_truoc'],
            'period_count' => $soKy,
            'period_days' => InstallmentSettings::soNguyen('tra_gop.so_ngay_moi_ky'),
            'grace_days' => InstallmentSettings::soNguyen('tra_gop.ngay_an_han'),
            'credit_score' => $xet['diem'],
            'status' => InstallmentStatus::DangTra,
        ])->save();

        foreach ($lich as $dong) {
            (new InstallmentPayment())->forceFill($dong + ['installment_plan_id' => $plan->id])->save();
        }

        return $plan->load('payments');
    }

    public function ghiNhanKy(int $kyId): string
    {
        $ky = InstallmentPayment::whereKey($kyId)->lockForUpdate()->firstOrFail();
        $plan = InstallmentPlan::whereKey($ky->installment_plan_id)->lockForUpdate()->firstOrFail();

        if ($ky->paid_at !== null) {
            return 'already';
        }

        $ky->forceFill(['paid_at' => now()])->save();

        if ($plan->status !== InstallmentStatus::DangTra) {
            return 'closed';
        }

        if (InstallmentPayment::query()->where('installment_plan_id', $plan->id)->whereNull('paid_at')->exists()) {
            return 'paid';
        }

        $plan->forceFill(['status' => InstallmentStatus::HoanTat, 'completed_at' => now()])->save();

        $order = Order::whereKey($plan->order_id)->lockForUpdate()->firstOrFail();
        app(OrderService::class)->setPaymentStatus($order, PaymentStatus::Paid);

        return 'completed';
    }

    public function thuTaiCuaHang(Order $order, int $kyId): string
    {
        $ky = null;

        $ketQua = DB::transaction(function () use ($order, $kyId, &$ky) {
            $plan = InstallmentPlan::query()->where('order_id', $order->id)->lockForUpdate()->first();

            if (! $plan) {
                throw new InstallmentException('Đơn này không trả góp.');
            }

            $ky = InstallmentPayment::query()->where('installment_plan_id', $plan->id)->whereKey($kyId)->first();

            if (! $ky) {
                throw new InstallmentException('Kỳ này không thuộc kế hoạch trả góp của đơn.');
            }

            if ($plan->status !== InstallmentStatus::DangTra) {
                throw new InstallmentException(sprintf('Kế hoạch trả góp đã ở trạng thái "%s", không ghi thêm kỳ.', $plan->status->label()));
            }

            if ($ky->paid_at !== null) {
                throw new InstallmentException($ky->nhan() . ' đã được ghi nhận rồi.');
            }

            $dau = InstallmentPayment::query()->where('installment_plan_id', $plan->id)->whereNull('paid_at')->orderBy('sequence')->first();

            if ($dau && $dau->id !== $ky->id) {
                throw new InstallmentException(sprintf('Ghi theo thứ tự: %s chưa trả.', mb_strtolower($dau->nhan())));
            }

            PaymentTransaction::create([
                'order_id' => $order->id,
                'installment_payment_id' => $ky->id,
                'gateway' => self::TAI_CUA_HANG,
                'amount' => $ky->amount,
                'status' => PaymentTransactionStatus::Paid,
                'message' => 'Thu tại cửa hàng — ' . (Auth::user()?->name ?? 'không rõ người ghi'),
                'paid_at' => now(),
            ]);

            return $this->ghiNhanKy($ky->id);
        });

        $this->audit->log(
            'tra-gop.thu-tai-cua-hang',
            sprintf('Thu %s tại cửa hàng cho %s, đơn %s', Money::format((string) $ky->amount), mb_strtolower($ky->nhan()), $order->order_number),
            $order,
            ['ky' => $ky->sequence, 'amount' => (string) $ky->amount, 'ket_qua' => $ketQua],
        );

        if ($ketQua === 'completed') {
            app(PaidOrderFulfilment::class)->sauKhiTraTien($order->fresh(), 'Đã trả đủ các kỳ trả góp.');
        }

        return $ketQua;
    }

    public function moMomo(Order $order, ?MomoFlow $flow): string
    {
        $plan = InstallmentPlan::query()->where('order_id', $order->id)->with('payments')->first();

        if (! $plan || ! $plan->dangTra() || ! ($ky = $plan->kyKeTiep())) {
            throw new InstallmentException('Đơn này không còn kỳ trả góp nào để trả.');
        }

        return app(MomoGateway::class)->createPayment($order, $flow, $ky);
    }

    public function xuLyQuaHan(): int
    {
        $homNay = now(Gio::mui())->toDateString();
        $dem = 0;

        InstallmentPlan::query()
            ->where('status', InstallmentStatus::DangTra->value)
            ->with('payments')
            ->orderBy('id')
            ->each(function (InstallmentPlan $plan) use ($homNay, &$dem) {
                $ky = $plan->kyKeTiep();

                if ($ky && $ky->due_on->copy()->addDays($plan->grace_days)->toDateString() < $homNay) {
                    $dem += $this->voNo($plan, $ky) ? 1 : 0;
                }
            });

        return $dem;
    }

    public function voNo(InstallmentPlan $plan, InstallmentPayment $ky): bool
    {
        $doi = DB::transaction(function () use ($plan) {
            $khoa = InstallmentPlan::whereKey($plan->id)->lockForUpdate()->first();

            if (! $khoa || $khoa->status !== InstallmentStatus::DangTra) {
                return false;
            }

            $khoa->forceFill(['status' => InstallmentStatus::VoNo, 'defaulted_at' => now()])->save();

            return true;
        });

        if (! $doi) {
            return false;
        }

        $order = Order::find($plan->order_id);
        $lyDo = sprintf(
            'Quá hạn %s trả góp (hạn %s, ân hạn %d ngày) — kế hoạch trả góp bị huỷ.',
            mb_strtolower($ky->nhan()),
            $ky->due_on->format('d/m/Y'),
            $plan->grace_days,
        );

        if ($order && $order->status !== OrderStatus::Cancelled) {
            try {
                app(OrderService::class)->changeStatus($order, OrderStatus::Cancelled, $lyDo, tuDong: true);
            } catch (OrderException $e) {
                Log::error('Kế hoạch trả góp vỡ nhưng không huỷ được đơn.', [
                    'order' => $order->order_number,
                    'ly_do' => $e->getMessage(),
                ]);
            }
        }

        $this->audit->log('tra-gop.vo', $lyDo . ($order ? ' Đơn ' . $order->order_number . '.' : ''), $order);

        return true;
    }

    public function dongTheoDon(Order $order): void
    {
        InstallmentPlan::query()
            ->where('order_id', $order->id)
            ->where('status', InstallmentStatus::DangTra->value)
            ->get()
            ->each(fn (InstallmentPlan $p) => $p->forceFill(['status' => InstallmentStatus::DaHuy, 'cancelled_at' => now()])->save());
    }
}
