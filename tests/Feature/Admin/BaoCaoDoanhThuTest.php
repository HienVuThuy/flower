<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\Analytics\KhoangThoiGian;
use App\Services\Analytics\SalesBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Báo cáo doanh thu của lab08, nay nằm trong trang Phân tích. */
class BaoCaoDoanhThuTest extends TestCase
{
    use RefreshDatabase;

    private function don(string $ma, OrderStatus $tt, string $tien, string $luc, string $pt = 'cod'): Order
    {
        $don = Order::create([
            'order_number' => $ma,
            'recipient_name' => 'K',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => $pt,
            'subtotal' => $tien,
            'discount_total' => '0',
            'shipping_fee' => '0',
            'coupon_discount' => '0',
            'grand_total' => $tien,
        ]);
        $don->forceFill(['status' => $tt->value, 'created_at' => Carbon::parse($luc, 'UTC')])->save();

        return $don;
    }

    #[Test]
    public function theo_thoi_gian_chi_tinh_don_da_giao_va_tru_tien_da_hoan(): void
    {
        $giao = $this->don('A', OrderStatus::Completed, '500000', '2026-09-01 03:00:00');
        $this->don('B', OrderStatus::Cancelled, '900000', '2026-09-01 03:00:00');
        $this->don('C', OrderStatus::Pending, '700000', '2026-09-01 03:00:00');

        $hoan = new Refund();
        $hoan->forceFill(['order_id' => $giao->id, 'amount' => '100000', 'status' => 'completed', 'method' => 'tien_mat', 'reason' => 'khac', 'code' => 'HT-TEST-1'])->save();

        $ngay = (new SalesBreakdown())->theoThoiGian()['ngay']->keyBy('ky');

        $this->assertSame(1, $ngay['2026-09-01']['so_don']);
        $this->assertSame('500000.00', $ngay['2026-09-01']['doanh_thu']);
        $this->assertSame('100000.00', $ngay['2026-09-01']['hoan']);
        $this->assertSame('400000.00', $ngay['2026-09-01']['thuan']);
    }

    #[Test]
    public function gom_ngay_theo_gio_viet_nam_roi_gop_thang_va_nam(): void
    {
        $this->don('D1', OrderStatus::Completed, '100000', '2026-08-31 17:30:00');
        $this->don('D2', OrderStatus::Completed, '200000', '2026-09-01 02:00:00');
        $this->don('D3', OrderStatus::Completed, '300000', '2025-12-10 02:00:00');

        $tg = (new SalesBreakdown())->theoThoiGian();

        $ngay = $tg['ngay']->keyBy('ky');
        $this->assertSame(2, $ngay['2026-09-01']['so_don'], '00:30 giờ VN ngày 01/09 thuộc ngày 01/09, không phải 31/08.');
        $this->assertArrayNotHasKey('2026-08-31', $ngay->all());

        $this->assertSame('300000.00', $tg['thang']->keyBy('ky')['2026-09']['doanh_thu']);

        $nam = $tg['nam']->keyBy('ky');
        $this->assertSame('300000.00', $nam['2025']['doanh_thu']);
        $this->assertSame('300000.00', $nam['2026']['doanh_thu']);
    }

    #[Test]
    public function theo_thoi_gian_di_theo_ky_dang_chon(): void
    {
        $this->don('K1', OrderStatus::Completed, '100000', '2026-09-01 02:00:00');
        $this->don('K2', OrderStatus::Completed, '200000', '2026-06-01 02:00:00');

        $tg = (new SalesBreakdown())
            ->trong(new KhoangThoiGian(Carbon::parse('2026-08-01', 'UTC'), Carbon::parse('2026-10-01', 'UTC')))
            ->theoThoiGian();

        $this->assertSame(['2026-09'], $tg['thang']->pluck('ky')->all());
    }

    #[Test]
    public function cac_tab_phan_tich_hien_phan_bao_cao_da_gop(): void
    {
        $this->don('W1', OrderStatus::Completed, '450000', now()->subDay()->toDateTimeString(), 'momo');
        User::factory()->count(2)->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.analytics.sales', ['ky' => 'all']))
            ->assertOk()
            ->assertSee('Theo ngày, tháng, năm')
            ->assertSee('Doanh thu thuần theo tháng')
            ->assertSee('450.000');

        $this->actingAs($admin)
            ->get(route('admin.analytics.index', ['ky' => 'all']))
            ->assertOk()
            ->assertSee('Doanh thu theo hình thức thanh toán');

        $this->actingAs($admin)
            ->get(route('admin.analytics.customers'))
            ->assertOk()
            ->assertSee('Tổng số khách hàng')
            ->assertSee('<span data-tong-khach>2</span>', false);
    }

    #[Test]
    public function muc_bao_cao_doanh_thu_rieng_da_bo(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/bao-cao')
            ->assertNotFound();
    }
}
