<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Shipping\GHNOrderService;
use Illuminate\Support\Facades\Log;

/** Việc phải làm ngay khi đơn đã được trả ĐỦ tiền: xác nhận, rồi bàn giao GHN. */
class PaidOrderFulfilment
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly GHNOrderService $ghn,
    ) {
    }

    public function sauKhiTraTien(Order $order, string $ghiChu): void
    {
        if ($order->status === OrderStatus::Cancelled) {
            return;
        }

        if ($order->choDoiTraGop()) {
            return;
        }

        if ($order->status === OrderStatus::Pending) {
            try {
                $this->orders->changeStatus($order, OrderStatus::Confirmed, $ghiChu, tuDong: true);
            } catch (OrderException $e) {
                Log::warning('Không tự xác nhận được đơn sau thanh toán.', [
                    'order' => $order->order_number,
                    'ly_do' => $e->getMessage(),
                ]);
            }
        }

        try {
            $ketQua = $this->ghn->create($order->refresh());

            if (($ketQua['code'] ?? null) !== 200) {
                Log::error('Không tạo được vận đơn GHN sau thanh toán.', [
                    'order' => $order->order_number,
                    'ghn' => $ketQua['message'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Lỗi khi tạo vận đơn GHN sau thanh toán.', [
                'order' => $order->order_number,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
