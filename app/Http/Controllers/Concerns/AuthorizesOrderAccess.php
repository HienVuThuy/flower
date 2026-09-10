<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Controllers\Shop\CheckoutController;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

trait AuthorizesOrderAccess
{
    /**
     * Chỉ chủ đơn mới xem/thao tác được.
     *
     * Khách vãng lai không có tài khoản, nên cửa thứ hai là mã đơn đã
     * được ghi vào phiên lúc đặt. Thiếu kiểm tra này thì đổi mã trên URL
     * là mở được đơn của người lạ.
     */
    protected function authorizeOrderAccess(Order $order): void
    {
        if (Auth::check() && $order->user_id === Auth::id()) {
            return;
        }

        $placed = session(CheckoutController::PLACED_KEY, []);

        abort_unless(in_array($order->order_number, $placed, strict: true), 403);
    }
}
