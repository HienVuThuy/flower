<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Controllers\Shop\CheckoutController;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

trait AuthorizesOrderAccess
{
    protected function authorizeOrderAccess(Order $order): void
    {
        if (Auth::check() && $order->user_id === Auth::id()) {
            return;
        }

        $placed = session(CheckoutController::PLACED_KEY, []);

        abort_unless(in_array($order->order_number, $placed, strict: true), 403);
    }
}
