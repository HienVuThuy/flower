<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Sửa thông tin giao hàng của đơn khi khách gọi báo nhập nhầm. */
class SuaGiaoHangTest extends TestCase
{
    use RefreshDatabase;

    private function nguoi(UserRole $vaiTro = UserRole::Admin): User
    {
        $u = User::factory()->create();
        $u->role = $vaiTro;
        $u->save();

        return $u;
    }

    private function don(OrderStatus $trangThai, array $them = []): Order
    {
        $don = Order::create([
            'order_number' => 'FP-GH-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Chị Mai',
            'recipient_phone' => '0911111111',
            'shipping_address' => '12 Hàng Hoa',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '30000.00',
            'coupon_discount' => '0.00',
            'grand_total' => '330000.00',
        ]);

        $don->forceFill(['status' => $trangThai, 'payment_status' => PaymentStatus::Unpaid] + $them)->save();

        return $don->fresh();
    }

    private function duLieu(array $ghiDe = []): array
    {
        return array_merge([
            'recipient_name' => 'Chị Mai',
            'recipient_phone' => '0922222222',
            'shipping_address' => '12 Hàng Hoa',
            'delivery_date' => '',
            'delivery_note' => '',
        ], $ghiDe);
    }

    #[Test]
    public function sua_so_dien_thoai_nham_va_co_nhat_ky_cu_moi(): void
    {
        $don = $this->don(OrderStatus::Confirmed);

        $this->actingAs($this->nguoi())
            ->patch(route('admin.orders.delivery', $don), $this->duLieu())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('0922222222', $don->fresh()->recipient_phone);

        $nk = ActivityLog::where('action', 'order.delivery_updated')->firstOrFail();
        $this->assertStringContainsString('0911111111 → 0922222222', json_encode($nk->properties, JSON_UNESCAPED_UNICODE));
    }

    #[Test]
    public function KHONG_sua_duoc_tinh_va_phi_ship(): void
    {
        $don = $this->don(OrderStatus::Pending);

        $this->actingAs($this->nguoi())
            ->patch(route('admin.orders.delivery', $don), $this->duLieu([
                'shipping_province' => 'Thành phố Hồ Chí Minh',
                'shipping_fee' => '0',
            ]));

        $don->refresh();
        $this->assertSame('Thành phố Hà Nội', $don->shipping_province);
        $this->assertSame('30000.00', $don->shipping_fee);
    }

    #[Test]
    public function KHONG_sua_khi_dang_chuan_bi_hoac_da_co_van_don(): void
    {
        $dangChuanBi = $this->don(OrderStatus::Preparing);
        $coVanDon = $this->don(OrderStatus::Confirmed, ['ghn_order_code' => 'GHN1']);

        foreach ([$dangChuanBi, $coVanDon] as $don) {
            $this->actingAs($this->nguoi())
                ->patch(route('admin.orders.delivery', $don), $this->duLieu())
                ->assertSessionHas('error');

            $this->assertSame('0911111111', $don->fresh()->recipient_phone);
        }
    }

    #[Test]
    public function so_dien_thoai_sai_bi_tu_choi(): void
    {
        $don = $this->don(OrderStatus::Confirmed);

        $this->actingAs($this->nguoi())
            ->patch(route('admin.orders.delivery', $don), $this->duLieu(['recipient_phone' => 'goi em nhe']))
            ->assertSessionHasErrors('recipient_phone');
    }

    #[Test]
    public function form_chi_hien_khi_sua_duoc(): void
    {
        $admin = $this->nguoi();
        $mo = $this->don(OrderStatus::Confirmed);
        $khoa = $this->don(OrderStatus::Shipping);

        $this->actingAs($admin)->get(route('admin.orders.show', $mo))
            ->assertOk()->assertSee('action="' . route('admin.orders.delivery', $mo) . '"', false);

        $this->actingAs($admin)->get(route('admin.orders.show', $khoa))
            ->assertOk()->assertDontSee('action="' . route('admin.orders.delivery', $khoa) . '"', false);
    }

    #[Test]
    public function khong_doi_gi_thi_khong_ghi_nhat_ky(): void
    {
        $don = $this->don(OrderStatus::Confirmed);

        $this->actingAs($this->nguoi())
            ->patch(route('admin.orders.delivery', $don), $this->duLieu(['recipient_phone' => '0911111111']));

        $this->assertSame(0, ActivityLog::where('action', 'order.delivery_updated')->count());
    }
}
