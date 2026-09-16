<?php

namespace App\Services\Shipping;

use App\Enums\GhnFeePayer;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/** Tạo vận đơn GHN từ một đơn hàng của hệ thống. */
class GHNOrderService
{
    public function __construct(
        private readonly GHNService $ghn,
    ) {
    }

    public function create(Order $order): array
    {
        if ($order->ghn_order_code) {
            return [
                'code' => 200,
                'message' => 'Đơn này đã có mã vận đơn '.$order->ghn_order_code.'.',
                'data' => ['order_code' => $order->ghn_order_code],
                'da_co_san' => true,
            ];
        }

        if (! in_array($order->status, [\App\Enums\OrderStatus::Confirmed, \App\Enums\OrderStatus::Preparing], true)) {
            return [
                'code' => -1,
                'message' => sprintf(
                    'Chỉ tạo vận đơn cho đơn "Đã xác nhận" hoặc "Đang chuẩn bị". Đơn này đang "%s".',
                    $order->status->label(),
                ),
            ];
        }

        if ($order->choDoiTraGop()) {
            return [
                'code' => -1,
                'message' => 'Đơn trả góp chưa trả đủ các kỳ nên chưa tạo vận đơn được.',
            ];
        }

        if (! $order->to_district_id || ! $order->to_ward_code) {
            return [
                'code' => -1,
                'message' => 'Đơn này chưa có mã quận/phường của GHN nên không tạo vận đơn được. '
                    .'Đơn đặt trước khi bật tính phí GHN sẽ rơi vào trường hợp này.',
            ];
        }

        $order->loadMissing('items.product');

        $items = [];
        $weight = 0;

        foreach ($order->items as $item) {
            $itemWeight = $item->product
                ? $item->product->shippingWeight($item->variant)
                : (int) config('services.ghn.default_weight', 200);

            $weight += $itemWeight * (int) $item->quantity;

            $items[] = [
                'name' => $item->product_name ?: 'Sản phẩm',
                'quantity' => (int) $item->quantity,
                'price' => (int) round((float) $item->unit_price),
                'weight' => $itemWeight,
            ];
        }

        $nguoiTra = GhnFeePayer::Shop;

        $response = $this->ghn->createOrder(array_merge([
            'payment_type_id' => $nguoiTra->ghnCode(),
            'note' => 'Đơn hàng '.$order->order_number,

            'required_note' => 'KHONGCHOXEMHANG',

            'to_name' => $order->recipient_name,
            'to_phone' => $order->recipient_phone,
            'to_address' => $order->shipping_address,
            'to_ward_code' => (string) $order->to_ward_code,
            'to_district_id' => (int) $order->to_district_id,

            'cod_amount' => $order->payment_status === PaymentStatus::Paid
                ? 0
                : (int) round((float) $order->grand_total),

            'items' => $items,
        ], $this->ghn->packageParameters($weight)));

        if (($response['code'] ?? null) === 200 && ! empty($response['data']['order_code'])) {
            $order->ghn_order_code = $response['data']['order_code'];

            $cuoc = $response['data']['total_fee'] ?? null;
            $order->ghn_total_fee = is_numeric($cuoc) ? (int) $cuoc : null;

            $order->ghn_fee_payer = $nguoiTra;
            $order->shipping_status = 'ready_to_pick';
            $order->save();

            Log::info('Đã tạo vận đơn GHN.', [
                'order_number' => $order->order_number,
                'ghn_order_code' => $order->ghn_order_code,
            ]);
        }

        return $response;
    }

    public function cancel(Order $order): array
    {
        if (! $order->ghn_order_code) {
            return ['code' => -1, 'message' => 'Đơn này chưa có vận đơn GHN để huỷ.'];
        }

        $response = $this->ghn->cancelOrder([$order->ghn_order_code]);

        if (($response['code'] ?? null) === 200) {
            $order->shipping_status = 'cancel';
            $order->save();

            Log::info('Đã huỷ vận đơn GHN.', [
                'order_number' => $order->order_number,
                'ghn_order_code' => $order->ghn_order_code,
            ]);
        }

        return $response;
    }
}
