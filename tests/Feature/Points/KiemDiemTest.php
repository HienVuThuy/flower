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
use App\Services\Points\PointLedger;
use App\Services\Refund\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Kiếm điểm từ mua hàng, trừ lại khi hoàn tiền, cộng khi đánh giá; hiện điểm cho khách. */
class KiemDiemTest extends TestCase
{
    use RefreshDatabase;

    private function soDu(User $u): int
    {
        return app(PointLedger::class)->soDu($u);
    }

    private function donDangGiao(?User $u, string $tong = '355000.00', string $ship = '30000.00', ?Product $sp = null): Order
    {
        $don = Order::create([
            'order_number' => 'FP-KD-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách', 'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường', 'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod', 'subtotal' => bcsub($tong, $ship, 2), 'discount_total' => '0.00',
            'shipping_fee' => $ship, 'coupon_discount' => '0.00', 'grand_total' => $tong,
        ]);
        $don->forceFill(['user_id' => $u?->id, 'status' => OrderStatus::Shipping, 'payment_status' => PaymentStatus::Paid])->save();

        if ($sp) {
            $don->items()->create([
                'product_id' => $sp->id, 'product_name' => $sp->name, 'quantity' => 1,
                'unit_base_price' => '325000.00', 'unit_price' => '325000.00', 'line_total' => '325000.00',
            ]);
        }

        return $don;
    }

    private function giao(Order $don): void
    {
        app(OrderService::class)->changeStatus($don->fresh(), OrderStatus::Completed);
    }

    #[Test]
    public function don_da_giao_cong_1_diem_moi_10k_tien_hang_khong_tinh_ship(): void
    {
        $u = User::factory()->create();

        $don = $this->donDangGiao($u);
        $this->assertSame(0, $this->soDu($u), 'Chưa giao thì chưa có điểm');

        $this->giao($don);

        $this->assertSame(32, $this->soDu($u));
    }

    #[Test]
    public function khach_vang_lai_va_don_huy_khong_co_diem(): void
    {
        $this->giao($this->donDangGiao(null));

        $u = User::factory()->create();
        app(OrderService::class)->changeStatus($this->donDangGiao($u)->fresh(), OrderStatus::Cancelled, 'Khách đổi ý');

        $this->assertSame(0, $this->soDu($u));
        $this->assertDatabaseCount('point_transactions', 0);
    }

    #[Test]
    public function hoan_tien_sau_khi_cong_thi_tru_lai_khong_qua_so_da_cong_va_duoc_am(): void
    {
        $u = User::factory()->create();
        $don = $this->donDangGiao($u);
        $this->giao($don);
        $this->assertSame(32, $this->soDu($u));

        app(PointLedger::class)->tru($u, 30, PointReason::DoiVoucher, 'thu:tieu');

        $hoan = fn (int $tien) => app(RefundService::class)->hoan($don->fresh(), [
            'amount' => $tien, 'reason' => 'khac', 'method' => 'chuyen_khoan', 'reference' => 'FT' . random_int(100000, 999999),
        ]);

        $hoan(150000);
        $this->assertSame(2 - 15, $this->soDu($u), 'Được âm: điểm của đơn đã tiêu trước khi hoàn');

        $hoan(205000);
        $this->assertSame(2 - 32, $this->soDu($u));
    }

    #[Test]
    public function danh_gia_co_nhan_xet_duoc_nhieu_hon_va_viet_lai_khong_cong_lan_hai(): void
    {
        $u = User::factory()->create();
        $sp = Product::factory()->for(Category::factory())->create(['status' => 'active']);
        $don = $this->donDangGiao($u, sp: $sp);
        $this->giao($don);
        $truoc = $this->soDu($u);

        $this->actingAs($u)->post(route('shop.reviews.store', $sp), [
            'rating' => 5, 'comment' => 'Cây về khoẻ, lá xanh, đóng gói kỹ lắm nha shop.',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'cộng 10 điểm'));

        $this->assertSame($truoc + 10, $this->soDu($u));

        $this->actingAs($u)->delete(route('shop.reviews.destroy', $u->reviews()->first()));
        $this->actingAs($u)->post(route('shop.reviews.store', $sp), ['rating' => 4, 'comment' => 'ok']);
        $this->assertSame($truoc + 10, $this->soDu($u));
    }

    #[Test]
    public function danh_gia_chi_cham_sao_duoc_3_diem(): void
    {
        $u = User::factory()->create();
        $sp = Product::factory()->for(Category::factory())->create(['status' => 'active']);
        $this->giao($this->donDangGiao($u, sp: $sp));
        $truoc = $this->soDu($u);

        $this->actingAs($u)->post(route('shop.reviews.store', $sp), ['rating' => 5, 'comment' => 'Cây đẹp']);

        $this->assertSame($truoc + 3, $this->soDu($u));
    }

    #[Test]
    public function diem_hien_o_menu_tai_khoan_va_tom_tat_ho_so(): void
    {
        $u = User::factory()->create();
        app(PointLedger::class)->cong($u, 1250, PointReason::DangBai, 'thu:1');

        $html = $this->actingAs($u)->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#data-diem-header[^>]*>.*?<strong>1\.250</strong>#s', $html);

        $html = $this->actingAs($u)->get(route('shop.profile.edit'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#Điểm thưởng</dt>\s*<dd><a[^>]*>1\.250</a>#', $html);

        $html = $this->actingAs($u)->get(route('shop.profile.edit', ['muc' => 'diem-thuong']))->getContent();
        $this->assertStringContainsString('1 điểm / 10.000đ', $html);
        $this->assertStringNotContainsString('data-so-du-am', $html);
    }
}
