<?php

namespace App\Services\Checkout;

use App\Models\Order;

/** Chống đặt trùng đơn hàng. */
class CheckoutGuard
{
    public const KEY = 'checkout.idempotency';

    public function key(): string
    {
        $key = session(self::KEY);

        if (! is_string($key) || $key === '') {
            $key = bin2hex(random_bytes(32));
            session([self::KEY => $key]);
        }

        return $key;
    }

    public function reset(): void
    {
        session()->forget(self::KEY);
    }

    public function existingOrder(): ?Order
    {
        $key = session(self::KEY);

        if (! is_string($key) || $key === '') {
            return null;
        }

        return Order::where('idempotency_key', $key)->first();
    }

    public function orderFor(string $key): ?Order
    {
        return Order::where('idempotency_key', $key)->first();
    }
}
