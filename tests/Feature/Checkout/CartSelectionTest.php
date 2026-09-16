<?php

namespace Tests\Feature\Checkout;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/** Chọn riêng từng món trong giỏ để thanh toán. */
class CartSelectionTest extends CheckoutTestCase
{
    private const SELECT = '/gio-hang/chon';

    #[Test]
    public function chi_thanh_toan_mon_da_tich_va_giu_lai_mon_chua_chon(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $muaNgay = Product::factory()->price('300000.00')->create();
        $deDanh = Product::factory()->price('900000.00')->create();
        $this->addToCart($muaNgay);
        $this->addToCart($deDanh);

        $cart = Cart::latest('id')->first();
        $dongMuaNgay = $cart->items()->where('product_id', $muaNgay->id)->first();

        $this->post(self::SELECT, ['selected' => [$dongMuaNgay->id]])->assertRedirect();

        $this->post(self::DETAILS, $this->details())->assertRedirect('/thanh-toan/xac-nhan');
        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        $order = Order::latest('id')->first();

        $this->assertSame(1, $order->items()->count(), 'Đơn chỉ được gồm món đã tích.');
        $this->assertSame($muaNgay->id, $order->items()->first()->product_id);

        $conLai = $cart->fresh()->items;
        $this->assertCount(1, $conLai, 'Món chưa chọn phải còn nguyên trong giỏ sau khi đặt đơn.');
        $this->assertSame($deDanh->id, $conLai->first()->product_id);
    }

    #[Test]
    public function tong_tien_chi_tinh_tren_mon_da_tich(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $re = Product::factory()->price('300000.00')->create();
        $dat = Product::factory()->price('900000.00')->create();
        $this->addToCart($re);
        $this->addToCart($dat);

        $cart = Cart::latest('id')->first();
        $dongRe = $cart->items()->where('product_id', $re->id)->first();

        $this->post(self::SELECT, ['selected' => [$dongRe->id]]);
        $this->post(self::DETAILS, $this->details());
        $this->post('/thanh-toan/dat-hang');

        $this->assertSame(300000.0, (float) Order::latest('id')->first()->subtotal);
    }

    #[Test]
    public function khong_tich_mon_nao_thi_khong_vao_duoc_trang_thanh_toan(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->addToCart(Product::factory()->price('300000.00')->create());

        $this->post(self::SELECT, ['selected' => []]);

        $this->get(self::DETAILS)->assertRedirect('/gio-hang');
        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function id_la_gui_len_khong_cham_duoc_gio_cua_nguoi_khac(): void
    {
        $nanNhan = User::factory()->create();
        $this->actingAs($nanNhan);
        $this->addToCart(Product::factory()->create());
        $dongCuaNanNhan = Cart::latest('id')->first()->items()->first();

        $keTanCong = User::factory()->create();
        $this->actingAs($keTanCong);
        $this->addToCart(Product::factory()->create());
        $dongCuaKeTanCong = Cart::latest('id')->first()->items()->first();

        $this->post(self::SELECT, ['selected' => [$dongCuaNanNhan->id]]);

        $this->assertTrue(
            (bool) $dongCuaNanNhan->fresh()->is_selected,
            'Dòng giỏ hàng của người khác không được đụng tới.',
        );
        $this->assertFalse(
            (bool) $dongCuaKeTanCong->fresh()->is_selected,
            'Chỉ giỏ của chính người thao tác mới bị đổi.',
        );
    }

    #[Test]
    public function id_khong_ton_tai_khong_gay_loi(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->addToCart(Product::factory()->create());

        $this->post(self::SELECT, ['selected' => [999999]])->assertRedirect();
    }
}
