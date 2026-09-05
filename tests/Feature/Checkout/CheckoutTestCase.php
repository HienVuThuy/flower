<?php

namespace Tests\Feature\Checkout;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nền chung cho các bài kiểm tra luồng thanh toán.
 * ============================================================
 * VÌ SAO TÁCH RA: mọi bài đều cần đúng ba việc mở đầu — có hàng trong
 * giỏ, có một bộ thông tin người nhận hợp lệ, và biết mã nào đang được
 * áp. Chép ba đoạn đó vào từng bài thì bài kiểm tra dài gấp đôi phần
 * thật sự đang được kiểm, và người đọc phải lọc mới thấy điều đang được
 * khẳng định.
 *
 * ĐI QUA HTTP, KHÔNG GỌI THẲNG SERVICE. Ba lỗi gần đây đều nằm ở chỗ
 * ghép nối — biểu mẫu gửi đi đâu, session còn gì sau khi chuyển hướng,
 * trang hiện ra cái gì — chứ không nằm trong phép tính. Gọi thẳng service
 * thì cả ba lỗi đó vẫn xanh.
 */
abstract class CheckoutTestCase extends TestCase
{
    use RefreshDatabase;

    protected const DETAILS = '/thanh-toan';

    protected const COUPON = '/thanh-toan/ma-giam-gia';

    /** Bộ thông tin người nhận hợp lệ, đủ để qua bước 1. */
    protected function details(array $overrides = []): array
    {
        return array_merge([
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com',
            'shipping_address' => '12 Đường Thử Nghiệm',
            'shipping_ward' => 'Phường 1',
            'shipping_district' => 'Quận 3',
            'shipping_province' => 'Thành phố Hồ Chí Minh',
            'payment_method' => 'cod',
            'address_id' => '',
        ], $overrides);
    }

    /** Một sản phẩm còn hàng, giá đặt được. */
    protected function product(string $price = '500000.00'): Product
    {
        return Product::factory()
            ->for(Category::factory())
            ->price($price)
            ->create();
    }

    /** Bỏ một sản phẩm vào giỏ qua đúng đường khách vẫn đi. */
    protected function addToCart(Product $product, int $quantity = 1): void
    {
        $this->post('/gio-hang', [
            'product_id' => $product->id,
            'quantity' => $quantity,
        ])->assertRedirect();
    }

    /** Khách đã đăng nhập, giỏ có sẵn một món. */
    protected function shopperWithCart(string $price = '500000.00'): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->product($price);
        $this->addToCart($product);

        return [$user, $product];
    }

    /**
     * Lưu một mã về ví của khách.
     *
     * ĐI THẲNG VÀO BẢNG chứ không qua trang Voucher: các bài ở đây đang
     * canh bước THANH TOÁN, nên dựng sẵn tình huống là đủ. Bản thân nút
     * "Lưu mã" có bài riêng của nó.
     */
    protected function claim(User $user, Coupon $coupon): void
    {
        DB::table('coupon_user')->insert([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'claimed_at' => now(),
            'used_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Tạo một mã rồi lưu luôn vào ví — tình huống hay dùng nhất. */
    protected function claimed(Coupon $coupon, User $user): Coupon
    {
        $this->claim($user, $coupon);

        return $coupon;
    }

    /**
     * Id dòng giỏ hàng của một sản phẩm.
     *
     * Cần cho các bài phải SỬA giỏ sau khi đã thêm — route sửa và xoá
     * nhận id của DÒNG GIỎ, không phải id sản phẩm.
     */
    protected function cartItemId(Product $product): int
    {
        return (int) DB::table('cart_items')
            ->where('product_id', $product->id)
            ->orderByDesc('id')
            ->value('id');
    }

    /**
     * Mã đang được áp cho lần thanh toán này, đọc từ session.
     *
     * Đọc SESSION chứ không dò chuỗi trong HTML: bài kiểm tra không được
     * hỏng chỉ vì ai đó đổi tên một lớp CSS.
     */
    protected function appliedCoupon(): ?string
    {
        return session('checkout.coupon');
    }
}
