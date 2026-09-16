<?php

namespace Tests\Feature\Checkout;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use PHPUnit\Framework\Attributes\Test;

/** Đi hết luồng: điền thông tin → xác nhận → đặt hàng. */
class PlaceOrderTest extends CheckoutTestCase
{
    private function placeOrder(array $overrides = []): Order
    {
        $this->post(self::DETAILS, $this->details($overrides))
            ->assertRedirect('/thanh-toan/xac-nhan');

        $this->get('/thanh-toan/xac-nhan')->assertOk();
        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        $order = Order::latest('id')->first();
        $this->assertNotNull($order, 'Phải có đơn hàng được ghi.');

        return $order;
    }

    #[Test]
    public function dat_duoc_don_va_tru_dung_ton_kho(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->price('500000.00')->stock(10)->create();
        $this->addToCart($product, 2);

        $order = $this->placeOrder();

        $this->assertSame(1, $order->items()->count());
        $this->assertSame(2, $order->items()->first()->quantity);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function hang_lam_theo_don_khong_bi_tru_ton_kho(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->price('500000.00')->madeToOrder()->create();
        $this->addToCart($product, 3);

        $order = $this->placeOrder();

        $this->assertSame(3, $order->items()->first()->quantity);
        $this->assertSame(0, $product->fresh()->stock_quantity, 'Không được trừ vào một con số vô nghĩa.');
        $this->assertFalse($product->fresh()->track_inventory);
    }

    #[Test]
    public function tong_don_bang_tien_hang_tru_giam_gia_cong_phi_giao(): void
    {
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('50000.00')->create(['code' => 'GIAM50K']));

        $this->get(self::DETAILS);
        $order = $this->placeOrder();

        $tienHang = (float) $order->subtotal;
        $giamSanPham = (float) $order->discount_total;
        $giamMa = (float) $order->coupon_discount;
        $phiGiao = (float) $order->shipping_fee;

        $this->assertSame(500000.0, $tienHang);
        $this->assertSame(50000.0, $giamMa);
        $this->assertSame('GIAM50K', $order->coupon_code);
        $this->assertSame(
            $tienHang - $giamSanPham - $giamMa + $phiGiao,
            (float) $order->grand_total,
            'Tổng đơn phải bằng đúng phép cộng của các dòng.',
        );
    }

    #[Test]
    public function khong_tin_so_tien_giam_do_trinh_duyet_gui_len(): void
    {
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('50000.00')->create(['code' => 'GIAM50K']));
        $this->get(self::DETAILS);

        $order = $this->placeOrder([
            'coupon_discount' => '499000',
            'discount_total' => '499000',
            'grand_total' => '1000',
            'subtotal' => '1000',
            'shipping_fee' => '0',
        ]);

        $this->assertSame(50000.0, (float) $order->coupon_discount);
        $this->assertSame(500000.0, (float) $order->subtotal);
        $this->assertGreaterThan(400000.0, (float) $order->grand_total);
    }

    #[Test]
    public function ma_go_do_o_o_nhap_van_duoc_ap_khi_bam_xem_lai_don_hang(): void
    {
        $this->shopperWithCart('500000.00');
        Coupon::factory()->fixed('50000.00')->create(['code' => 'GONHUNGCHUABAM']);
        $this->get(self::DETAILS);
        $this->assertNull($this->appliedCoupon());

        $this->post(self::DETAILS, $this->details(['coupon_code' => 'gonhungchuabam']))
            ->assertRedirect('/thanh-toan/xac-nhan');

        $this->assertSame('GONHUNGCHUABAM', $this->appliedCoupon(),
            'Mã gõ dở phải được áp, và chữ thường cũng phải nhận.');
    }

    #[Test]
    public function ma_sai_o_o_nhap_thi_quay_lai_bao_loi_chu_khong_di_tiep(): void
    {
        $this->shopperWithCart('500000.00');
        $this->get(self::DETAILS);

        $this->post(self::DETAILS, $this->details(['coupon_code' => 'KHONGCOTHAT']))
            ->assertRedirect()
            ->assertSessionHasErrors('coupon_code')
            ->assertSessionHasInput('recipient_name', 'Nguyễn Văn Kiểm Thử');

        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function loi_tu_choi_ma_chi_co_gia_tri_cho_don_dang_lam_do(): void
    {
        [$user, $product] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('50000.00')->create(['code' => 'GIAM50K']));

        $this->get(self::DETAILS);
        $this->delete(self::COUPON);
        $this->post(self::COUPON.'/tu-chon');
        $this->placeOrder();

        $this->addToCart($product);
        $this->get(self::DETAILS);

        $this->assertSame('GIAM50K', $this->appliedCoupon(),
            'Đơn mới phải được tự chọn mã lại từ đầu.');
    }

    #[Test]
    public function ghi_nhan_luot_dung_ma_sau_khi_don_tao_thanh_cong(): void
    {
        [$user] = $this->shopperWithCart('500000.00');
        $coupon = Coupon::factory()->fixed('50000.00')->create(['code' => 'GIAM50K']);
        $this->claim($user, $coupon);

        $this->get(self::DETAILS);
        $this->assertSame(0, $coupon->fresh()->used_count, 'Chỉ áp mã thì chưa tính là đã dùng.');

        $this->placeOrder();
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    #[Test]
    public function gio_trong_thi_khong_vao_duoc_trang_thanh_toan(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $this->get(self::DETAILS)->assertRedirect('/gio-hang');
        $this->post('/thanh-toan/dat-hang')->assertRedirect();
        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function thieu_thong_tin_bat_buoc_thi_khong_sang_buoc_xac_nhan(): void
    {
        $this->shopperWithCart('500000.00');

        $this->post(self::DETAILS, $this->details(['recipient_phone' => '123']))
            ->assertRedirect()
            ->assertSessionHasErrors('recipient_phone');

        $this->get('/thanh-toan/xac-nhan')->assertRedirect();
        $this->assertSame(0, Order::count());
    }
}
