<?php

namespace Tests\Feature\Loyalty;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\MemberTier;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Checkout\CheckoutSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Quyền lợi hạng khi thanh toán: giảm theo hạng, miễn phí giao theo hạng,
 * luật cộng dồn với mã, mã dành cho hạng.
 */
class UuDaiHangThanhToanTest extends TestCase
{
    use RefreshDatabase;

    /** Khách đã mua $daMua (đơn đã giao) — quyết định hạng. */
    private function khach(string $daMua): User
    {
        $u = User::factory()->create();

        if (bccomp($daMua, '0', 2) > 0) {
            $d = Order::create([
                'order_number' => 'FP-UD-' . strtoupper(bin2hex(random_bytes(3))),
                'recipient_name' => 'Khách', 'recipient_phone' => '0912345678',
                'shipping_address' => '1 Đường', 'shipping_province' => 'Thành phố Hà Nội',
                'payment_method' => 'cod', 'subtotal' => $daMua, 'discount_total' => '0.00',
                'shipping_fee' => '0.00', 'coupon_discount' => '0.00', 'grand_total' => $daMua,
            ]);
            $d->forceFill(['user_id' => $u->id, 'status' => OrderStatus::Completed, 'payment_status' => PaymentStatus::Paid])->save();
        }

        $this->actingAs($u);

        return $u;
    }

