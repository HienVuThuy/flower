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
 * ============================================================
 * ⚠️ DỮ LIỆU MẪU. Tên khách và lời nhận xét là do soạn ra, không phải
 * khách thật. Cửa hàng thật PHẢI xoá seeder này trước khi bán.
 *
 * VÌ SAO PHẢI TẠO CẢ ĐƠN HÀNG, không chèn thẳng vào bảng `reviews`:
 *
 * Đánh giá trong hệ thống này gắn với MỘT ĐƠN CỤ THỂ (`order_id`), và
 * chỉ viết được khi đơn đã ở trạng thái "Hoàn thành". Chèn thẳng review
 * với order_id = null là tạo ra thứ mà giao diện không bao giờ sinh ra
 * được — dữ liệu mẫu kiểu đó che mất chính cái ràng buộc đang cần thử.
 *
 * Phần thưởng kèm theo: trang thống kê của quản trị viên cũng có số để
 * hiện, thay vì "chưa có dữ liệu".
 *
 * LOGIC ĐIỂM SAO — đã kiểm bằng thực nghiệm:
 *   Khách mua lần 1, chấm 5 sao  -> điểm sản phẩm = 5.0 (1 đánh giá)
 *   CHÍNH khách đó mua lần 2, chấm 4 sao -> điểm = 4.5 (2 đánh giá)
 * Điểm là TRUNG BÌNH của mọi đánh giá đang hiển thị, KHÔNG phải điểm của
 * lần chấm gần nhất. Một khách mua nhiều lần thì có nhiều tiếng nói —
 * đúng như vậy, vì mỗi lần mua là một trải nghiệm riêng.
 */
class ReviewSampleSeeder extends Seeder
{
    /** Khách mẫu: tên + email. Email dùng tên miền .test, không gửi được thật. */
    private const CUSTOMERS = [
        ['Lê Thị Mai Anh', 'maianh@khachmau.test'],
        ['Trần Quốc Bảo', 'quocbao@khachmau.test'],
        ['Phạm Thu Hà', 'thuha@khachmau.test'],
        ['Nguyễn Minh Đức', 'minhduc@khachmau.test'],
        ['Vũ Khánh Linh', 'khanhlinh@khachmau.test'],
    ];

    /**
     * Nhận xét theo mức sao.
     *
     * VIẾT NHƯ NGƯỜI THẬT VIẾT: có chỗ cụ thể (bao lâu thì nở, chậu có
     * lỗ thoát nước không), có chỗ chê. Một trang toàn 5 sao với lời khen
     * chung chung trông giả hơn là không có đánh giá nào.
     */
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
        /*
         * Chỉ đánh giá cây và hoa, KHÔNG đánh giá phụ kiện.
         *
         * Không phải vì phụ kiện không đáng đánh giá, mà vì dữ liệu mẫu
         * nên giống cách khách thật dùng trang: người ta mua chậu kèm
         * cây và nhận xét về cái cây.
         */
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
            /*
             * Sản phẩm đầu danh sách có nhiều đánh giá hơn — giống thật:
             * hàng bán chạy thì nhiều người nói về nó.
             */
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

        /*
         * MỘT KHÁCH MUA HAI LẦN cùng một sản phẩm, chấm hai mức khác nhau.
         *
         * Đây chính là tình huống hay bị làm sai: điểm phải là TRUNG BÌNH
         * của hai lần, không phải điểm lần sau đè lên lần trước. Có sẵn
         * trong dữ liệu mẫu thì lỗi đó lộ ra ngay khi nhìn trang.
         */
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

    /** Tài khoản khách mẫu — đã xác thực email để dùng được mọi trang. */
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

        // role và email_verified_at nằm ngoài $fillable — gán trực tiếp.
        $user->role = UserRole::Customer;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /**
     * Một đơn đã hoàn thành, có thật trong bảng `orders` và `order_items`.
     *
     * Tính tiền bằng bcmath như OrderService, không dùng số thực: sai một
     * đồng trong dữ liệu mẫu là trang thống kê hiện một con số không cộng
     * lại được, và người đọc sẽ nghi ngờ cả những số đúng.
     */
    private function completedOrder(User $user, Product $product, int $i, int $j): Order
    {
        $price = (string) $product->base_price;
        $quantity = 1 + ($j % 2);
        $lineTotal = bcmul($price, (string) $quantity, 2);

        $province = ['Thành phố Hà Nội', 'Bắc Ninh', 'Thành phố Hồ Chí Minh'][$j % 3];
        $shipping = (string) app(ShippingRates::class)->feeFor($province);
        $grand = bcadd($lineTotal, $shipping, 2);

        // Rải đơn ra trong 90 ngày gần đây để biểu đồ doanh thu có hình.
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

            /*
             * status và payment_status nằm NGOÀI $fillable — đó là chủ ý
             * để không request nào ghi được vào. Seeder gán trực tiếp,
             * đúng cách OrderService làm.
             */
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

    /** Phân bố sao lệch về phía tốt, nhưng KHÔNG toàn 5 sao. */
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
