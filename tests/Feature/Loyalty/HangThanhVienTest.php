<?php

namespace Tests\Feature\Loyalty;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\MemberTier;
use App\Models\Order;
use App\Models\User;
use App\Services\Loyalty\MemberTierResolver;
use App\Services\Loyalty\QualifiedSpending;
use App\Services\Order\OrderService;
use App\Services\Points\PointLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Hạng thành viên: theo chi tiêu hợp lệ (không theo điểm), cấu hình được, thưởng điểm theo hạng. */
class HangThanhVienTest extends TestCase
{
    use RefreshDatabase;

    private function don(User $u, string $tong, string $ship = '0.00', OrderStatus $tt = OrderStatus::Completed): Order
    {
        $d = Order::create([
            'order_number' => 'FP-HTV-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách', 'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường', 'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod', 'subtotal' => bcsub($tong, $ship, 2), 'discount_total' => '0.00',
            'shipping_fee' => $ship, 'coupon_discount' => '0.00', 'grand_total' => $tong,
        ]);
        $d->forceFill(['user_id' => $u->id, 'status' => $tt, 'payment_status' => PaymentStatus::Paid])->save();

        return $d;
    }

    private function hoan(Order $d, string $tien): void
    {
        $r = $d->refunds()->create(['code' => 'HT-HTV-' . random_int(1000, 9999), 'amount' => $tien, 'method' => 'tien_mat', 'reason' => 'khac']);
        $r->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
    }

    #[Test]
    public function chi_tieu_hop_le_chi_don_da_giao_tru_ship_tru_hoan(): void
    {
        $u = User::factory()->create();
        $a = $this->don($u, '1030000.00', '30000.00');
        $this->don($u, '800000.00');
        $this->don($u, '5000000.00', '0.00', OrderStatus::Cancelled);
        $this->don($u, '5000000.00', '0.00', OrderStatus::Shipping);
        $this->hoan($a, '200000.00');

        $this->don(User::factory()->create(), '9000000.00');

        $this->assertSame('1600000.00', app(QualifiedSpending::class)->cua($u));
    }

    #[Test]
    public function ranh_gioi_hang_dung_nguong(): void
    {
        $dv = app(MemberTierResolver::class);

        $this->assertSame('mam', $dv->theoChiTieu('1999999.00')->code);
        $this->assertSame('la', $dv->theoChiTieu('2000000.00')->code);
        $this->assertSame('hoa', $dv->theoChiTieu('5000000.00')->code);
        $this->assertSame('vuon', $dv->theoChiTieu('29999999.00')->code);
        $this->assertSame('rung', $dv->theoChiTieu('30000000.00')->code);
    }

    #[Test]
    public function ho_so_va_menu_noi_hang_va_con_thieu_bao_nhieu(): void
    {
        $u = User::factory()->create();
        $this->don($u, '1500000.00');

        $html = $this->actingAs($u)->get(route('shop.profile.edit', ['muc' => 'hang-thanh-vien']))->assertOk()->getContent();
        $this->assertStringContainsString('data-hang="mam"', $html);
        $this->assertMatchesRegularExpression('#Còn <strong>500\.000[^<]*</strong> nữa để lên hạng Lá#', $html);
        $this->assertMatchesRegularExpression('#data-hang-header[^>]*>.*?<strong>Mầm</strong>#s', $html);

        app(PointLedger::class)->tru($u, 999, \App\Enums\PointReason::DoiVoucher, 'thu');
        $this->assertSame('mam', app(MemberTierResolver::class)->cua($u)['hang']->code);
    }

    #[Test]
    public function don_duoc_giao_thuong_them_diem_theo_hang(): void
    {
        $u = User::factory()->create();
        $this->don($u, '5000000.00');

        $moi = $this->don($u, '1000000.00', '0.00', OrderStatus::Shipping);
        app(OrderService::class)->changeStatus($moi->fresh(), OrderStatus::Completed);

        $this->assertSame(110, app(PointLedger::class)->soDu($u));
    }

    #[Test]
    public function quan_tri_sua_nguong_va_chan_cau_hinh_vo_ly(): void
    {
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        $hang = MemberTier::orderBy('min_spend')->get();
        $bo = fn (array $doi = []) => ['hang' => $hang->values()->map(fn ($h, $i) => array_merge([
            'id' => $h->id, 'name' => $h->name, 'min_spend' => (int) $h->min_spend, 'bonus_points_percent' => $h->bonus_points_percent,
            'discount_percent' => (float) $h->discount_percent,
            'free_shipping_from' => $h->free_shipping_from === null ? '' : (int) $h->free_shipping_from,
        ], $doi[$i] ?? []))->all()];

        $this->actingAs($admin)->put(route('admin.member-tiers.update'), $bo([1 => ['min_spend' => 3000000, 'name' => 'Lá xanh']]))
            ->assertSessionHasNoErrors();
        $this->assertSame('3000000.00', (string) MemberTier::where('code', 'la')->value('min_spend'));
        $this->assertSame('Lá xanh', MemberTier::where('code', 'la')->value('name'));

        $this->actingAs($admin)->put(route('admin.member-tiers.update'), $bo([0 => ['min_spend' => 100000]]))
            ->assertSessionHasErrors('hang');
        $this->actingAs($admin)->put(route('admin.member-tiers.update'), $bo([1 => ['min_spend' => 3000000], 2 => ['min_spend' => 3000000]]))
            ->assertSessionHasErrors('hang');
        $this->assertSame('5000000.00', (string) MemberTier::where('code', 'hoa')->value('min_spend'));
        $this->assertSame('0.00', (string) MemberTier::where('code', 'mam')->value('min_spend'));

        $nv = User::factory()->create();
        $nv->role = UserRole::Staff;
        $nv->save();
        $this->actingAs($nv)->get(route('admin.member-tiers.index'))->assertForbidden();
    }
}
