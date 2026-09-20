<?php

namespace Tests\Feature\Points;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PointReason;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\OrderService;
use App\Services\Points\PointException;
use App\Services\Points\PointLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Dùng điểm thưởng trừ tiền khi đặt hàng. */
class DiemTruTienTest extends TestCase
{
    use RefreshDatabase;

    private User $u;

    protected function setUp(): void
    {
        parent::setUp();
        $this->u = User::factory()->create();
        $this->actingAs($this->u);
    }

    private function so(): PointLedger
    {
        return app(PointLedger::class);
    }

    private function nap(int $diem): void
    {
        $this->so()->cong($this->u, $diem, PointReason::DangBai, 'nap:' . $diem . ':' . random_int(1, 999999));
    }

    private function vaoThanhToan(string $gia = '300000.00'): Product
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

        return $sp;
    }

    #[Test]
    public function dung_diem_bi_kep_theo_30_phan_tram_tien_hang_va_noi_ra(): void
    {
        $this->nap(5000);
        $this->vaoThanhToan();

        $this->post(route('shop.checkout.apply-points'), ['points' => 5000])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'tối đa 900 điểm'));

        $this->get(route('shop.checkout.details'))->assertOk()
            ->assertSee('data-diem-giam="90000.00"', false);
    }

    #[Test]
    public function tran_30_phan_tram_tinh_tren_tien_hang_SAU_ma_giam_gia(): void
    {
        $this->nap(5000);
        $this->vaoThanhToan();
        \App\Models\Coupon::factory()->fixed('100000.00')->create(['code' => 'GIAM100K']);

        $this->post(route('shop.checkout.apply-coupon'), ['coupon_code' => 'GIAM100K'])->assertSessionHas('success');
        $this->post(route('shop.checkout.apply-points'), ['points' => 5000]);

        $this->get(route('shop.checkout.details'))->assertSee('data-diem-giam="60000.00"', false);

        $gio = app(\App\Services\Checkout\CheckoutSource::class)->basket();
        $this->assertNotNull($gio->coupon, 'Giỏ phải đang mang mã để phép kiểm có nghĩa');
        $this->assertSame(600, $gio->withPoints(5000)->pointsUsed());
    }

    #[Test]
    public function dung_diem_bi_kep_theo_so_du(): void
    {
        $this->nap(350);
        $this->vaoThanhToan();

        $this->post(route('shop.checkout.apply-points'), ['points' => 900]);

        $this->get(route('shop.checkout.details'))->assertSee('data-diem-giam="35000.00"', false);
    }

    #[Test]
    public function duoi_muc_toi_thieu_thi_khong_dung_va_bao_loi(): void
    {
        $this->nap(99);
        $this->vaoThanhToan();

        $this->post(route('shop.checkout.apply-points'), ['points' => 99])->assertSessionHas('error');

        $this->get(route('shop.checkout.details'))->assertDontSee('data-diem-giam', false);
    }

    #[Test]
    public function khach_vang_lai_khong_dung_diem(): void
    {
        auth()->logout();

        $this->post(route('shop.checkout.apply-points'), ['points' => 500])->assertRedirect('/dang-nhap');
    }

    #[Test]
    public function dat_hang_tru_diem_chup_vao_don_va_phan_bo_thue(): void
    {
        $this->nap(1000);
        $this->vaoThanhToan();
        $this->post(route('shop.checkout.apply-points'), ['points' => 500]);

        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        $don = Order::latest('id')->firstOrFail();
        $this->assertSame(500, $don->points_used);
        $this->assertSame('50000.00', (string) $don->points_discount);
        $this->assertSame(
            bcadd(bcsub('300000.00', '50000.00', 2), (string) $don->shipping_fee, 2),
            (string) $don->grand_total,
        );

        $this->assertSame(500, $this->so()->soDu($this->u));

        $this->assertSame(
            bcadd((string) $don->coupon_discount, (string) $don->points_discount, 2),
            bcadd((string) $don->items()->sum('discount_amount'), '0', 2),
        );

        $this->assertNull(session(\App\Services\Checkout\CheckoutSource::POINTS_KEY));
    }

    #[Test]
    public function diem_vua_tieu_o_noi_khac_thi_don_khong_giam_bang_diem_ao(): void
    {
        $this->nap(500);
        $this->vaoThanhToan();
        $this->post(route('shop.checkout.apply-points'), ['points' => 500]);

        $this->so()->tru($this->u, 450, PointReason::DoiVoucher, 'tab-khac');

        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        $don = Order::latest('id')->firstOrFail();
        $this->assertSame(0, $don->points_used);
        $this->assertSame(50, $this->so()->soDu($this->u));
    }

    #[Test]
    public function tru_diem_cho_don_khoa_va_tu_choi_khi_khong_du(): void
    {
        $don = Order::create([
            'order_number' => 'FP-DT-1', 'recipient_name' => 'K', 'recipient_phone' => '0912345678',
            'shipping_address' => '1', 'shipping_province' => 'Thành phố Hà Nội', 'payment_method' => 'cod',
            'subtotal' => '1', 'discount_total' => '0', 'shipping_fee' => '0', 'coupon_discount' => '0', 'grand_total' => '1',
        ]);
        $this->nap(100);

        $this->expectException(PointException::class);
        $this->so()->dungChoDon($this->u, 101, $don);
    }

    #[Test]
    public function huy_don_tra_lai_diem_mot_lan(): void
    {
        $this->nap(1000);
        $this->vaoThanhToan();
        $this->post(route('shop.checkout.apply-points'), ['points' => 500]);
        $this->post('/thanh-toan/dat-hang');
        $don = Order::latest('id')->firstOrFail();

        app(OrderService::class)->changeStatus($don->fresh(), OrderStatus::Cancelled, 'Khách đổi ý');

        $this->assertSame(1000, $this->so()->soDu($this->u));
        $this->assertSame(1, \App\Models\PointTransaction::where('reason', PointReason::HoanDiem->value)->count());
    }

    #[Test]
    public function hoan_du_tien_don_da_giao_thi_tra_diem_da_dung(): void
    {
        $this->nap(1000);
        $this->vaoThanhToan();
        $this->post(route('shop.checkout.apply-points'), ['points' => 500]);
        $this->post('/thanh-toan/dat-hang');
        $don = Order::latest('id')->firstOrFail();

        $dv = app(OrderService::class);
        $dv->setPaymentStatus($don->fresh(), PaymentStatus::Paid);
        foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Shipping, OrderStatus::Completed] as $b) {
            $dv->changeStatus($don->fresh(), $b);
        }

        app(\App\Services\Refund\RefundService::class)->hoan($don->fresh(), [
            'amount' => (int) $don->fresh()->grand_total, 'reason' => 'khac', 'method' => 'chuyen_khoan', 'reference' => 'FT1234567',
        ]);

        $tra = \App\Models\PointTransaction::where('reason', PointReason::HoanDiem->value)->sole();
        $this->assertSame(500, $tra->amount);

        $this->assertSame(1000, $this->so()->soDu($this->u));
    }
}
