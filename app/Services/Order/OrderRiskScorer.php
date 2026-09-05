<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;

/**
 * Chấm điểm rủi ro cho một đơn hàng vừa đặt.
 * ============================================================
 * TRẢ LỜI ĐÚNG MỘT CÂU: "đơn này có đáng gọi xác nhận trước khi cắt hoa
 * không?"
 *
 * KHÔNG BAO GIỜ TỰ CHẶN ĐƠN. Lớp này chỉ ghi điểm và liệt kê dấu hiệu;
 * quyết định là của người. Xem migration 2026_09_06_010000 để biết vì
 * sao đó là ranh giới không được vượt.
 *
 * MỌI DẤU HIỆU ĐỀU TỪ DỮ LIỆU CÓ THẬT TRONG HỆ THỐNG NÀY — lịch sử huỷ
 * đơn, giá trị đơn, hình thức thanh toán, số đơn gần đây. Không có dấu
 * hiệu nào cần dịch vụ bên ngoài, không có dấu hiệu nào đoán mò về con
 * người (tuổi, giới tính, khu vực).
 *
 * TÍNH MỘT LẦN LÚC ĐẶT rồi chụp vào đơn. Tính lại khi xem sẽ cho con số
 * khác sau vài tháng, và không ai đối chiếu được với quyết định đã làm.
 */
class OrderRiskScorer
{
    /**
     * @return array{score: int, flags: list<array{code: string, label: string, points: int}>}
     */
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

        // Kẹp ở 100: điểm là thang để so sánh giữa các đơn, không phải
        // tổng cộng dồn vô hạn. Ba đơn 140/180/250 điểm thì con số không
        // còn nói lên điều gì.
        $score = min(100, array_sum(array_column($flags, 'points')));

        return ['score' => $score, 'flags' => $flags];
    }

    /** Ghi điểm vào đơn. Gọi SAU khi đơn đã tạo xong. */
    public function apply(Order $order): void
    {
        $result = $this->score($order);

        /*
         * forceFill vì risk_score/risk_flags cố ý nằm ngoài $fillable:
         * chúng là kết luận của hệ thống, không phải dữ liệu người dùng
         * nhập. Để trong fillable là mở đường cho một request tự khai
         * mình "0 điểm rủi ro".
         */
        $order->forceFill([
            'risk_score' => $result['score'],
            'risk_flags' => $result['flags'] ?: null,
        ])->save();
    }

    /* ================= TỪNG DẤU HIỆU ================= */

    /**
     * Người này (hoặc số điện thoại này) từng huỷ đơn.
     *
     * DẤU HIỆU MẠNH NHẤT vì nó dựa trên hành vi ĐÃ XẢY RA THẬT của chính
     * họ, không phải suy đoán từ đặc điểm chung.
     *
     * Tra theo user_id NẾU có, nếu không thì theo số điện thoại: khách
     * vãng lai không có tài khoản, nhưng số điện thoại vẫn là thứ họ phải
     * dùng lại để nhận hàng.
     */
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

    /**
     * Đơn COD giá trị lớn.
     *
     * Chỉ tính với COD: chuyển khoản trước thì tiền đã về, khách không
     * nhận cũng không mất giá vốn. Đây là lý do dấu hiệu này gắn với HÌNH
     * THỨC THANH TOÁN chứ không chỉ với số tiền.
     */
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

    /**
     * Nhiều đơn từ cùng số điện thoại trong thời gian ngắn.
     *
     * KHÔNG phải lúc nào cũng xấu — có người đặt ba đơn giao ba nơi khác
     * nhau trong một dịp lễ, hoàn toàn thật. Vì thế đây chỉ là một dấu
     * hiệu cộng điểm, không phải căn cứ để chặn.
     */
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

        // +1 tính cả đơn hiện tại.
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
