<?php

namespace App\Services\Order;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Sinh mã đơn hàng dạng FP-260823-A7K2.
 *
 * KHÔNG dùng id tự tăng làm mã công khai: nó để lộ tổng số đơn cửa
 * hàng đã bán và cho phép người ngoài dò đơn của người khác bằng cách
 * đếm lên. Phần ngẫu nhiên ở cuối chặn việc đó.
 */
class OrderNumberGenerator
{
    public function generate(): string
    {
        // Vòng lặp phòng trường hợp trùng — xác suất rất thấp nhưng
        // cột order_number là UNIQUE nên phải xử lý cho chắc.
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
