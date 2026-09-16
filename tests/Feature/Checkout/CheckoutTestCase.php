<?php

namespace Tests\Feature\Checkout;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Nền chung cho các bài kiểm tra luồng thanh toán. */
abstract class CheckoutTestCase extends TestCase
{
    use RefreshDatabase;

    protected const DETAILS = '/thanh-toan';

    protected const COUPON = '/thanh-toan/ma-giam-gia';

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

    protected function product(string $price = '500000.00'): Product
    {
        return Product::factory()
            ->for(Category::factory())
            ->price($price)
            ->create();
    }

    protected function addToCart(Product $product, int $quantity = 1): void
    {
        $this->post('/gio-hang', [
            'product_id' => $product->id,
            'quantity' => $quantity,
        ])->assertRedirect();
    }

    protected function shopperWithCart(string $price = '500000.00'): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->product($price);
        $this->addToCart($product);

        return [$user, $product];
    }

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

    protected function claimed(Coupon $coupon, User $user): Coupon
    {
        $this->claim($user, $coupon);

        return $coupon;
    }

    protected function cartItemId(Product $product): int
    {
        return (int) DB::table('cart_items')
            ->where('product_id', $product->id)
            ->orderByDesc('id')
            ->value('id');
    }

    protected function appliedCoupon(): ?string
    {
        return session('checkout.coupon');
    }
}
