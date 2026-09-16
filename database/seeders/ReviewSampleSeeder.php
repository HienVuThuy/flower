<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Shipping\ShippingRates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dữ liệu mẫu cho phần đánh giá.
 * ⚠️ DỮ LIỆU MẪU. Tên khách và lời nhận xét là do soạn ra, không phải
 */
class ReviewSampleSeeder extends Seeder
{
    private const CUSTOMERS = [
        ['Lê Thị Mai Anh', 'maianh@khachmau.test'],
        ['Trần Quốc Bảo', 'quocbao@khachmau.test'],
        ['Phạm Thu Hà', 'thuha@khachmau.test'],
        ['Nguyễn Minh Đức', 'minhduc@khachmau.test'],
        ['Vũ Khánh Linh', 'khanhlinh@khachmau.test'],
    ];

    private const COMMENTS = [
        5 => [
            'Hoa tươi, gói kỹ, giao đúng giờ hẹn. Người nhận thích lắm.',
            'Cây khoẻ, đất tơi, có sẵn lỗ thoát nước dưới đáy chậu. Rất ưng.',
            'Đặt buổi sáng chiều nhận được luôn. Hoa nở đẹp suốt 5 ngày.',
            'Lá dày, không có vết sâu. Shop tư vấn nhiệt tình chỗ đặt cây.',
        ],
        4 => [
            'Hoa đẹp nhưng giao muộn hơn hẹn khoảng một tiếng.',
            'Cây tốt, chậu hơi nhỏ so với ảnh. Vẫn đáng tiền.',
            'Đóng gói chắc chắn. Một cành bị gãy nhẹ nhưng không đáng kể.',
            'Ưng ý. Giá hơi cao so với ngoài chợ nhưng bù lại chất lượng ổn định.',
        ],
        3 => [
            'Hoa ổn, không có gì đặc biệt. Được vài ngày là bắt đầu rũ.',
            'Cây sống nhưng lá vàng mất mấy lá lúc mới về. Giờ đã hồi.',
        ],
        2 => [
            'Giao chậm hai ngày, tới nơi hoa đã hơi héo. Shop có xin lỗi.',
        ],
    ];

    public function run(): void
    {
        $products = Product::query()
            ->whereHas('category', fn ($q) => $q->where('kind', 'plant'))
            ->whereNotNull('base_price')
            ->inRandomOrder()
            ->take(14)
            ->get();

        if ($products->isEmpty()) {
            $this->command?->warn('Chưa có sản phẩm nào để tạo đánh giá mẫu.');

            return;
        }

        $customers = collect(self::CUSTOMERS)->map(
            fn (array $row) => $this->customer($row[0], $row[1])
        );

        $soDon = 0;
        $soDanhGia = 0;

        foreach ($products as $i => $product) {
            $soNguoi = max(1, 4 - intdiv($i, 4));

            foreach ($customers->take($soNguoi) as $j => $customer) {
                $rating = $this->ratingFor($i, $j);

                $order = $this->completedOrder($customer, $product, $i, $j);
                $soDon++;

                Review::create([
                    'product_id' => $product->id,
                    'user_id' => $customer->id,
                    'order_id' => $order->id,
                    'rating' => $rating,
                    'comment' => $this->commentFor($rating, $i + $j),
                ]);
                $soDanhGia++;
            }
        }

        $product = $products->first();
        $customer = $customers->first();

        $order = $this->completedOrder($customer, $product, 99, 99);
        Review::create([
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'rating' => 4,
            'comment' => 'Mua lần hai. Lần này cây nhỏ hơn lần trước một chút.',
        ]);
        $soDon++;
        $soDanhGia++;

        $this->command?->info("Đã tạo {$soDon} đơn đã hoàn thành và {$soDanhGia} đánh giá mẫu.");
    }

    private function customer(string $name, string $email): User
    {
        $user = User::firstWhere('email', $email);

        if ($user) {
            return $user;
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => 'MatKhauMau@123',
        ]);

        $user->role = UserRole::Customer;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function completedOrder(User $user, Product $product, int $i, int $j): Order
    {
        $price = (string) $product->base_price;
        $quantity = 1 + ($j % 2);
        $lineTotal = bcmul($price, (string) $quantity, 2);

        $province = ['Thành phố Hà Nội', 'Bắc Ninh', 'Thành phố Hồ Chí Minh'][$j % 3];
        $shipping = (string) app(ShippingRates::class)->feeFor($province);
        $grand = bcadd($lineTotal, $shipping, 2);

        $placedAt = now()->subDays(3 + (($i * 7 + $j * 3) % 87));

        return DB::transaction(function () use (
            $user, $product, $price, $quantity, $lineTotal, $province, $shipping, $grand, $placedAt
        ) {
            $order = Order::create([
                'order_number' => 'FP-MAU-'.strtoupper(bin2hex(random_bytes(3))),
                'user_id' => $user->id,
                'recipient_name' => $user->name,
                'recipient_phone' => '09'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                'recipient_email' => $user->email,
                'shipping_address' => 'Số '.random_int(1, 200).' đường Mẫu',
                'shipping_province' => $province,
                'payment_method' => PaymentMethod::Cod,
                'subtotal' => $lineTotal,
                'discount_total' => '0.00',
                'shipping_fee' => $shipping,
                'coupon_discount' => '0.00',
                'grand_total' => $grand,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->product_code,
                'unit_base_price' => $price,
                'unit_price' => $price,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ]);

            $order->status = OrderStatus::Completed;
            $order->payment_status = PaymentStatus::Paid;
            $order->confirmed_at = $placedAt->copy()->addHours(2);
            $order->completed_at = $placedAt->copy()->addDays(2);
            $order->created_at = $placedAt;
            $order->updated_at = $placedAt->copy()->addDays(2);
            $order->save();

            return $order;
        });
    }

    private function ratingFor(int $i, int $j): int
    {
        return [5, 5, 4, 5, 4, 3, 5, 4, 5, 2, 4, 5][($i * 5 + $j * 3) % 12];
    }

    private function commentFor(int $rating, int $seed): string
    {
        $pool = self::COMMENTS[$rating];

        return $pool[$seed % count($pool)];
    }
}
