<?php

namespace Tests\Feature\Admin;

use App\Enums\FlowerLotStatus;
use App\Enums\ReturnReason;
use App\Enums\ReturnSettlement;
use App\Enums\StockReceiptKind;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\FlowerKind;
use App\Models\FlowerLot;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Analytics\KhoangThoiGian;
use App\Services\Analytics\PurchasingReport;
use App\Services\Inventory\StockReceiptService;
use App\Services\Inventory\SupplierReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Phân tích thu mua: lấy hàng ở đâu thì ĐÁNG TIỀN nhất. */
class ThuMuaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(UserRole $vaiTro = UserRole::Admin): User
    {
        $u = User::factory()->create();
        $u->role = $vaiTro;
        $u->save();

        return $u;
    }

    private function ncc(string $ten): Supplier
    {
        return Supplier::create(['name' => $ten, 'kind' => 'vua']);
    }

    private function lo(FlowerKind $loai, ?Supplier $ncc, array $ghiDe = []): FlowerLot
    {
        $lo = new FlowerLot();

        $lo->forceFill(array_merge([
            'code' => 'LH-' . uniqid(),
            'flower_kind_id' => $loai->id,
            'supplier_id' => $ncc?->id,
            'supplier_name' => $ncc?->name,
            'purchased_at' => now()->subDays(3)->toDateString(),
            'quantity' => '100.00',
            'unit' => 'bo',
            'total_cost' => '5000000.00',
            'hao_hut' => '0.00',
            'status' => FlowerLotStatus::DaDong,
            'closed_at' => now(),
        ], $ghiDe))->save();

        return $lo->fresh();
    }

    private function bao(?KhoangThoiGian $k = null): PurchasingReport
    {
        return app(PurchasingReport::class)->trong($k ?? new KhoangThoiGian());
    }

    private function nhap(Product $sp, ?Supplier $ncc, int $sl, string $gia, ?string $ngay = null): StockReceipt
    {
        $this->actingAs($this->admin())->post('/admin/nhap-kho', [
            'received_at' => $ngay ?? now()->subDays(5)->toDateString(),
            'supplier_id' => $ncc?->id,
            'items' => [['mat_hang' => (string) $sp->id, 'quantity' => $sl, 'unit_cost' => $gia]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $p = StockReceipt::where('kind', StockReceiptKind::NhapMoi->value)->latest('id')->firstOrFail();
        app(StockReceiptService::class)->ghiSo($p);

        return $p->fresh('items');
    }

    #[Test]
    public function vua_re_ma_hong_nhieu_KHONG_dung_dau_bang(): void
    {
        $loai = FlowerKind::create(['name' => 'Hồng đỏ', 'default_unit' => 'bo']);
        $a = $this->ncc('Vựa A');
        $b = $this->ncc('Vựa B');

        $this->lo($loai, $a, ['total_cost' => '5000000.00', 'hao_hut' => '20.00']);
        $this->lo($loai, $b, ['total_cost' => '5500000.00', 'hao_hut' => '5.00']);

        $g = $this->bao()->hoa()->first();
        [$dau, $sau] = $g['nguon'];

        $this->assertSame('Vựa B', $dau['ten'], 'Đứng đầu phải là chỗ đáng tiền nhất');
        $this->assertSame('57894.73', $dau['gia_dung_duoc']);
        $this->assertSame('62500.00', $sau['gia_dung_duoc']);

        $this->assertSame('50000.00', $sau['don_gia']);
        $this->assertSame('Vựa A', $g['re_nhat']);
        $this->assertSame('Vựa B', $g['dang_tien_nhat']);
        $this->assertTrue($g['khac_nhau']);
    }

    #[Test]
    public function mot_nguon_thi_khong_noi_re_nhat_khac_dang_tien(): void
    {
        $loai = FlowerKind::create(['name' => 'Cúc', 'default_unit' => 'bo']);
        $this->lo($loai, $this->ncc('Chợ Quảng Bá'), ['hao_hut' => '30.00']);

        $this->assertFalse($this->bao()->hoa()->first()['khac_nhau']);
    }

    #[Test]
    public function hai_don_vi_la_HAI_NHOM_khong_bao_gio_tron(): void
    {
        $loai = FlowerKind::create(['name' => 'Baby', 'default_unit' => 'bo']);
        $n = $this->ncc('Vựa Đà Lạt');

        $this->lo($loai, $n, ['unit' => 'bo', 'quantity' => '10.00', 'total_cost' => '500000.00']);
        $this->lo($loai, $n, ['unit' => 'kg', 'quantity' => '2.00', 'total_cost' => '300000.00']);

        $nhom = $this->bao()->hoa();

        $this->assertCount(2, $nhom);
        $this->assertEqualsCanonicalizing(
            ['50000.00', '150000.00'],
            $nhom->map(fn ($g) => $g['nguon'][0]['don_gia'])->all(),
        );
    }

    #[Test]
    public function don_gia_la_BINH_QUAN_GIA_QUYEN_khong_phai_trung_binh_cac_lan(): void
    {
        $loai = FlowerKind::create(['name' => 'Tulip', 'default_unit' => 'bo']);
        $n = $this->ncc('Vựa X');

        $this->lo($loai, $n, ['quantity' => '2.00', 'total_cost' => '200000.00']);
        $this->lo($loai, $n, ['quantity' => '98.00', 'total_cost' => '4900000.00']);

        $this->assertSame('51000.00', $this->bao()->hoa()->first()['nguon'][0]['don_gia']);
    }

    #[Test]
    public function hoa_tra_lai_CO_hoan_tien_thi_tru_ca_tien_lan_so_dung_duoc(): void
    {
        $loai = FlowerKind::create(['name' => 'Ly', 'default_unit' => 'bo']);
        $lo = $this->lo($loai, $this->ncc('Vựa L'), ['status' => FlowerLotStatus::DangDung, 'closed_at' => null]);

        app(SupplierReturnService::class)->traHangHoa($lo, [
            'quantity' => 10, 'reason' => ReturnReason::HangHong->value, 'settlement' => ReturnSettlement::HoanTien->value,
        ]);

        $n = $this->bao()->hoa()->first()['nguon'][0];

        $this->assertSame(10.0, $n['ti_le_tra']);
        $this->assertSame('50000.00', $n['gia_dung_duoc']);
    }

    #[Test]
    public function hoa_tra_lai_KHONG_duoc_gi_thi_gia_dung_duoc_TANG(): void
    {
        $loai = FlowerKind::create(['name' => 'Lan', 'default_unit' => 'bo']);
        $lo = $this->lo($loai, $this->ncc('Vựa M'), ['status' => FlowerLotStatus::DangDung, 'closed_at' => null]);

        app(SupplierReturnService::class)->traHangHoa($lo, [
            'quantity' => 10, 'reason' => ReturnReason::HangHong->value, 'settlement' => ReturnSettlement::KhongDuocGi->value,
        ]);

        $this->assertSame('55555.55', $this->bao()->hoa()->first()['nguon'][0]['gia_dung_duoc']);
    }

    #[Test]
    public function hong_sach_thi_gia_dung_duoc_la_NULL_khong_phai_so_to_hay_0(): void
    {
        $loai = FlowerKind::create(['name' => 'Cẩm tú cầu', 'default_unit' => 'bo']);
        $this->lo($loai, $this->ncc('Vựa H'), ['quantity' => '10.00', 'hao_hut' => '10.00']);

        $this->assertNull($this->bao()->hoa()->first()['nguon'][0]['gia_dung_duoc']);
    }

    #[Test]
    public function hoa_bang_gia_thi_KHONG_co_quan_quan(): void
    {
        $loai = FlowerKind::create(['name' => 'Đồng tiền', 'default_unit' => 'bo']);
        $this->lo($loai, $this->ncc('Vựa 1'));
        $this->lo($loai, $this->ncc('Vựa 2'));

        $g = $this->bao()->hoa()->first();

        $this->assertNull($g['re_nhat']);
        $this->assertNull($g['dang_tien_nhat']);
        $this->assertFalse($g['khac_nhau']);
    }

    #[Test]
    public function ten_go_tay_khac_hoa_thuong_va_khoang_trang_van_la_MOT_nguon(): void
    {
        $loai = FlowerKind::create(['name' => 'Hướng dương', 'default_unit' => 'bo']);
        $this->lo($loai, null, ['supplier_name' => 'Vựa  Bình']);
        $this->lo($loai, null, ['supplier_name' => 'vựa bình ']);

        $g = $this->bao()->hoa()->first();

        $this->assertCount(1, $g['nguon']);
        $this->assertSame(2, $g['nguon'][0]['so_lan']);
    }

    #[Test]
    public function lan_mua_khong_ghi_nguon_duoc_DEM_va_noi_ra(): void
    {
        $loai = FlowerKind::create(['name' => 'Cát tường', 'default_unit' => 'bo']);
        $this->lo($loai, null);

        $this->assertSame(1, $this->bao()->thieuNguon()['lo_hoa']);
        $this->assertNull($this->bao()->hoa()->first()['nguon'][0]['ten']);
    }

    #[Test]
    public function it_hon_ba_lan_mua_thi_danh_dau_it_du_lieu(): void
    {
        $loai = FlowerKind::create(['name' => 'Mẫu đơn', 'default_unit' => 'bo']);
        $n = $this->ncc('Vựa P');

        $this->lo($loai, $n);
        $this->lo($loai, $n);
        $this->assertTrue($this->bao()->hoa()->first()['nguon'][0]['mong']);

        $this->lo($loai, $n);
        $this->assertFalse($this->bao()->hoa()->first()['nguon'][0]['mong']);
    }

    #[Test]
    public function gia_theo_thang_so_voi_thang_TRUOC_CO_SO_LIEU(): void
    {
        $loai = FlowerKind::create(['name' => 'Thạch thảo', 'default_unit' => 'bo']);
        $n = $this->ncc('Vựa T');

        $this->lo($loai, $n, ['purchased_at' => '2026-05-10', 'total_cost' => '4000000.00']);
        $this->lo($loai, $n, ['purchased_at' => '2026-06-10', 'total_cost' => '5000000.00']);
        $this->lo($loai, $n, ['purchased_at' => '2026-08-10', 'total_cost' => '5500000.00']);

        $thang = $this->bao()->hoa()->first()['thang'];

        $this->assertSame(['2026-05', '2026-06', '2026-08'], array_column($thang, 'thang'));
        $this->assertNull($thang[0]['doi']);
        $this->assertSame(25.0, $thang[1]['doi']);

        $this->assertSame(10.0, $thang[2]['doi']);
    }

    #[Test]
    public function cot_ngay_loc_theo_NGAY_DIA_PHUONG_khong_lech_7_tieng(): void
    {
        config(['app.display_timezone' => 'America/New_York']);

        $loai = FlowerKind::create(['name' => 'Salem', 'default_unit' => 'bo']);
        $n = $this->ncc('Vựa S');

        $k = new KhoangThoiGian(KhoangThoiGian::nuaDemTruoc(0), null);
        $homNay = KhoangThoiGian::diaPhuong($k->tu)->toDateString();
        $homQua = KhoangThoiGian::diaPhuong($k->tu)->subDay()->toDateString();

        $this->lo($loai, $n, ['purchased_at' => $homNay]);
        $this->lo($loai, $n, ['purchased_at' => $homQua]);

        $this->assertSame(1, $this->bao($k)->hoa()->first()['so_lan']);
    }

    #[Test]
    public function hang_dem_duoc_so_gia_giua_hai_nguon(): void
    {
        $sp = Product::factory()->for(Category::factory())->stock(0)->create(['name' => 'Chậu sứ']);

        $this->nhap($sp, $this->ncc('Gốm Bát Tràng'), 10, '100000');
        $this->nhap($sp, $this->ncc('Chợ Kim Biên'), 10, '80000');

        $g = $this->bao()->hang()->first();

        $this->assertSame('Chậu sứ', $g['ten']);
        $this->assertSame('Chợ Kim Biên', $g['nguon'][0]['ten']);
        $this->assertSame('80000.00', $g['nguon'][0]['gia_dung_duoc']);
    }

    #[Test]
    public function hang_tra_KHONG_duoc_gi_thi_gia_dung_duoc_tang_va_doi_thu_hang(): void
    {
        $sp = Product::factory()->for(Category::factory())->stock(0)->create(['name' => 'Chậu sứ']);

        $this->nhap($sp, $this->ncc('Gốm Bát Tràng'), 10, '100000');
        $cho = $this->nhap($sp, $this->ncc('Chợ Kim Biên'), 10, '80000');

        $tra = app(SupplierReturnService::class)->traHangDem($cho, [$cho->items->first()->id => ['quantity' => 4]], [
            'returned_at' => now()->toDateString(),
            'reason' => ReturnReason::HangHong->value,
            'settlement' => ReturnSettlement::KhongDuocGi->value,
        ]);
        app(StockReceiptService::class)->ghiSo($tra);

        $g = $this->bao()->hang()->first();

        $this->assertSame('Gốm Bát Tràng', $g['nguon'][0]['ten']);
        $this->assertSame('Chợ Kim Biên', $g['nguon'][1]['ten']);
        $this->assertSame('133333.33', $g['nguon'][1]['gia_dung_duoc']);
        $this->assertSame(40.0, $g['nguon'][1]['ti_le_tra']);
        $this->assertSame(1, $g['nguon'][1]['so_lan'], 'Phiếu trả không phải một lần mua');
        $this->assertTrue($g['khac_nhau']);
    }

    #[Test]
    public function phieu_con_NHAP_khong_tinh(): void
    {
        $sp = Product::factory()->for(Category::factory())->stock(0)->create();

        $this->actingAs($this->admin())->post('/admin/nhap-kho', [
            'received_at' => now()->toDateString(),
            'items' => [['mat_hang' => (string) $sp->id, 'quantity' => 5, 'unit_cost' => 1000]],
        ])->assertRedirect();

        $this->assertTrue($this->bao()->hang()->isEmpty());
    }

    #[Test]
    public function ton_dau_ky_KHONG_phai_mot_lan_mua(): void
    {
        $sp = Product::factory()->for(Category::factory())->stock(0)->create();
        $p = $this->nhap($sp, null, 5, '1000');
        $p->forceFill(['kind' => StockReceiptKind::TonDauKy])->save();

        $this->assertTrue($this->bao()->hang()->isEmpty());
    }

    #[Test]
    public function trang_mo_duoc_khi_chua_co_du_lieu(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.analytics.purchasing'))
            ->assertOk()
            ->assertSee('Chưa có lô hoa nào trong kỳ này.')
            ->assertSee('Chưa có phiếu nhập nào đã ghi sổ trong kỳ này.');
    }

    #[Test]
    public function trang_hien_cau_re_nhat_khac_dang_tien_nhat(): void
    {
        $loai = FlowerKind::create(['name' => 'Hồng đỏ', 'default_unit' => 'bo']);
        $this->lo($loai, $this->ncc('Vựa A'), ['total_cost' => '5000000.00', 'hao_hut' => '20.00']);
        $this->lo($loai, $this->ncc('Vựa B'), ['total_cost' => '5500000.00', 'hao_hut' => '5.00']);

        $this->actingAs($this->admin())
            ->get(route('admin.analytics.purchasing', ['ky' => 'all']))
            ->assertOk()
            ->assertSeeInOrder(['Rẻ nhất trên hoá đơn là', 'Vựa A', 'đáng tiền nhất', 'Vựa B'])
            ->assertSee('57.895₫');
    }

    #[Test]
    public function trang_KHONG_in_cau_re_nhat_khi_chi_co_mot_nguon(): void
    {
        $loai = FlowerKind::create(['name' => 'Cúc', 'default_unit' => 'bo']);
        $this->lo($loai, $this->ncc('Chợ Quảng Bá'), ['hao_hut' => '30.00']);

        $this->actingAs($this->admin())
            ->get(route('admin.analytics.purchasing', ['ky' => 'all']))
            ->assertOk()
            ->assertSee('Chợ Quảng Bá')
            ->assertDontSee('Rẻ nhất trên hoá đơn');
    }

    #[Test]
    public function nhan_vien_vao_duoc_nhung_KHONG_thay_tab_loi_nhuan(): void
    {
        $nv = $this->admin(UserRole::Staff);

        $this->actingAs($nv)
            ->get(route('admin.analytics.purchasing'))
            ->assertOk()
            ->assertDontSee('href="' . route('admin.analytics.profit'), false)
            ->assertSee('href="' . route('admin.analytics.sales'), false);
    }

    #[Test]
    public function tab_loi_nhuan_van_hien_cho_quan_tri(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.analytics.sales'))
            ->assertOk()
            ->assertSee('href="' . route('admin.analytics.profit'), false)
            ->assertSee('href="' . route('admin.analytics.purchasing'), false);
    }

    #[Test]
    public function khach_hang_KHONG_vao_duoc(): void
    {
        $this->actingAs($this->admin(UserRole::Customer))
            ->get(route('admin.analytics.purchasing'))
            ->assertForbidden();
    }
}
