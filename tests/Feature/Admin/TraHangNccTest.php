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
use App\Services\Analytics\FlowerCostReport;
use App\Services\Analytics\KhoangThoiGian;
use App\Services\Inventory\FlowerLotException;
use App\Services\Inventory\FlowerLotService;
use App\Services\Inventory\StockReceiptException;
use App\Services\Inventory\StockReceiptService;
use App\Services\Inventory\SupplierReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Trả hàng cho nhà cung cấp: hàng hỏng, giao sai, không đạt. */
class TraHangNccTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function dv(): SupplierReturnService
    {
        return app(SupplierReturnService::class);
    }

    private function phieuDaGhiSo(int $sl = 10, string $gia = '100000', int $tonDau = 0): array
    {
        $sp = Product::factory()->for(Category::factory())->stock($tonDau)->create(['name' => 'Chậu sứ']);

        $this->actingAs($this->admin())->post('/admin/nhap-kho', [
            'received_at' => now()->subDays(5)->toDateString(),
            'items' => [['mat_hang' => (string) $sp->id, 'quantity' => $sl, 'unit_cost' => $gia]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $phieu = StockReceipt::where('kind', StockReceiptKind::NhapMoi->value)
            ->latest('id')
            ->firstOrFail();
        app(StockReceiptService::class)->ghiSo($phieu);

        return [$phieu->fresh('items'), $sp];
    }

    private function traHang(StockReceipt $goc, int $sl, ReturnSettlement $cach, ?int $tien = null): StockReceipt
    {
        return $this->dv()->traHangDem($goc, [$goc->items->first()->id => ['quantity' => $sl]], [
            'returned_at' => now()->toDateString(),
            'reason' => ReturnReason::HangHong->value,
            'settlement' => $cach->value,
            'settlement_amount' => $tien,
        ]);
    }

    #[Test]
    public function tra_hang_ghi_so_thi_TRU_khoi_kho(): void
    {
        [$goc, $sp] = $this->phieuDaGhiSo(sl: 10, tonDau: 0);

        $this->assertSame(10, (int) $sp->fresh()->stock_quantity);

        $tra = $this->traHang($goc, 3, ReturnSettlement::HoanTien);

        $this->assertSame(10, (int) $sp->fresh()->stock_quantity);

        app(StockReceiptService::class)->ghiSo($tra);

        $this->assertSame(7, (int) $sp->fresh()->stock_quantity);
    }

    #[Test]
    public function dong_phieu_tra_luu_SO_AM_o_dung_don_gia_da_mua(): void
    {
        [$goc] = $this->phieuDaGhiSo(sl: 10, gia: '100000');

        $tra = $this->traHang($goc, 3, ReturnSettlement::HoanTien);
        $dong = $tra->items()->first();

        $this->assertSame(-3, (int) $dong->quantity);
        $this->assertSame('100000.00', $dong->unit_cost);
        $this->assertSame(StockReceiptKind::TraNcc, $tra->kind);
        $this->assertSame($goc->id, $tra->return_of_id);
    }

    #[Test]
    public function gia_von_binh_quan_KHONG_doi_khi_tra_dung_don_gia(): void
    {
        [$goc, $sp] = $this->phieuDaGhiSo(sl: 10, gia: '100000');

        $tra = $this->traHang($goc, 3, ReturnSettlement::HoanTien);
        app(StockReceiptService::class)->ghiSo($tra);

        $bang = $this->bangGiaVon();
        $khoa = $sp->id . ':';

        $cuoi = end($bang[$khoa]);

        $this->assertSame(7, $cuoi['sl'], 'Số lượng nền giá vốn phải trừ phần đã trả');
        $this->assertSame('700000.00', $cuoi['tien']);
    }

    #[Test]
    public function tra_ma_KHONG_duoc_gi_thi_gia_von_moi_cai_con_lai_TANG(): void
    {
        [$goc, $sp] = $this->phieuDaGhiSo(sl: 10, gia: '100000');

        $tra = $this->traHang($goc, 3, ReturnSettlement::KhongDuocGi);
        app(StockReceiptService::class)->ghiSo($tra);

        $cuoi = end($this->bangGiaVon()[$sp->id . ':']);

        $this->assertSame(7, $cuoi['sl'], 'Hàng đã trả thì không còn trên kệ');
        $this->assertSame(
            '1000000.00',
            $cuoi['tien'],
            'Tiền đã tiêu vẫn nằm nguyên trong nền giá vốn — vựa có đền đâu mà trừ',
        );
    }

    #[Test]
    public function vua_hoan_THIEU_thi_phan_hut_o_lai_trong_gia_von(): void
    {
        [$goc, $sp] = $this->phieuDaGhiSo(sl: 10, gia: '100000');

        $tra = $this->traHang($goc, 3, ReturnSettlement::HoanTien, tien: 200000);
        app(StockReceiptService::class)->ghiSo($tra);

        $cuoi = end($this->bangGiaVon()[$sp->id . ':']);

        $this->assertSame(7, $cuoi['sl']);

        $this->assertSame('800000.02', $cuoi['tien']);
    }

    #[Test]
    public function khong_tra_qua_so_da_nhap(): void
    {
        [$goc] = $this->phieuDaGhiSo(sl: 10);

        $this->expectException(StockReceiptException::class);
        $this->expectExceptionMessageMatches('/chỉ còn trả được 10/iu');

        $this->traHang($goc, 11, ReturnSettlement::HoanTien);
    }

    #[Test]
    public function tra_hai_lan_cong_don_khong_vuot_qua_so_da_nhap(): void
    {
        [$goc] = $this->phieuDaGhiSo(sl: 10);

        $this->traHang($goc, 6, ReturnSettlement::HoanTien);

        $this->expectException(StockReceiptException::class);
        $this->expectExceptionMessageMatches('/chỉ còn trả được 4/iu');

        $this->traHang($goc, 5, ReturnSettlement::HoanTien);
    }

    #[Test]
    public function khong_tra_duoc_hang_cua_phieu_con_nhap(): void
    {
        $sp = Product::factory()->for(Category::factory())->stock(0)->create();

        $this->actingAs($this->admin())->post('/admin/nhap-kho', [
            'received_at' => now()->toDateString(),
            'items' => [['mat_hang' => (string) $sp->id, 'quantity' => 5, 'unit_cost' => 1000]],
        ])->assertRedirect();

        $nhap = StockReceipt::firstOrFail();

        $this->expectException(StockReceiptException::class);
        $this->expectExceptionMessageMatches('/chưa ghi sổ/iu');

        $this->traHang($nhap->fresh('items'), 1, ReturnSettlement::HoanTien);
    }

    #[Test]
    public function tien_lay_lai_tinh_theo_don_gia_khi_khong_go_tay(): void
    {
        [$goc] = $this->phieuDaGhiSo(sl: 10, gia: '100000');

        $tra = $this->traHang($goc, 3, ReturnSettlement::HoanTien);

        $this->assertSame('300000.00', $tra->settlement_amount);
    }

    #[Test]
    public function vua_tra_it_hon_thi_ghi_dung_so_vua_tra(): void
    {
        [$goc] = $this->phieuDaGhiSo(sl: 10, gia: '100000');

        $tra = $this->traHang($goc, 3, ReturnSettlement::HoanTien, tien: 250000);

        $this->assertSame('250000.00', $tra->settlement_amount);
    }

    #[Test]
    public function doi_hang_hoac_khong_duoc_gi_thi_tien_la_NULL_chu_khong_phai_0(): void
    {
        foreach ([ReturnSettlement::DoiHang, ReturnSettlement::KhongDuocGi] as $cach) {
            [$goc] = $this->phieuDaGhiSo(sl: 10);

            $tra = $this->traHang($goc, 2, $cach);

            $this->assertNull($tra->settlement_amount, 'Sai với cách xử lý ' . $cach->value);
        }
    }

    #[Test]
    public function tra_hoa_co_hoan_tien_thi_gia_von_hoa_GIAM(): void
    {
        $lo = $this->loHoa(sl: '20.00', tien: '2000000.00');

        $this->dv()->traHangHoa($lo, [
            'quantity' => '3',
            'reason' => ReturnReason::HangHong->value,
            'settlement' => ReturnSettlement::HoanTien->value,
        ]);

        app(FlowerLotService::class)->dongLo($lo->fresh());

        $b = $this->baoCaoHoa();

        $this->assertSame('300000.00', $b['tien_tra_lai']);
        $this->assertSame('1700000.00', $b['gia_von'], 'Giá vốn phải giảm đúng phần đã lấy lại');
        $this->assertSame(1, $b['so_lo_phai_tra']);
    }

    #[Test]
    public function tra_hoa_ma_DOI_HANG_thi_gia_von_GIU_NGUYEN(): void
    {
        $lo = $this->loHoa(sl: '20.00', tien: '2000000.00');

        $this->dv()->traHangHoa($lo, [
            'quantity' => '3',
            'reason' => ReturnReason::HangHong->value,
            'settlement' => ReturnSettlement::DoiHang->value,
        ]);

        app(FlowerLotService::class)->dongLo($lo->fresh());

        $b = $this->baoCaoHoa();

        $this->assertSame('0.00', $b['tien_tra_lai']);
        $this->assertSame('2000000.00', $b['gia_von'], 'Đổi hàng mà giá vốn vẫn bị trừ');
        $this->assertNull($lo->fresh()->tra_lai_tien);
    }

    #[Test]
    public function tra_hoa_ma_KHONG_DUOC_GI_thi_gia_von_cung_GIU_NGUYEN(): void
    {
        $lo = $this->loHoa(sl: '20.00', tien: '2000000.00');

        $this->dv()->traHangHoa($lo, [
            'quantity' => '5',
            'reason' => ReturnReason::HangHong->value,
            'settlement' => ReturnSettlement::KhongDuocGi->value,
        ]);

        app(FlowerLotService::class)->dongLo($lo->fresh());

        $this->assertSame('2000000.00', $this->baoCaoHoa()['gia_von']);
    }

    #[Test]
    public function khong_tra_qua_so_hoa_da_lay(): void
    {
        $lo = $this->loHoa(sl: '20.00');

        $this->expectException(FlowerLotException::class);
        $this->expectExceptionMessageMatches('/tối đa/iu');

        $this->dv()->traHangHoa($lo, [
            'quantity' => '25',
            'reason' => ReturnReason::HangHong->value,
            'settlement' => ReturnSettlement::HoanTien->value,
        ]);
    }

    #[Test]
    public function khong_ghi_tra_hai_lan_cho_mot_lo(): void
    {
        $lo = $this->loHoa();

        $this->dv()->traHangHoa($lo, [
            'quantity' => '2',
            'reason' => ReturnReason::HangHong->value,
            'settlement' => ReturnSettlement::HoanTien->value,
        ]);

        $this->expectException(FlowerLotException::class);
        $this->expectExceptionMessageMatches('/đã ghi trả hàng rồi/iu');

        $this->dv()->traHangHoa($lo->fresh(), [
            'quantity' => '1',
            'reason' => ReturnReason::HangHong->value,
            'settlement' => ReturnSettlement::HoanTien->value,
        ]);
    }

    #[Test]
    public function trang_tra_hang_mo_duoc_va_lap_phieu_qua_bieu_mau(): void
    {
        [$goc] = $this->phieuDaGhiSo(sl: 10, gia: '100000');
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/tra-hang-ncc')->assertOk()->assertSee($goc->code);

        $this->actingAs($admin)->post('/admin/tra-hang-ncc/phieu/' . $goc->id, [
            'returned_at' => now()->toDateString(),
            'reason' => ReturnReason::GiaoSai->value,
            'settlement' => ReturnSettlement::TruCongNo->value,
            'items' => [$goc->items->first()->id => ['quantity' => 2]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $tra = StockReceipt::where('kind', StockReceiptKind::TraNcc->value)->firstOrFail();

        $this->assertSame(-2, (int) $tra->items()->first()->quantity);
        $this->assertSame('200000.00', $tra->settlement_amount);
        $this->assertSame(ReturnReason::GiaoSai, $tra->return_reason);
    }

    private function loHoa(string $sl = '20.00', string $tien = '2000000.00'): FlowerLot
    {
        $loai = FlowerKind::create(['name' => 'Hồng đỏ ' . uniqid(), 'default_unit' => 'bo']);
        $ncc = Supplier::create(['name' => 'Vựa ' . uniqid(), 'kind' => 'vua']);

        $lo = new FlowerLot();
        $lo->forceFill([
            'code' => 'LH-' . uniqid(),
            'flower_kind_id' => $loai->id,
            'supplier_id' => $ncc->id,
            'supplier_name' => $ncc->name,
            'purchased_at' => now()->subDays(2)->toDateString(),
            'quantity' => $sl,
            'unit' => 'bo',
            'total_cost' => $tien,
            'status' => FlowerLotStatus::DangDung,
        ])->save();

        return $lo->fresh();
    }

    private function baoCaoHoa(): array
    {
        return app(FlowerCostReport::class)
            ->trong(new KhoangThoiGian(KhoangThoiGian::nuaDemTruoc(29), null))
            ->baoCao();
    }

    private function bangGiaVon(): array
    {
        $bao = app(\App\Services\Analytics\ProfitReport::class);
        $m = new \ReflectionMethod($bao, 'bangGiaVon');
        $m->setAccessible(true);

        return $m->invoke($bao);
    }
}
