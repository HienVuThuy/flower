<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Services\Shop\ThamSoKinhDoanh;
use Illuminate\Database\Eloquent\Builder;

/**
 * Chấm điểm rủi ro cho một đơn hàng vừa đặt — bài toán "bom hàng" COD: khách không nhận,
 * cửa hàng mất phí giao hai chiều, hoa tươi héo thì mất trắng. Chỉ gắn cờ để người gọi xác nhận,
 * không tự chặn. Đơn đã trả trước thì không có rủi ro bom hàng, chỉ còn xét đặt dồn dập.
 */
class OrderRiskScorer
{
    public function score(Order $order): array
    {
        $cod = $order->payment_method === PaymentMethod::Cod;

        $flags = array_values(array_filter([
            $cod ? $this->cancelledBefore($order) : null,
            $this->highValueCod($order),
            $cod ? $this->freshFlowerCod($order) : null,
            $cod ? $this->guest($order) : null,
            $cod ? $this->noEmail($order) : null,
            $this->burstOrders($order),
            $cod ? $this->trustedCustomer($order) : null,
        ]));

        $score = max(0, min(100, array_sum(array_column($flags, 'points'))));

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

    /** Đơn cùng khách (tài khoản, không có thì số điện thoại), trừ chính đơn này. */
    private function cungKhach(Order $order): ?Builder
    {
        $q = Order::query()->where('id', '!=', $order->id);

        if ($order->user_id) {
            return $q->where('user_id', $order->user_id);
        }

        return $order->recipient_phone ? $q->where('recipient_phone', $order->recipient_phone) : null;
    }

    /**
     * Chỉ đếm đơn do KHÁCH huỷ, hoặc đơn huỷ khi đã đi giao (khách không nhận).
     * Đơn cửa hàng tự huỷ vì hết hàng không phải lỗi của khách.
     */
    private function cancelledBefore(Order $order): ?array
    {
        $q = $this->cungKhach($order);

        if ($q === null) {
            return null;
        }

        $count = $q->where('status', OrderStatus::Cancelled->value)
            ->where(function ($w) {
                $w->whereIn('id', OrderStatusEvent::query()->where('status', OrderStatus::Shipping->value)->select('order_id'))
                    ->orWhereIn('id', OrderStatusEvent::query()
                        ->where('status', OrderStatus::Cancelled->value)
                        ->whereColumn('order_status_events.changed_by', 'orders.user_id')
                        ->select('order_id'));
            })
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'code' => 'cancelled_before',
            'label' => "Từng tự huỷ hoặc không nhận {$count} đơn",
            'points' => min(
                (int) config('risk.cancelled_cap', 40),
                $count * (int) config('risk.weights.cancelled_before', 20),
            ),
        ];
    }

    private function highValueCod(Order $order): ?array
    {
        if ($order->payment_method !== PaymentMethod::Cod) {
            return null;
        }

        $threshold = (float) ThamSoKinhDoanh::giaTri('risk.thresholds.high_value');

        if ((float) $order->grand_total < $threshold) {
            return null;
        }

        return [
            'code' => 'high_value_cod',
            'label' => 'Đơn COD giá trị cao (' . number_format((float) $order->grand_total, 0, ',', '.') . 'đ)',
            'points' => (int) config('risk.weights.high_value_cod', 25),
        ];
    }

    /** Hoa tươi bị trả lại là mất trắng — cây chậu, phụ kiện thì còn bán lại được. */
    private function freshFlowerCod(Order $order): ?array
    {
        $coHoaTuoi = $order->items()
            ->whereHas('product', fn ($q) => $q->withTrashed()->where('product_type', ProductType::Flower->value))
            ->exists();

        return $coHoaTuoi ? [
            'code' => 'fresh_flower_cod',
            'label' => 'Có hoa tươi, trả khi nhận — khách không nhận là hoa héo, mất trắng',
            'points' => (int) config('risk.weights.fresh_flower_cod', 10),
        ] : null;
    }

    private function guest(Order $order): ?array
    {
        if ($order->user_id) {
            return null;
        }

        return [
            'code' => 'guest',
            'label' => 'Đặt hàng không đăng nhập',
            'points' => (int) config('risk.weights.guest', 5),
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
            'points' => (int) config('risk.weights.no_email', 5),
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
            'label' => ($count + 1) . " đơn từ cùng số điện thoại trong {$hours} giờ",
            'points' => (int) config('risk.weights.burst_orders', 20),
        ];
    }

    /** Khách đã nhận hàng thành công nhiều lần thì bớt điểm — đừng bắt khách quen chờ gọi xác nhận. */
    private function trustedCustomer(Order $order): ?array
    {
        $q = $this->cungKhach($order);

        if ($q === null) {
            return null;
        }

        $daNhan = $q->where('status', OrderStatus::Completed->value)->count();

        if ($daNhan < (int) config('risk.thresholds.trusted_from', 2)) {
            return null;
        }

        return [
            'code' => 'trusted',
            'label' => "Đã nhận thành công {$daNhan} đơn trước đây",
            'points' => -1 * (int) config('risk.weights.trusted', 20),
        ];
    }
}
