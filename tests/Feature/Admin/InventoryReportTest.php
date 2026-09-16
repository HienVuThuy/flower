<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Analytics\InventoryReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Báo cáo tồn kho. */
class InventoryReportTest extends TestCase
{
    use RefreshDatabase;

    private function hang(string $ten, int $ton, string $gia = '100000.00', string $trangThai = 'active'): Product
    {
        return Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price($gia)
            ->create([
                'name' => $ten,
                'status' => $trangThai,
                'track_inventory' => true,
                'stock_quantity' => $ton,
                'weight' => 500,
            ]);
    }

    private function daBan(Product $p, int $soLuong, ?ProductVariant $v = null, int $ngayTruoc = 1): void
    {
        $order = Order::create([
            'order_number' => 'FP-TON-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => '0',
            'discount_total' => '0',
            'coupon_discount' => '0',
            'shipping_fee' => '0',
            'grand_total' => '0',
        ]);

        $order->forceFill([
            'status' => OrderStatus::Completed->value,
            'created_at' => now()->subDays($ngayTruoc),
        ])->save();

        $order->items()->create([
            'product_id' => $p->id,
            'product_variant_id' => $v?->id,
            'product_name' => $p->name,
            'unit_base_price' => $p->base_price,
            'unit_price' => $p->base_price,
            'quantity' => $soLuong,
            'line_total' => '0',
        ]);
    }

    private function bao(int $ngay = 30): InventoryReport
    {
        return app(InventoryReport::class)->trongVong($ngay);
    }

    #[Test]
    public function sap_het_xep_theo_so_ngay_con_ban_duoc_chu_khong_theo_so_luong(): void
    {
        $gap = $this->hang('Gấp', 20);
        $duDung = $this->hang('Đủ dùng', 2);
        $thua = $this->hang('Thừa', 4);

        $this->daBan($gap, 600);
        $this->daBan($duDung, 6);
        $this->daBan($thua, 2);

        $ds = $this->bao()->sapHet(nguong: 14);

        $this->assertSame(
            ['Gấp', 'Đủ dùng'],
            $ds->pluck('name')->all(),
            'Đang xếp theo số lượng thay vì theo số ngày còn bán được.',
        );

        $this->assertNotContains('Thừa', $ds->pluck('name')->all());
    }

    #[Test]
    public function chua_ban_duoc_cai_nao_thi_cover_la_NULL_chu_khong_phai_so_lon(): void
    {
        $this->hang('Không ai mua', 7);

        $dong = $this->bao()->rows()->firstWhere('name', 'Không ai mua');

        $this->assertNull($dong['cover']);
        $this->assertSame(0, $dong['sold']);
    }

    #[Test]
    public function hang_chua_ban_duoc_KHONG_nam_trong_muc_sap_het(): void
    {
        $this->hang('Không ai mua', 1);

        $this->assertTrue($this->bao()->sapHet()->isEmpty());
        $this->assertSame('Không ai mua', $this->bao()->chetVon()->first()['name']);
    }

    #[Test]
    public function moi_quy_cach_la_mot_dong_kho_rieng(): void
    {
        $p = $this->hang('Lưỡi hổ mini', 0);

        foreach ([['Chậu sứ', 0], ['Chậu gốm', 12]] as [$ten, $ton]) {
            ProductVariant::create([
                'product_id' => $p->id,
                'name' => $ten,
                'price' => '180000.00',
                'is_active' => true,
                'track_inventory' => true,
                'stock_quantity' => $ton,
            ]);
        }

        $dong = $this->bao()->rows()->where('name', 'Lưỡi hổ mini');

        $this->assertCount(2, $dong);
        $this->assertSame([0, 12], $dong->pluck('stock')->sort()->values()->all());
    }

    #[Test]
    public function hang_KHONG_theo_doi_ton_thi_khong_nam_trong_bao_cao(): void
    {
        $this->hang('Có theo dõi', 5);

        Product::factory()
            ->for(Category::factory())
            ->price('50000.00')
            ->create(['name' => 'Không theo dõi', 'track_inventory' => false, 'stock_quantity' => 0]);

        $ten = $this->bao()->rows()->pluck('name')->all();

        $this->assertContains('Có theo dõi', $ten);
        $this->assertNotContains('Không theo dõi', $ten);
    }

    #[Test]
    public function don_chua_giao_KHONG_tinh_vao_toc_do_ban(): void
    {
        $p = $this->hang('Cây thử', 10);

        $order = Order::create([
            'order_number' => 'FP-CHUA-GIAO',
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => '0', 'discount_total' => '0', 'coupon_discount' => '0',
            'shipping_fee' => '0', 'grand_total' => '0',
        ]);

        $order->forceFill(['status' => OrderStatus::Shipping->value])->save();

        $order->items()->create([
            'product_id' => $p->id,
            'product_name' => $p->name,
            'unit_base_price' => '100000.00',
            'unit_price' => '100000.00',
            'quantity' => 300,
            'line_total' => '0',
        ]);

        $this->assertSame(0, $this->bao()->rows()->firstWhere('name', 'Cây thử')['sold']);
    }

    #[Test]
    public function don_ngoai_ky_KHONG_tinh_vao_toc_do_ban(): void
    {
        $p = $this->hang('Cây thử', 10);
        $this->daBan($p, 300, ngayTruoc: 60);

        $this->assertSame(0, $this->bao(30)->rows()->firstWhere('name', 'Cây thử')['sold']);
        $this->assertSame(300, $this->bao(90)->rows()->firstWhere('name', 'Cây thử')['sold']);
    }

    #[Test]
    public function het_hang_ma_van_bay_ban_thi_vao_muc_dang_mat_don(): void
    {
        $p = $this->hang('Hết mà vẫn bán', 0);
        $this->daBan($p, 20);

        $this->assertSame('Hết mà vẫn bán', $this->bao()->daHet()->first()['name']);
    }

    #[Test]
    public function het_hang_nhung_DA_AN_thi_khong_tinh_la_mat_don(): void
    {
        $this->hang('Hết và đã ẩn', 0, trangThai: 'draft');

        $this->assertTrue($this->bao()->daHet()->isEmpty());
        $this->assertSame(0, $this->bao()->tongQuan()['out']);
    }

    #[Test]
    public function gia_tri_ton_kho_tinh_theo_gia_ban(): void
    {
        $this->hang('Cây A', 4, '250000.00');
        $this->hang('Cây B', 2, '100000.00');

        $this->assertEqualsWithDelta(1_200_000, $this->bao()->tongQuan()['value'], 0.01);
    }

    #[Test]
    public function trang_ton_kho_noi_ro_day_KHONG_phai_gia_von(): void
    {
        $this->hang('Cây A', 4, '250000.00');

        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        $this->actingAs($admin)
            ->get('/admin/ton-kho')
            ->assertOk()
            ->assertSee('Giá trị tồn kho')
            ->assertSee('không phải giá vốn', escape: false)
            ->assertSee('chưa tính được lãi/lỗ', escape: false);
    }

    #[Test]
    public function khach_va_nguoi_dung_thuong_khong_vao_duoc_trang_ton_kho(): void
    {
        $this->get('/admin/ton-kho')->assertRedirect();

        $this->actingAs(User::factory()->create())
            ->get('/admin/ton-kho')
            ->assertForbidden();
    }
}
