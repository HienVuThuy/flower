<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Checkout\CheckoutBasket;
use App\Services\Checkout\CheckoutLine;
use App\Services\Coupon\CouponException;
use App\Services\Order\OrderException;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Những gì phải đúng khi hai việc xảy ra cùng lúc.
 * ============================================================
 * Các bài ở đây canh một nhóm lỗi có chung một dạng: MỘT PHÉP KIỂM VÀ
 * MỘT PHÉP GHI BỊ TÁCH RỜI NHAU. Giữa hai bước đó, thế giới đổi — người
 * khác lấy mất lượt mã cuối, admin tắt sản phẩm, một request song song
 * huỷ trước cùng một đơn.
 *
 * VÌ SAO KHÔNG DỰNG HAI TIẾN TRÌNH THẬT: PHPUnit chạy một luồng, không
 * mô phỏng được hai request đồng thời. Nên mỗi bài ở đây DỰNG SẴN đúng
 * cái trạng thái mà request thứ hai sẽ nhìn thấy, rồi hỏi: đoạn mã có
 * kiểm lại không, hay vẫn tin vào thứ nó đọc từ trước?
 *
 * Đó chính là điều cần canh. Bản thân khoá hàng của cơ sở dữ liệu thì
 * không cần kiểm — MariaDB lo phần đó. Cái dễ mất là PHÉP KIỂM LẠI SAU
 * KHI KHOÁ, vì nhìn qua nó giống hệt một dòng thừa.
 */
class TransactionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function orders(): OrderService
    {
        return app(OrderService::class);
    }

    private function product(string $price = '300000.00'): Product
    {
        return Product::factory()
            ->for(Category::factory())
            ->price($price)
            ->stock(50)
            ->create();
    }

    /** Đặt một đơn qua đúng đường khách vẫn đi, trả về đơn vừa tạo. */
    private function placeOrder(Product $product, ?string $couponCode = null): Order
    {
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect();

        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com',
            'shipping_address' => '12 Đường Thử Nghiệm',
            'shipping_ward' => 'Phường 1',
            'shipping_district' => 'Quận 3',
            'shipping_province' => 'Thành phố Hồ Chí Minh',
            'payment_method' => 'cod',
            'address_id' => '',
            'coupon_code' => $couponCode ?? '',
        ])->assertRedirect();

        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    // ================================================================
    // Hàng vừa ngừng bán trong lúc còn nằm trong giỏ
    // ================================================================

    #[Test]
    public function khong_dat_duoc_hang_vua_bi_tat_sau_khi_da_vao_gio(): void
    {
        /*
         * Kịch bản thật: khách để giỏ hàng mở từ trưa tới tối. Chiều đó
         * admin chuyển sản phẩm sang "nháp" vì hết mùa. Phép kiểm lúc
         * THÊM VÀO GIỎ đã chạy xong từ trưa và không biết gì về việc này.
         */
        $this->actingAs(User::factory()->create());

        $product = $this->product();
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $product->status = 'draft';
        $product->save();

        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com',
            'shipping_address' => '12 Đường Thử Nghiệm',
            'shipping_province' => 'Thành phố Hồ Chí Minh',
            'payment_method' => 'cod',
            'address_id' => '',
        ]);

        $this->post('/thanh-toan/dat-hang')
            ->assertRedirect('/gio-hang');

        $this->assertSame(0, Order::count(), 'Không được tạo đơn cho hàng đã ngừng bán.');

        // Và kho không bị trừ cho một đơn không tồn tại.
        $this->assertSame(50, $product->fresh()->stock_quantity);
    }

    // ================================================================
    // Mã giảm giá: không vượt được số lượt cho phép
    // ================================================================

    /*
     * VÌ SAO HAI BÀI NÀY GỌI THẲNG SERVICE, KHÔNG QUA HTTP.
     *
     * Đi qua HTTP thì không tới được chỗ cần canh: giỏ hàng được dựng
     * lại ở MỖI request, và lúc dựng lại nó tự bỏ mã không còn dùng
     * được. Nên một mã đã hết lượt từ trước sẽ biến mất khỏi giỏ trước
     * khi tới lượt đặt hàng — bài kiểm tra xanh mà chẳng chứng minh gì.
     *
     * Khe hở thật sự hẹp hơn thế: mã CÒN dùng được lúc dựng giỏ, và bị
     * người khác lấy nốt lượt cuối trong vài phần nghìn giây tới lúc
     * ghi đơn. Một tiến trình không dựng lại được cảnh đó, nên ở đây
     * dựng thẳng cái giỏ mang mã, rồi làm mã hết lượt — đúng bằng thứ
     * mà request song song sẽ nhìn thấy.
     */

    private function basketWithCoupon(Product $product, Coupon $coupon): CheckoutBasket
    {
        return new CheckoutBasket(
            lines: collect([new CheckoutLine($product, null, 1)]),
            source: 'cart',
            coupon: $coupon,
            province: 'Thành phố Hồ Chí Minh',
        );
    }

    private function checkoutData(): array
    {
        return [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com',
            'shipping_address' => '12 Đường Thử Nghiệm',
            'shipping_province' => 'Thành phố Hồ Chí Minh',
            'payment_method' => 'cod',
        ];
    }

    #[Test]
    public function bo_dem_ma_khong_vuot_qua_gioi_han(): void
    {
        $this->actingAs(User::factory()->create());

        $coupon = Coupon::factory()->create([
            'code' => 'SAPHET',
            'usage_limit' => 5,
            'used_count' => 4,
        ]);

        // Lượt cuối: phải ghi được.
        $product = $this->product();
        $this->orders()->place(
            $this->basketWithCoupon($product, $coupon),
            $this->checkoutData(),
        );
        $this->assertSame(5, $coupon->fresh()->used_count);

        // Lượt thứ sáu: phải bị từ chối, bộ đếm đứng yên ở 5.
        try {
            $this->orders()->place(
                $this->basketWithCoupon($this->product(), $coupon->fresh()),
                $this->checkoutData(),
            );
            $this->fail('Mã đã hết lượt mà vẫn ghi nhận được.');
        } catch (CouponException) {
            // Đúng như mong đợi.
        }

        $this->assertSame(5, $coupon->fresh()->used_count,
            'Bộ đếm không được vượt quá usage_limit.');
    }

    #[Test]
    public function don_bi_cuon_lai_khi_ghi_luot_ma_that_bai(): void
    {
        /*
         * ĐIỀU QUAN TRỌNG NHẤT trong nhóm này: ghi lượt mã và tạo đơn
         * phải CÙNG SỐNG HOẶC CÙNG CHẾT.
         *
         * Trước khi sửa, redeem() chạy ở controller SAU khi transaction
         * tạo đơn đã commit — nên khi nó ngã, đơn vẫn còn, đã giảm giá,
         * mà bộ đếm mã đứng yên. Đó là đường để dùng một mã "giới hạn 5
         * lượt" không giới hạn số lần.
         */
        $this->actingAs(User::factory()->create());

        $coupon = Coupon::factory()->create([
            'code' => 'HETSACH',
            'usage_limit' => 3,
            'used_count' => 3,
        ]);

        $product = $this->product();

        try {
            $this->orders()->place(
                $this->basketWithCoupon($product, $coupon),
                $this->checkoutData(),
            );
            $this->fail('Đáng lẽ phải ném ngoại lệ vì mã đã hết lượt.');
        } catch (CouponException) {
            // Đúng như mong đợi.
        }

        $this->assertSame(0, Order::count(),
            'Ghi lượt mã hỏng thì đơn phải bị cuộn lại, không được để lại đơn đã giảm giá.');
        $this->assertSame(50, $product->fresh()->stock_quantity,
            'Đơn bị cuộn lại thì kho cũng phải trở về như cũ.');
    }

    // ================================================================
    // Huỷ đơn hai lần: kho chỉ được hoàn một lần
    // ================================================================

    #[Test]
    public function huy_don_lan_thu_hai_bi_tu_choi_va_kho_chi_hoan_mot_lan(): void
    {
        $this->actingAs(User::factory()->create());

        $product = $this->product();
        $order = $this->placeOrder($product);

        $this->assertSame(49, $product->fresh()->stock_quantity);

        $this->orders()->changeStatus($order, OrderStatus::Cancelled, 'Khách đổi ý');
        $this->assertSame(50, $product->fresh()->stock_quantity, 'Huỷ lần đầu phải hoàn kho.');

        /*
         * Đối tượng $order ở đây vẫn giữ trạng thái CŨ trong bộ nhớ —
         * đúng như một request song song đã đọc đơn trước khi request
         * kia kịp huỷ. Nếu changeStatus() tin vào bản sao này, nó sẽ
         * hoàn kho lần nữa và kho thành 51.
         */
        $orderCu = new Order();
        $orderCu->setRawAttributes($order->getRawOriginal(), sync: true);
        $orderCu->exists = true;
        $orderCu->status = OrderStatus::Pending;

        try {
            $this->orders()->changeStatus($orderCu, OrderStatus::Cancelled, 'Bấm lần hai');
            $this->fail('Huỷ lần hai đáng lẽ phải bị từ chối.');
        } catch (OrderException) {
            // Đúng như mong đợi.
        }

        $this->assertSame(50, $product->fresh()->stock_quantity,
            'Kho không được cộng lại hai lần cho cùng một đơn.');
    }

    // ================================================================
    // Trạng thái thanh toán phải hợp với trạng thái đơn
    // ================================================================

    #[Test]
    public function khong_hoan_tien_cho_don_chua_huy(): void
    {
        $this->actingAs(User::factory()->create());

        $order = $this->placeOrder($this->product());
        $this->orders()->setPaymentStatus($order, PaymentStatus::Paid);
        foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Shipping] as $buoc) {
            $this->orders()->changeStatus($order->fresh(), $buoc);
        }

        $this->expectException(OrderException::class);
        $this->orders()->setPaymentStatus($order->fresh(), PaymentStatus::Refunded);
    }

    #[Test]
    public function hoan_tien_duoc_khi_don_da_huy(): void
    {
        // Mặt còn lại của bài trên: luật mới không được chặn nhầm đường
        // hoàn tiền hợp lệ — đó mới là đường admin thật sự cần dùng.
        $this->actingAs(User::factory()->create());

        $order = $this->placeOrder($this->product());
        $this->orders()->setPaymentStatus($order, PaymentStatus::Paid);
        $this->orders()->changeStatus($order->fresh(), OrderStatus::Cancelled, 'Khách đổi ý');

        $this->orders()->setPaymentStatus($order->fresh(), PaymentStatus::Refunded);

        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);
    }

    #[Test]
    public function khong_go_danh_dau_thanh_toan_cua_don_da_giao_xong(): void
    {
        $this->actingAs(User::factory()->create());

        $order = $this->placeOrder($this->product());
        $this->orders()->setPaymentStatus($order, PaymentStatus::Paid);

        foreach ([OrderStatus::Confirmed, OrderStatus::Preparing,
                  OrderStatus::Shipping, OrderStatus::Completed] as $buoc) {
            $this->orders()->changeStatus($order->fresh(), $buoc);
        }

        $this->expectException(OrderException::class);
        $this->orders()->setPaymentStatus($order->fresh(), PaymentStatus::Unpaid);
    }
}
