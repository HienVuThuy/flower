<?php

namespace Tests\Feature\Pricing;

use App\Enums\OrderStatus;
use App\Enums\PriceSignal;
use App\Enums\UserEventType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserEvent;
use App\Services\Pricing\DemandSignals;
use App\Services\Pricing\PricingAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cố vấn giá cho admin. */
class PricingAdvisorTest extends TestCase
{
    use RefreshDatabase;

    private Category $danhMuc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->danhMuc = Category::factory()->create(['kind' => 'plant', 'is_active' => true]);
    }

    private function sanPham(string $ten, array $ghiDe = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => $ten,
            'category_id' => $this->danhMuc->id,
        ], $ghiDe));
    }

    private function xem(Product $p, int $lan, UserEventType $loai = UserEventType::ProductView): void
    {
        for ($i = 0; $i < $lan; $i++) {
            UserEvent::query()->create([
                'session_id' => 'phien-' . $i,
                'event_type' => $loai,
                'product_id' => $p->id,
                'category_id' => $p->category_id,
                'created_at' => now()->subDays(2),
            ]);
        }
    }

    private function ban(Product $p, int $lan, int $ngayTruoc = 2, OrderStatus $trangThai = OrderStatus::Completed): void
    {
        for ($i = 0; $i < $lan; $i++) {
            $order = Order::create([
                'order_number' => 'FP-TEST-' . strtoupper(bin2hex(random_bytes(4))),
                'recipient_name' => 'Khách thử',
                'recipient_phone' => '0912345678',
                'shipping_address' => '1 Đường Thử',
                'shipping_province' => 'Thành phố Hà Nội',
                'payment_method' => 'cod',
                'subtotal' => '300000.00',
                'discount_total' => '0.00',
                'shipping_fee' => '25000.00',
                'coupon_discount' => '0.00',
                'grand_total' => '325000.00',
            ]);

            $order->status = $trangThai;
            $order->created_at = now()->subDays($ngayTruoc);
            $order->save();

            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $p->id,
                'product_name' => $p->name,
                'quantity' => 1,
                'unit_base_price' => $p->base_price,
                'unit_price' => $p->base_price,
                'line_total' => $p->base_price,
            ]);
        }
    }

    private function deXuat(int $ngay = 30)
    {
        return (new PricingAdvisor(new DemandSignals($ngay)))->suggest()['suggestions'];
    }

    private function tinHieuCho(string $ten): ?PriceSignal
    {
        return $this->deXuat()->first(fn ($s) => $s->product()->name === $ten)?->signal;
    }

    #[Test]
    public function it_luot_xem_thi_KHONG_de_xuat_gi_ca(): void
    {
        $p = $this->sanPham('Món chưa ai thấy', ['stock_quantity' => 3]);
        $this->xem($p, 3);

        $this->assertNull($this->tinHieuCho('Món chưa ai thấy'));
    }

    #[Test]
    public function nhieu_luot_xem_ma_khong_ai_dat_thi_moi_de_xuat(): void
    {
        $p = $this->sanPham('Món nhiều người xem', ['stock_quantity' => 3]);
        $this->xem($p, 40);

        $this->assertSame(PriceSignal::InterestNoSale, $this->tinHieuCho('Món nhiều người xem'));
    }

    #[Test]
    public function moi_de_xuat_deu_mang_theo_con_so_sinh_ra_no(): void
    {
        $p = $this->sanPham('Món nhiều người xem', ['stock_quantity' => 3]);
        $this->xem($p, 40);

        $deXuat = $this->deXuat()->firstOrFail();

        $this->assertNotEmpty($deXuat->evidence);
        $this->assertStringContainsString('40 lượt xem', implode(' | ', $deXuat->evidence));
    }

    #[Test]
    public function them_gio_nhieu_ma_it_don_thi_KHONG_do_cho_gia(): void
    {
        $p = $this->sanPham('Món hay bị bỏ giỏ', ['stock_quantity' => 3]);
        $this->xem($p, 40);
        $this->xem($p, 8, UserEventType::AddToCart);

        $this->assertSame(PriceSignal::CartNotCheckout, $this->tinHieuCho('Món hay bị bỏ giỏ'));
    }

    #[Test]
    public function ton_kho_nam_lau_khong_can_luot_xem(): void
    {
        $p = $this->sanPham('Món nằm kho', [
            'stock_quantity' => 20,
            'created_at' => now()->subDays(120),
        ]);

        $this->xem($p, 2);
        $this->ban($p, 1, ngayTruoc: 80);

        $this->assertSame(PriceSignal::StaleStock, $this->tinHieuCho('Món nằm kho'));
    }

    #[Test]
    public function ton_kho_it_thi_nam_lau_cung_khong_dang_bao(): void
    {
        $p = $this->sanPham('Món gần hết', [
            'stock_quantity' => 2,
            'created_at' => now()->subDays(120),
        ]);

        $this->ban($p, 1, ngayTruoc: 80);

        $this->assertNull($this->tinHieuCho('Món gần hết'));
    }

    #[Test]
    public function don_da_huy_khong_duoc_tinh_la_ban_duoc(): void
    {
        $p = $this->sanPham('Món toàn bị huỷ', ['stock_quantity' => 3]);
        $this->xem($p, 40);
        $this->ban($p, 5, trangThai: OrderStatus::Cancelled);

        $this->assertSame(PriceSignal::InterestNoSale, $this->tinHieuCho('Món toàn bị huỷ'));
    }

    #[Test]
    public function don_dang_giao_van_duoc_tinh_la_ban_duoc(): void
    {
        $p = $this->sanPham('Món đang giao', ['stock_quantity' => 3]);
        $this->xem($p, 40);
        $this->ban($p, 4, trangThai: OrderStatus::Shipping);

        $this->assertNull(
            $this->tinHieuCho('Món đang giao'),
            'Đơn đang giao đang bị bỏ qua — món này thật ra bán được.',
        );
    }

    #[Test]
    public function mau_mong_thi_phai_tu_bao_la_mau_mong(): void
    {
        $p = $this->sanPham('Món bất kỳ');
        $this->ban($p, 2);

        $ket = (new PricingAdvisor(new DemandSignals(30)))->suggest();

        $this->assertTrue($ket['thin_data']);
        $this->assertSame(2, $ket['total_orders']);
    }

    #[Test]
    public function bao_dung_so_san_pham_bi_bo_qua_vi_thieu_du_lieu(): void
    {
        foreach (range(1, 4) as $i) {
            $this->sanPham('Món ít xem ' . $i, ['stock_quantity' => 1]);
        }

        $duXem = $this->sanPham('Món đủ xem', ['stock_quantity' => 3]);
        $this->xem($duXem, 40);

        $ket = (new PricingAdvisor(new DemandSignals(30)))->suggest();

        $this->assertSame(5, $ket['examined']);
        $this->assertSame(4, $ket['skipped_too_few_views']);
        $this->assertCount(1, $ket['suggestions']);
    }

    #[Test]
    public function mat_bang_gia_chi_tinh_tu_hang_DA_BAN_DUOC(): void
    {
        foreach ([100000, 200000, 300000] as $i => $gia) {
            $p = $this->sanPham('Đã bán ' . $i, ['base_price' => $gia . '.00']);
            $this->ban($p, 1);
        }

        foreach (range(1, 3) as $i) {
            $this->sanPham('Chưa bán bao giờ ' . $i, ['base_price' => '9000000.00', 'stock_quantity' => 1]);
        }

        $ket = $this->sanPham('Món cần xét', ['base_price' => '150000.00', 'stock_quantity' => 3]);
        $this->xem($ket, 40);

        $deXuat = $this->deXuat()->first(fn ($s) => $s->product()->name === 'Món cần xét');

        $this->assertNotNull($deXuat);
        $this->assertNotNull($deXuat->anchor);
        $this->assertStringContainsString('200.000', $deXuat->anchor);
    }

    #[Test]
    public function duoi_ba_san_pham_da_ban_thi_khong_dua_ra_mat_bang(): void
    {
        foreach ([100000, 300000] as $i => $gia) {
            $p = $this->sanPham('Đã bán ' . $i, ['base_price' => $gia . '.00']);
            $this->ban($p, 1);
        }

        $ket = $this->sanPham('Món cần xét', ['base_price' => '150000.00', 'stock_quantity' => 3]);
        $this->xem($ket, 40);

        $deXuat = $this->deXuat()->first(fn ($s) => $s->product()->name === 'Món cần xét');

        $this->assertNotNull($deXuat);
        $this->assertNull($deXuat->anchor, 'Đã dựng mặt bằng giá từ chỉ hai sản phẩm.');
    }

    #[Test]
    public function trang_de_xuat_gia_chi_admin_moi_vao_duoc(): void
    {
        $this->get('/admin/de-xuat-gia')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/admin/de-xuat-gia')
            ->assertForbidden();
    }
}
