<?php

namespace Tests\Feature\Gift;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\GiftItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\CashFlowReport;
use App\Services\Analytics\InventoryReport;
use App\Services\Analytics\KhoangThoiGian;
use App\Services\Analytics\ProfitReport;
use App\Services\Catalog\SocialProof;
use App\Services\Pricing\DemandSignals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Dòng quà tặng không phải hàng bán: không vào "đã bán", bán chạy, tốc độ bán, nhu cầu cho Đề xuất giá, lãi… */
class BaoCaoKhongDemQuaTest extends TestCase
{
    use RefreshDatabase;

    private Product $senDa;

    private Product $phanBon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-14 03:00:00', 'UTC'));

        $this->senDa = Product::factory()->for(Category::factory())->create(['name' => 'Sen đá A', 'status' => 'active']);
        $this->phanBon = Product::factory()->for(Category::factory())->create(['name' => 'Túi phân bón mini', 'status' => 'active']);

        $phieu = StockReceipt::create(['code' => 'NK-QUA-1', 'supplier' => 'Vựa thử', 'received_at' => '2026-09-01']);
        $phieu->forceFill(['status' => 'posted', 'kind' => 'nhap_moi'])->save();
        $phieu->items()->create(['product_id' => $this->phanBon->id, 'product_name' => 'Túi phân bón mini', 'quantity' => 50, 'unit_cost' => '10000.00']);

        $don = Order::create([
            'order_number' => 'FP-BCQ-1', 'recipient_name' => 'Khách', 'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường', 'shipping_province' => 'Thành phố Hà Nội', 'payment_method' => 'cod',
            'subtotal' => '200000.00', 'discount_total' => '0.00', 'shipping_fee' => '0.00', 'coupon_discount' => '0.00', 'grand_total' => '200000.00',
        ]);
        $don->forceFill(['status' => OrderStatus::Completed, 'payment_status' => PaymentStatus::Paid, 'created_at' => now()->subDays(2)])->save();

        $hang = $don->items()->create([
            'product_id' => $this->senDa->id, 'product_name' => 'Sen đá A', 'quantity' => 2,
            'unit_base_price' => '100000.00', 'unit_price' => '100000.00', 'line_total' => '200000.00',
        ]);

        $vat = GiftItem::create(['name' => 'Túi phân bón mini', 'kind' => 'do_vat', 'product_id' => $this->phanBon->id, 'is_active' => true]);

        $don->items()->create([
            'product_id' => $this->phanBon->id, 'product_name' => 'Túi phân bón mini', 'quantity' => 3,
            'unit_base_price' => '15000.00', 'unit_price' => '0.00', 'line_total' => '0.00',
            'is_gift' => true, 'gift_item_id' => $vat->id, 'parent_item_id' => $hang->id,
        ]);
    }

    #[Test]
    public function da_ban_tren_trang_san_pham_va_quan_tri_khong_dem_qua(): void
    {
        $dv = app(SocialProof::class);
        $this->assertSame(2, $dv->banGanDay($this->senDa));
        $this->assertSame(0, $dv->banGanDay($this->phanBon));

        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        $this->actingAs($admin)->get(route('admin.products.show', $this->phanBon))
            ->assertOk()
            ->assertViewHas('banHang', fn ($b) => (int) $b->so_luong === 0);
    }

    #[Test]
    public function ban_chay_toc_do_ban_va_nhu_cau_de_xuat_gia_khong_dem_qua(): void
    {
        $banChay = app(AnalyticsService::class)->forPeriod('30')->bestSellers()->pluck('name')->all();
        $this->assertContains('Sen đá A', $banChay);
        $this->assertNotContains('Túi phân bón mini', $banChay);

        $toc = (new \ReflectionMethod(InventoryReport::class, 'daBanTrongKy'))->invoke(app(InventoryReport::class)->trongVong(30));
        $this->assertSame(2, $toc[$this->senDa->id . ':0'] ?? null);
        $this->assertArrayNotHasKey($this->phanBon->id . ':0', $toc->all());

        $nhuCau = new DemandSignals(30);
        $banRa = (new \ReflectionMethod(DemandSignals::class, 'banRa'))->invoke($nhuCau, [$this->phanBon->id, $this->senDa->id], now()->subDays(30));
        $this->assertArrayNotHasKey($this->phanBon->id, $banRa);
        $this->assertSame(2, $banRa[$this->senDa->id]['qty']);

        $ganNhat = (new \ReflectionMethod(DemandSignals::class, 'banGanNhat'))->invoke($nhuCau, [$this->phanBon->id]);
        $this->assertArrayNotHasKey($this->phanBon->id, $ganNhat, 'Lần tặng gần nhất không phải lần bán gần nhất');
    }

    #[Test]
    public function gia_von_qua_la_khoan_rieng_khong_nam_trong_lai_theo_san_pham(): void
    {
        $bao = app(ProfitReport::class)->trong(new KhoangThoiGian(now()->subDays(30), now()->addDay()))->baoCao();

        $this->assertSame('30000.00', $bao['chi_phi_qua']['tien'], '3 túi × 10.000đ');
        $this->assertSame(0, $bao['chi_phi_qua']['so_dong_chua_gia']);
        $this->assertNotContains('Túi phân bón mini', $bao['theo_san_pham']->pluck('ten')->all());
        $this->assertNotContains('Túi phân bón mini', $bao['can_nhap_gia_von']->pluck('ten')->all());

        $thang = app(CashFlowReport::class)->thang('2026-09');
        $this->assertSame('30000.00', $thang['lai']['chi_phi_qua']);
        $this->assertSame(bcsub($thang['lai']['lai_gop_hang'], '30000.00', 2), $thang['lai']['lai_rong']);
    }
}
