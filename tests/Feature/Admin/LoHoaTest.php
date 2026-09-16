<?php

namespace Tests\Feature\Admin;

use App\Enums\FlowerLotStatus;
use App\Enums\FlowerQuality;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\FlowerKind;
use App\Models\FlowerLot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Analytics\FlowerCostReport;
use App\Services\Analytics\KhoangThoiGian;
use App\Services\Inventory\FlowerLotException;
use App\Services\Inventory\FlowerLotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Hoa tươi: đếm theo LÔ, không đếm theo cành. */
class LoHoaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function loai(string $ten = 'Hồng đỏ'): FlowerKind
    {
        return FlowerKind::create(['name' => $ten, 'default_unit' => 'bo']);
    }

    private function lo(array $ghiDe = []): FlowerLot
    {
        $lo = new FlowerLot();

        $lo->forceFill(array_merge([
            'code' => 'LH-' . uniqid(),
            'flower_kind_id' => $this->loai('Hồng ' . uniqid())->id,
            'purchased_at' => now()->subDays(3)->toDateString(),
            'quantity' => '10.00',
            'unit' => 'bo',
            'total_cost' => '1000000.00',
            'status' => FlowerLotStatus::DangDung,
        ], $ghiDe))->save();

        return $lo->fresh();
    }

    private function dichVu(): FlowerLotService
    {
        return app(FlowerLotService::class);
    }

    private function bao(): array
    {
        return app(FlowerCostReport::class)
            ->trong(new KhoangThoiGian(KhoangThoiGian::nuaDemTruoc(29), null))
            ->baoCao();
    }

    #[Test]
    public function lo_chua_dong_thi_tien_CHUA_vao_gia_von(): void
    {
        $this->lo(['total_cost' => '1000000.00']);

        $b = $this->bao();

        $this->assertSame('0.00', $b['gia_von']);
        $this->assertSame(0, $b['so_lo_dong']);
        $this->assertSame(1, $b['so_lo_con_mo']);
        $this->assertSame('1000000.00', $b['tien_lo_con_mo']);
    }

    #[Test]
    public function ky_TOAN_BO_cung_khong_dem_lo_chua_dong(): void
    {
        $this->doanhThuHoa('500000.00');
        $this->lo(['total_cost' => '1000000.00']);

        $b = app(FlowerCostReport::class)
            ->trong(new KhoangThoiGian())
            ->baoCao();

        $this->assertSame('0.00', $b['gia_von'], 'Kỳ "Toàn bộ" đang đếm cả lô chưa đóng');
        $this->assertSame(0, $b['so_lo_dong']);
        $this->assertNull($b['lai_gop']);
    }

    #[Test]
    public function dong_lo_roi_thi_tien_vao_gia_von_cua_KY_DONG(): void
    {
        $lo = $this->lo([
            'purchased_at' => now()->subDays(40)->toDateString(),
            'total_cost' => '1000000.00',
        ]);

        $this->dichVu()->dongLo($lo, haoHut: 0);

        $b = $this->bao();

        $this->assertSame('1000000.00', $b['gia_von']);
        $this->assertSame(1, $b['so_lo_dong']);
    }

    #[Test]
    public function lo_dong_o_ky_TRUOC_khong_tinh_vao_ky_nay(): void
    {
        $lo = $this->lo(['purchased_at' => now()->subDays(60)->toDateString()]);

        $lo->forceFill([
            'status' => FlowerLotStatus::DaDong,
            'closed_at' => now()->subDays(45),
        ])->save();

        $b = $this->bao();

        $this->assertSame('0.00', $b['gia_von']);
        $this->assertSame(0, $b['so_lo_dong']);
    }

    #[Test]
    public function chua_dong_lo_nao_thi_lai_gop_la_NULL_chu_khong_bang_doanh_thu(): void
    {
        $this->doanhThuHoa('500000.00');
        $this->lo();

        $b = $this->bao();

        $this->assertSame('500000.00', $b['doanh_thu']);
        $this->assertNull($b['lai_gop']);
    }

    #[Test]
    public function co_lo_dong_thi_lai_gop_la_doanh_thu_tru_gia_von(): void
    {
        $this->doanhThuHoa('500000.00');

        $lo = $this->lo(['total_cost' => '300000.00']);
        $this->dichVu()->dongLo($lo);

        $b = $this->bao();

        $this->assertSame('200000.00', $b['lai_gop']);
    }

    #[Test]
    public function doanh_thu_hoa_chi_tinh_san_pham_LA_HOA(): void
    {
        $this->doanhThuHoa('500000.00');
        $this->doanhThuHoa('900000.00', ProductType::Plant);

        $this->assertSame('500000.00', $this->bao()['doanh_thu']);
    }

    #[Test]
    public function khong_dong_lo_hai_lan(): void
    {
        $lo = $this->lo();
        $this->dichVu()->dongLo($lo);

        $this->expectException(FlowerLotException::class);
        $this->expectExceptionMessageMatches('/đã đóng rồi/iu');

        $this->dichVu()->dongLo($lo);
    }

    #[Test]
    public function hao_hut_khong_vuot_qua_so_da_mua(): void
    {
        $lo = $this->lo(['quantity' => '10.00']);

        $this->expectException(FlowerLotException::class);
        $this->expectExceptionMessageMatches('/không thể lớn hơn/iu');

        $this->dichVu()->dongLo($lo, haoHut: 12);
    }

    #[Test]
    public function hao_hut_am_bi_tu_choi(): void
    {
        $lo = $this->lo();

        $this->expectException(FlowerLotException::class);

        $this->dichVu()->dongLo($lo, haoHut: -1);
    }

    #[Test]
    public function hao_hut_KHONG_lam_giam_gia_von(): void
    {
        $lo = $this->lo(['quantity' => '10.00', 'total_cost' => '1000000.00']);

        $this->dichVu()->dongLo($lo, haoHut: 4);

        $this->assertSame('1000000.00', $this->bao()['gia_von']);
        $this->assertSame(40.0, $lo->fresh()->tiLeHaoHut());
    }

    #[Test]
    public function hao_hut_trung_binh_tinh_theo_TONG_so_luong(): void
    {
        $a = $this->lo(['quantity' => '2.00']);
        $b = $this->lo(['quantity' => '200.00']);

        $this->dichVu()->dongLo($a, haoHut: 2);
        $this->dichVu()->dongLo($b, haoHut: 1);

        $this->assertSame(1.5, $this->bao()['hao_hut_trung_binh']);
    }

    #[Test]
    public function lo_mo_qua_lau_duoc_nhac(): void
    {
        $this->lo(['purchased_at' => now()->subDays(2)->toDateString()]);
        $qua = $this->lo(['purchased_at' => now()->subDays(20)->toDateString()]);

        $nhac = $this->dichVu()->loQuenDong();

        $this->assertCount(1, $nhac);
        $this->assertSame($qua->id, $nhac->first()->id);

        $this->assertSame(1, $this->bao()['lo_qua_han']);
    }

    #[Test]
    public function lo_da_dong_thi_khong_con_bi_nhac(): void
    {
        $lo = $this->lo(['purchased_at' => now()->subDays(20)->toDateString()]);
        $this->dichVu()->dongLo($lo);

        $this->assertCount(0, $this->dichVu()->loQuenDong());
        $this->assertSame(0, $this->bao()['lo_qua_han']);
    }

    #[Test]
    public function don_gia_la_tien_chia_so_luong_va_khong_chia_cho_0(): void
    {
        $lo = $this->lo(['quantity' => '8.00', 'total_cost' => '1200000.00']);

        $this->assertSame('150000.00', $lo->donGia());

        $khong = $this->lo(['quantity' => '0.00']);

        $this->assertNull($khong->donGia());
        $this->assertNull($khong->tiLeHaoHut());
    }

    #[Test]
    public function ghi_lo_va_dong_lo_qua_giao_dien(): void
    {
        $admin = $this->admin();
        $loai = $this->loai('Cúc vàng');
        $ncc = Supplier::create(['name' => 'Vựa Quảng Bá', 'kind' => 'vua']);

        $this->actingAs($admin)->post('/admin/lo-hoa', [
            'flower_kind_id' => $loai->id,
            'supplier_id' => $ncc->id,
            'purchased_at' => now()->subDay()->toDateString(),
            'quantity' => '12.5',
            'unit' => 'kg',
            'total_cost' => '750000',
            'quality' => FlowerQuality::Tot->value,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $lo = FlowerLot::firstOrFail();

        $this->assertSame('Vựa Quảng Bá', $lo->supplier_name);
        $this->assertSame('12.50', $lo->quantity);

        $this->actingAs($admin)
            ->patch('/admin/lo-hoa/' . $lo->id . '/dong', ['hao_hut' => '2.5'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertTrue($lo->fresh()->daDong());
        $this->assertSame('2.50', $lo->fresh()->hao_hut);

        $this->actingAs($admin)->get('/admin/lo-hoa')->assertOk();
    }

    #[Test]
    public function so_luong_va_tien_phai_lon_hon_0(): void
    {
        $loai = $this->loai();

        foreach ([['quantity' => '0'], ['total_cost' => '0']] as $xau) {
            $res = $this->actingAs($this->admin())->post('/admin/lo-hoa', array_merge([
                'flower_kind_id' => $loai->id,
                'purchased_at' => now()->toDateString(),
                'quantity' => '10',
                'unit' => 'bo',
                'total_cost' => '100000',
            ], $xau));

            $res->assertSessionHasErrors(array_key_first($xau));
        }

        $this->assertSame(0, FlowerLot::count());
    }

    #[Test]
    public function ten_loai_hoa_khong_duoc_trung(): void
    {
        $this->actingAs($this->admin())->post('/admin/loai-hoa', [
            'name' => 'Hồng đỏ',
            'default_unit' => 'bo',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin())->post('/admin/loai-hoa', [
            'name' => 'Hồng đỏ',
            'default_unit' => 'bo',
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, FlowerKind::count());
    }

    #[Test]
    public function hoa_tuoi_KHONG_hien_o_o_chon_cua_phieu_nhap(): void
    {
        $hoa = Product::factory()->for(Category::factory())->stock(10)
            ->create(['name' => 'Bó tulip Hà Lan', 'product_type' => ProductType::Flower]);

        $chau = Product::factory()->for(Category::factory())->stock(10)
            ->create(['name' => 'Chậu sứ trắng', 'product_type' => ProductType::Other]);

        $html = $this->actingAs($this->admin())
            ->get('/admin/nhap-kho/create')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="' . $chau->id . ':"', $html);
        $this->assertStringNotContainsString('value="' . $hoa->id . ':"', $html);
    }

    #[Test]
    public function gui_thang_hoa_vao_phieu_nhap_thi_bi_bo_qua(): void
    {
        $hoa = Product::factory()->for(Category::factory())->stock(10)
            ->create(['product_type' => ProductType::Flower]);

        $chau = Product::factory()->for(Category::factory())->stock(10)
            ->create(['product_type' => ProductType::Other]);

        $this->actingAs($this->admin())->post('/admin/nhap-kho', [
            'received_at' => now()->toDateString(),
            'items' => [
                ['mat_hang' => (string) $hoa->id, 'quantity' => 5, 'unit_cost' => 100000],
                ['mat_hang' => (string) $chau->id, 'quantity' => 3, 'unit_cost' => 200000],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $phieu = \App\Models\StockReceipt::firstOrFail();

        $this->assertSame(1, $phieu->items()->count(), 'Dòng hoa đã lọt vào phiếu nhập');
        $this->assertSame($chau->id, $phieu->items()->first()->product_id);
    }

    #[Test]
    public function hoa_tuoi_KHONG_hien_o_trang_khai_ton_dau_ky(): void
    {
        $hoa = Product::factory()->for(Category::factory())->stock(10)
            ->create(['product_type' => ProductType::Flower]);

        $chau = Product::factory()->for(Category::factory())->stock(10)
            ->create(['product_type' => ProductType::Other]);

        $html = $this->actingAs($this->admin())
            ->get('/admin/ton-dau-ky')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('items[' . $chau->id . ']', $html);
        $this->assertStringNotContainsString('items[' . $hoa->id . ']', $html);
    }

    #[Test]
    public function kiem_ke_thi_VAN_nhan_hoa(): void
    {
        $hoa = Product::factory()->for(Category::factory())->stock(10)
            ->create(['product_type' => ProductType::Flower]);

        $html = $this->actingAs($this->admin())
            ->get('/admin/kiem-ke/create')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="dem[' . $hoa->id . ':]', $html);
    }

    private function doanhThuHoa(string $tien, ProductType $loai = ProductType::Flower): void
    {
        $sp = Product::factory()
            ->for(Category::factory())
            ->create(['product_type' => $loai, 'track_inventory' => false]);

        $don = new Order();
        $don->forceFill([
            'order_number' => 'KT-' . uniqid(),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => $tien,
            'grand_total' => $tien,
            'status' => OrderStatus::Completed,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->save();

        (new OrderItem())->forceFill([
            'order_id' => $don->id,
            'product_id' => $sp->id,
            'product_name' => $sp->name,
            'unit_base_price' => $tien,
            'unit_price' => $tien,
            'quantity' => 1,
            'line_total' => $tien,
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
        ])->save();
    }
}
