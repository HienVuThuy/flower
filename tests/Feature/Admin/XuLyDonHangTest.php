<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Admin xử lý đơn hàng: bộ lọc, tab trạng thái, không huỷ đơn đang giao (lab08). */
class XuLyDonHangTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function don(string $ma, array $ghiDe = [], string $sanPham = 'Chậu sen đá'): Order
    {
        $don = Order::create($ghiDe + [
            'order_number' => $ma,
            'recipient_name' => 'Khách ' . $ma,
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Phố Huế',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => '100000',
            'discount_total' => '0',
            'shipping_fee' => '0',
            'coupon_discount' => '0',
            'grand_total' => '100000',
        ]);

        $don->forceFill(array_intersect_key($ghiDe, array_flip(['status', 'payment_method', 'shipping_status', 'ghn_order_code'])))->save();

        $don->items()->create([
            'product_name' => $sanPham,
            'unit_base_price' => '100000',
            'unit_price' => '100000',
            'quantity' => 1,
            'line_total' => '100000',
        ]);

        return $don;
    }

    #[Test]
    public function tim_theo_ma_van_don_GHN_va_ten_san_pham(): void
    {
        $this->don('FP-A', ['ghn_order_code' => 'LKX9QW']);
        $this->don('FP-B', [], 'Bó hoa hồng đỏ');

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index', ['q' => 'LKX9QW']))
            ->assertSee('FP-A')
            ->assertDontSee('FP-B');

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index', ['q' => 'hoa hồng']))
            ->assertSee('FP-B')
            ->assertDontSee('FP-A');
    }

    #[Test]
    public function loc_theo_phuong_thuc_va_trang_thai_van_chuyen(): void
    {
        $this->don('FP-COD');
        $this->don('FP-MOMO', ['payment_method' => 'momo', 'shipping_status' => 'delivering']);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['phuong_thuc' => 'momo']))
            ->assertSee('FP-MOMO')
            ->assertDontSee('FP-COD');

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['van_chuyen' => 'delivering']))
            ->assertSee('FP-MOMO')
            ->assertDontSee('FP-COD');
    }

    #[Test]
    public function so_tren_tab_dem_theo_bo_loc_dang_chon(): void
    {
        $this->don('FP-1', ['payment_method' => 'momo']);
        $this->don('FP-2', ['payment_method' => 'momo', 'status' => OrderStatus::Confirmed->value]);
        $this->don('FP-3');

        $html = $this->actingAs($this->admin())
            ->get(route('admin.orders.index', ['phuong_thuc' => 'momo']))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#Tất cả\s*<span class="admin-filter-tab__count">2</span>#u', $html);
    }

    #[Test]
    public function chon_so_don_moi_trang(): void
    {
        foreach (range(1, 25) as $i) {
            $this->don('FP-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        }

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index', ['moi_trang' => 50]))
            ->assertSee('FP-001')
            ->assertSee('FP-025');

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index'))
            ->assertDontSee('FP-001');
    }

    #[Test]
    public function don_dang_giao_chi_hien_hoan_hang_va_bat_buoc_ly_do(): void
    {
        $don = $this->don('FP-GIAO', ['status' => OrderStatus::Shipping->value]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $don))
            ->assertSee('Hoàn hàng (giao không thành công)');

        $this->actingAs($admin)
            ->patch(route('admin.orders.update-status', $don), ['status' => 'cancelled'])
            ->assertSessionHasErrors('cancel_reason');

        $this->assertSame(OrderStatus::Shipping, $don->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.orders.update-status', $don), ['status' => 'cancelled', 'cancel_reason' => 'Khách từ chối nhận'])
            ->assertSessionHas('success');

        $this->assertSame(OrderStatus::Cancelled, $don->fresh()->status);
        $this->assertSame('Hoàn hàng: Khách từ chối nhận', $don->fresh()->cancel_reason);
    }

    #[Test]
    public function don_chua_giao_van_huy_binh_thuong(): void
    {
        $don = $this->don('FP-CHO');

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.update-status', $don), ['status' => 'cancelled'])
            ->assertSessionHas('success');

        $this->assertSame(OrderStatus::Cancelled, $don->fresh()->status);
        $this->assertNull($don->fresh()->cancel_reason);
    }
}
