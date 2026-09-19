<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\Analytics\RevenueReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Báo cáo doanh thu: bảng số liệu và biểu đồ (lab08). */
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
    public function chi_tinh_don_da_giao_va_tru_tien_da_hoan(): void
    {
        $giao = $this->don('A', OrderStatus::Completed, '500000', '2026-09-01 03:00:00');
        $this->don('B', OrderStatus::Cancelled, '900000', '2026-09-01 03:00:00');
        $this->don('C', OrderStatus::Pending, '700000', '2026-09-01 03:00:00');

        $hoan = new Refund();
        $hoan->forceFill(['order_id' => $giao->id, 'amount' => '100000', 'status' => 'completed', 'method' => 'tien_mat', 'reason' => 'khac', 'code' => 'HT-TEST-1'])->save();

        $tq = app(RevenueReport::class)->tongQuan();

        $this->assertSame(3, $tq['tong_don']);
        $this->assertSame(1, $tq['don_da_giao']);
        $this->assertSame('500000.00', $tq['doanh_thu']);
        $this->assertSame('100000.00', $tq['da_hoan']);
        $this->assertSame('400000.00', $tq['thuan']);
    }

    #[Test]
    public function gom_ngay_theo_gio_viet_nam_roi_gop_thang_va_nam(): void
    {
        $this->don('D1', OrderStatus::Completed, '100000', '2026-08-31 17:30:00');
        $this->don('D2', OrderStatus::Completed, '200000', '2026-09-01 02:00:00');
        $this->don('D3', OrderStatus::Completed, '300000', '2025-12-10 02:00:00');

        $bc = app(RevenueReport::class);

        $ngay = $bc->theoNgay()->keyBy('ky');
        $this->assertSame(2, $ngay['2026-09-01']['so_don'], '00:30 giờ VN ngày 01/09 thuộc ngày 01/09, không phải 31/08.');
        $this->assertArrayNotHasKey('2026-08-31', $ngay->all());

        $thang = $bc->theoThang()->keyBy('ky');
        $this->assertSame('300000.00', $thang['2026-09']['doanh_thu']);

        $nam = $bc->theoNam()->keyBy('ky');
        $this->assertSame('300000.00', $nam['2025']['doanh_thu']);
        $this->assertSame('300000.00', $nam['2026']['doanh_thu']);
    }

    #[Test]
    public function doanh_thu_theo_phuong_thuc_thanh_toan(): void
    {
        $this->don('P1', OrderStatus::Completed, '100000', '2026-09-01 02:00:00', 'cod');
        $this->don('P2', OrderStatus::Completed, '250000', '2026-09-01 02:00:00', 'momo');

        $pt = app(RevenueReport::class)->theoPhuongThuc()->keyBy(fn ($d) => $d['phuong_thuc']->value);

        $this->assertSame('100000.00', $pt['cod']['doanh_thu']);
        $this->assertSame('250000.00', $pt['momo']['doanh_thu']);
        $this->assertSame(0, $pt['tra_gop']['so_don']);
    }

    #[Test]
    public function hai_trang_hien_so_lieu_va_bieu_do_svg_khong_tai_thu_vien_ngoai(): void
    {
        $this->don('W1', OrderStatus::Completed, '450000', now()->subDay()->toDateTimeString());
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Doanh thu theo danh mục')
            ->assertSee('Doanh thu theo tháng')
            ->assertSee('Doanh thu theo năm')
            ->assertSee('450.000');

        $this->actingAs($admin)
            ->get(route('admin.reports.charts'))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('Doanh thu theo phương thức thanh toán')
            ->assertDontSee('chart.js', false);
    }

    #[Test]
    public function nhan_vien_xem_duoc_khach_hang_thi_khong(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('admin.reports.index'))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'customer']))->get(route('admin.reports.index'))->assertForbidden();
    }
}
