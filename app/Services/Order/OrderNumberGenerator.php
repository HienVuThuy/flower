<?php

namespace App\Services\Order;

use App\Models\Order;
use Illuminate\Support\Str;

/** Sinh mã đơn hàng dạng FP-260823-A7K2. */
class OrderNumberGenerator
{
    public function generate(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $number = sprintf(
                'FP-%s-%s',
                now()->format('ymd'),
                Str::upper(Str::random(4)),
            );

            if (! Order::withTrashed()->where('order_number', $number)->exists()) {
                return $number;
            }
        }

        throw new OrderException('Không sinh được mã đơn hàng, vui lòng thử lại.');
    }
}
