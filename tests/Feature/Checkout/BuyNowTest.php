<?php

namespace Tests\Feature\Checkout;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/** "Mua ngay" — mua thẳng một món, không đụng vào giỏ. */
class BuyNowTest extends CheckoutTestCase
{
    #[Test]
    public function mua_ngay_khong_dung_vao_gio_hang(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $trongGio = Product::factory()->price('105000.00')->create();
        $this->addToCart($trongGio);

        $muaNgay = Product::factory()->price('520000.00')->create();
        $this->post('/mua-ngay', ['product_id' => $muaNgay->id, 'quantity' => 1])
            ->assertRedirect();

        $this->post(self::DETAILS, $this->details());
        $this->post('/thanh-toan/dat-hang');

        $order = Order::latest('id')->first();

        $this->assertSame($muaNgay->id, $order->items()->first()->product_id);
        $this->assertSame(520000.0, (float) $order->subtotal);

        $this->assertSame(
            1,
            Cart::latest('id')->first()->items()->count(),
            'Giỏ hàng phải còn nguyên để khách quay lại mua tiếp.',
        );
    }

    #[Test]
    public function mo_lai_trang_gio_hang_thi_phien_mua_ngay_bi_go(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $trongGio = Product::factory()->price('105000.00')->create();
        $this->addToCart($trongGio);

        $muaNgay = Product::factory()->price('520000.00')->create();
        $this->post('/mua-ngay', ['product_id' => $muaNgay->id, 'quantity' => 1]);
        $this->assertTrue(session()->has('checkout.direct'));

        $this->get('/gio-hang')
            ->assertRedirect('/gio-hang')
            ->assertSessionHas('info');
        $this->get('/gio-hang')->assertOk();
        $this->assertFalse(
            session()->has('checkout.direct'),
            'Mở lại giỏ hàng là quay về mua từ giỏ — phiên mua ngay phải bị gỡ.',
        );

        $this->post(self::DETAILS, $this->details());
        $this->post('/thanh-toan/dat-hang');

        $this->assertSame(
            105000.0,
            (float) Order::latest('id')->first()->subtotal,
            'Trang thanh toán phải tính đúng con số mà trang giỏ vừa hiện.',
        );
    }
}
