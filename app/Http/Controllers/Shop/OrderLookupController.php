<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\OrderLookupRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** TRA CỨU ĐƠN CHO KHÁCH VÃNG LAI (Guide §XI). */
class OrderLookupController extends Controller
{
    private const MAX_REMEMBERED = 20;

    public function form(): View
    {
        return view('shop.orders.lookup');
    }

    public function find(OrderLookupRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $order = $this->match($data['order_number'], $data['contact']);

        if (! $order) {
            return back()
                ->withInput($request->only('order_number'))
                ->withErrors([
                    'order_number' => 'Không tìm thấy đơn hàng khớp với thông tin bạn nhập. '
                        . 'Vui lòng kiểm tra lại mã đơn và số điện thoại đã dùng khi đặt.',
                ]);
        }

        $this->remember($order);

        return redirect()->route('shop.orders.show', $order);
    }

    private function match(string $orderNumber, string $contact): ?Order
    {
        $digits = preg_replace('/\D/', '', $contact);

        $candidates = [];

        if ($digits !== '') {
            $candidates[] = $digits;

            if (str_starts_with($digits, '84')) {
                $candidates[] = '0' . substr($digits, 2);
            }
        }

        $email = str_contains($contact, '@') ? $contact : null;

        if (! $candidates && ! $email) {
            return null;
        }

        return Order::query()
            ->where('order_number', $orderNumber)
            ->where(function ($q) use ($candidates, $email) {
                if ($candidates) {
                    $q->orWhereIn('recipient_phone', $candidates);
                }

                if ($email) {
                    $q->orWhere('recipient_email', $email);
                }
            })
            ->first();
    }

    private function remember(Order $order): void
    {
        if (Auth::check() && $order->user_id === Auth::id()) {
            return;
        }

        $placed = session(CheckoutController::PLACED_KEY, []);

        if (! in_array($order->order_number, $placed, strict: true)) {
            $placed[] = $order->order_number;
        }

        session([
            CheckoutController::PLACED_KEY => array_slice($placed, -self::MAX_REMEMBERED),
        ]);
    }
}
