<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;

/** Chấm điểm rủi ro cho một đơn hàng vừa đặt. */
class OrderRiskScorer
{
    public function score(Order $order): array
    {
        $flags = [];

        foreach ([
            $this->cancelledBefore($order),
            $this->highValueCod($order),
            $this->guest($order),
            $this->noEmail($order),
            $this->burstOrders($order),
        ] as $flag) {
            if ($flag !== null) {
                $flags[] = $flag;
            }
        }

        $score = min(100, array_sum(array_column($flags, 'points')));

        return ['score' => $score, 'flags' => $flags];
    }

    public function apply(Order $order): void
    {
        $result = $this->score($order);

        $order->forceFill([
            'risk_score' => $result['score'],
            'risk_flags' => $result['flags'] ?: null,
        ])->save();
    }

    private function cancelledBefore(Order $order): ?array
    {
        $query = Order::query()
            ->where('id', '!=', $order->id)
            ->where('status', OrderStatus::Cancelled);

        if ($order->user_id) {
            $query->where('user_id', $order->user_id);
        } elseif ($order->recipient_phone) {
            $query->where('recipient_phone', $order->recipient_phone);
        } else {
            return null;
        }

        $count = $query->count();

        if ($count === 0) {
            return null;
        }

        $points = min(
            (int) config('risk.cancelled_cap', 40),
            $count * (int) config('risk.weights.cancelled_before', 20),
        );

        return [
            'code' => 'cancelled_before',
            'label' => "Đã từng huỷ {$count} đơn trước đây",
            'points' => $points,
        ];
    }

    private function highValueCod(Order $order): ?array
    {
        if ($order->payment_method !== PaymentMethod::Cod) {
            return null;
        }

        $threshold = (float) config('risk.thresholds.high_value', 1500000);

        if ((float) $order->grand_total < $threshold) {
            return null;
        }

        return [
            'code' => 'high_value_cod',
            'label' => 'Đơn COD giá trị cao ('.number_format((float) $order->grand_total, 0, ',', '.').'đ)',
            'points' => (int) config('risk.weights.high_value_cod', 25),
        ];
    }

    private function guest(Order $order): ?array
    {
        if ($order->user_id) {
            return null;
        }

        return [
            'code' => 'guest',
            'label' => 'Đặt hàng không đăng nhập',
            'points' => (int) config('risk.weights.guest', 10),
        ];
    }

    private function noEmail(Order $order): ?array
    {
        if ($order->recipient_email) {
            return null;
        }

        return [
            'code' => 'no_email',
            'label' => 'Không để lại email liên hệ',
            'points' => (int) config('risk.weights.no_email', 10),
        ];
    }

    private function burstOrders(Order $order): ?array
    {
        if (! $order->recipient_phone) {
            return null;
        }

        $hours = (int) config('risk.thresholds.burst_hours', 24);
        $limit = (int) config('risk.thresholds.burst_count', 3);

        $count = Order::query()
            ->where('id', '!=', $order->id)
            ->where('recipient_phone', $order->recipient_phone)
            ->where('created_at', '>=', now()->subHours($hours))
            ->count();

        if ($count + 1 < $limit) {
            return null;
        }

        return [
            'code' => 'burst_orders',
            'label' => ($count + 1)." đơn từ cùng số điện thoại trong {$hours} giờ",
            'points' => (int) config('risk.weights.burst_orders', 20),
        ];
    }
}