    private function vaoThanhToan(string $gia = '300000.00'): void
    {
        $sp = Product::factory()->for(Category::factory())->price($gia)->stock(50)->create();

        $this->post('/gio-hang', ['product_id' => $sp->id, 'quantity' => 1])->assertRedirect();
        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử', 'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com', 'shipping_address' => '12 Đường Thử Nghiệm',
            'shipping_ward' => 'Phường 1', 'shipping_district' => 'Quận 3',
            'shipping_province' => 'Thành phố Hồ Chí Minh', 'payment_method' => 'cod',
            'address_id' => '', 'coupon_code' => '',
        ])->assertRedirect();
    }

    private function gio(): \App\Services\Checkout\CheckoutBasket
    {
        return app(CheckoutSource::class)->basket();
    }

    #[Test]
    public function hang_hoa_giam_2_phan_tram_va_chup_vao_don(): void
    {
        $this->khach('5000000.00');
        $this->vaoThanhToan();

        $this->get(route('shop.checkout.details'))->assertSee('data-uu-dai-hang="6000.00"', false);

        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        $don = Order::latest('id')->firstOrFail();
        $this->assertSame('hoa', $don->member_tier_code);
        $this->assertSame('6000.00', (string) $don->member_discount);
        $this->assertSame(bcadd('294000.00', (string) $don->shipping_fee, 2), (string) $don->grand_total);

        // Đối soát thuế: phần phân bổ xuống dòng = hạng + mã + điểm.
        $this->assertSame('6000.00', bcadd((string) $don->items()->sum('discount_amount'), '0', 2));
    }

    #[Test]
    public function khach_hang_mam_va_khach_vang_lai_khong_duoc_giam(): void
    {
        $this->khach('0');
        $this->vaoThanhToan();

        $this->assertSame('0.00', $this->gio()->memberDiscount());
        $this->get(route('shop.checkout.details'))->assertDontSee('data-uu-dai-hang', false);
    }

    #[Test]
    public function ma_khong_cong_don_thi_tat_uu_dai_hang_va_noi_ra(): void
    {
        $this->khach('5000000.00');
        $this->vaoThanhToan();
        Coupon::factory()->fixed('50000.00')->create(['code' => 'KHONGDON']);

        $this->post(route('shop.checkout.apply-coupon'), ['coupon_code' => 'KHONGDON'])->assertSessionHas('success');

        $gio = $this->gio();
        $this->assertSame('0.00', $gio->memberDiscount());
        $this->assertSame('50000.00', $gio->couponDiscount());
        $this->get(route('shop.checkout.details'))->assertSee('data-uu-dai-hang-bi-chan', false);
    }

    #[Test]
    public function ma_cong_don_thi_tinh_tren_phan_con_lai_sau_hang(): void
    {
        $this->khach('5000000.00');
        $this->vaoThanhToan();
        Coupon::factory()->percent('10')->create(['code' => 'CONGDON', 'stack_with_member' => true]);

        $this->post(route('shop.checkout.apply-coupon'), ['coupon_code' => 'CONGDON']);

        $gio = $this->gio();
        $this->assertSame('6000.00', $gio->memberDiscount());
        // 10% của 294.000, không phải của 300.000.
        $this->assertSame('29400.00', $gio->couponDiscount());
        $this->assertSame('264600.00', $gio->payableItemsTotal());
    }

    #[Test]
    public function ma_danh_cho_hang_chan_hang_thap_hon_va_khach_vang_lai(): void
    {
        $vuon = MemberTier::where('code', 'vuon')->value('id');
        $hoa = MemberTier::where('code', 'hoa')->value('id');
        Coupon::factory()->fixed('20000.00')->create(['code' => 'CHOVUON', 'min_member_tier_id' => $vuon]);
        Coupon::factory()->fixed('20000.00')->create(['code' => 'CHOHOA', 'min_member_tier_id' => $hoa]);

        $this->khach('5000000.00');
        $this->vaoThanhToan();

        $this->post(route('shop.checkout.apply-coupon'), ['coupon_code' => 'CHOVUON'])
            ->assertSessionHas('error', 'Mã này dành cho thành viên hạng Vườn trở lên.');
        $this->post(route('shop.checkout.apply-coupon'), ['coupon_code' => 'CHOHOA'])->assertSessionHas('success');

        auth()->logout();
        $this->expectException(\App\Services\Coupon\CouponException::class);
        app(\App\Services\Coupon\CouponService::class)->resolve('CHOHOA', '300000.00');
    }

    #[Test]
    public function hang_vuon_duoc_mien_phi_giao_tu_nguong_rieng(): void
    {
        config(['shipping.free_from' => 500000]);

        $this->khach('15000000.00');
        $this->vaoThanhToan('350000.00');

        $gio = $this->gio();
        $this->assertTrue($gio->freeShippingByTier());
        $this->assertTrue($gio->isFreeShipping());
        $this->assertSame('0.00', $gio->shippingFee());
        $this->get(route('shop.checkout.details'))->assertSee('ưu đãi hạng Vườn');
    }

    #[Test]
    public function nguong_hang_cao_hon_nguong_chung_thi_khong_lam_te_di(): void
    {
        config(['shipping.free_from' => 300000]);
        MemberTier::where('code', 'vuon')->update(['free_shipping_from' => 800000]);

        $this->khach('15000000.00');
        $this->vaoThanhToan('350000.00');

        $gio = $this->gio();
        $this->assertFalse($gio->freeShippingByTier());
        $this->assertTrue($gio->isFreeShipping());
    }

    #[Test]
    public function tu_chon_ma_khong_ap_ma_khong_cong_don_kem_hon_uu_dai_hang(): void
    {
        $u = $this->khach('30000000.00');   // Rừng 5% → 15.000đ trên đơn 300.000đ
        $this->vaoThanhToan();

        $it = Coupon::factory()->fixed('10000.00')->create(['code' => 'ITHONHANG']);
        DB::table('coupon_user')->insert(['user_id' => $u->id, 'coupon_id' => $it->id, 'claimed_at' => now(), 'used_count' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->get(route('shop.checkout.details'))->assertOk();
        $this->assertNull(session(CheckoutSource::COUPON_KEY), 'Mã 10.000đ không cộng dồn kém hơn ưu đãi 15.000đ');

        $nhieu = Coupon::factory()->fixed('20000.00')->create(['code' => 'NHIEUHONHANG']);
        DB::table('coupon_user')->insert(['user_id' => $u->id, 'coupon_id' => $nhieu->id, 'claimed_at' => now(), 'used_count' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->get(route('shop.checkout.details'))->assertOk();
        $this->assertSame('NHIEUHONHANG', session(CheckoutSource::COUPON_KEY));
    }

    #[Test]
    public function quan_tri_luu_quyen_loi_hang_va_thuoc_tinh_ma(): void
    {
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        $hang = MemberTier::orderBy('min_spend')->get();
        $bo = ['hang' => $hang->values()->map(fn ($h) => [
            'id' => $h->id, 'name' => $h->name, 'min_spend' => (int) $h->min_spend,
            'discount_percent' => $h->code === 'hoa' ? '2.5' : (float) $h->discount_percent,
            'free_shipping_from' => $h->code === 'vuon' ? '' : ($h->free_shipping_from === null ? '' : (int) $h->free_shipping_from),
            'bonus_points_percent' => $h->bonus_points_percent,
        ])->all()];

        $this->actingAs($admin)->put(route('admin.member-tiers.update'), $bo)->assertSessionHasNoErrors();
        $this->assertSame('2.50', (string) MemberTier::where('code', 'hoa')->value('discount_percent'));
        $this->assertNull(MemberTier::where('code', 'vuon')->value('free_shipping_from'));

        $ma = Coupon::factory()->fixed('10000.00')->create(['code' => 'SUAMA', 'stack_with_member' => true]);
        $du = [
            'code' => 'SUAMA', 'name' => 'Sửa mã', 'type' => 'fixed_amount', 'value' => '10000',
            'status' => 'active', 'min_member_tier_id' => MemberTier::where('code', 'la')->value('id'),
        ];

        // Bỏ tích "cộng dồn" (không gửi ô) phải tắt được.
        $this->actingAs($admin)->put(route('admin.coupons.update', $ma), $du)->assertSessionHasNoErrors();
        $ma->refresh();
        $this->assertFalse($ma->stack_with_member);
        $this->assertSame(MemberTier::where('code', 'la')->value('id'), $ma->min_member_tier_id);
    }
}
