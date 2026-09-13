<?php

namespace Tests\Feature\Admin;

use App\Enums\FlowerLotStatus;
use App\Enums\StockReceiptKind;
use App\Enums\StockReceiptStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\FlowerKind;
use App\Models\FlowerLot;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\User;
use App\Services\Analytics\KhoangThoiGian;
use App\Services\Analytics\PurchasingReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tổng quan có số liệu của các nghiệp vụ mới: kho, thu mua, hoa, đổi trả, lãi.
 */
class TongQuanNghiepVuTest extends TestCase
{
    use RefreshDatabase;

    private function nguoi(UserRole $vaiTro): User
    {
        $u = User::factory()->create();
        $u->role = $vaiTro;
        $u->save();

        return $u;
    }

    private function phieuNhap(string $ngay, int $sl, string $gia, StockReceiptStatus $trangThai = StockReceiptStatus::Posted): void
    {
        $sp = Product::factory()->for(Category::factory())->price('300000.00')->create();

        $p = StockReceipt::create(['code' => 'NK-TQ-' . uniqid(), 'received_at' => $ngay]);
        $p->forceFill(['kind' => StockReceiptKind::NhapMoi, 'status' => $trangThai, 'posted_at' => $trangThai === StockReceiptStatus::Posted ? now() : null])->save();
        $p->items()->create(['product_id' => $sp->id, 'product_name' => $sp->name, 'quantity' => $sl, 'unit_cost' => $gia]);
    }

    private function lo(string $ngay, string $tien): void
    {
        $loai = FlowerKind::firstOrCreate(['name' => 'Hồng thử'], ['default_unit' => 'bo']);

        (new FlowerLot())->forceFill([
            'code' => 'LH-TQ-' . uniqid(), 'flower_kind_id' => $loai->id, 'purchased_at' => $ngay,
            'quantity' => '5', 'unit' => 'bo', 'total_cost' => $tien, 'status' => FlowerLotStatus::DangDung,
        ])->save();
    }

    #[Test]
    public function tien_lay_hang_cung_bo_loc_voi_trang_thu_mua(): void
    {
        $this->phieuNhap(now()->subDays(3)->toDateString(), 2, '100000.00');
        $this->phieuNhap(now()->subDays(90)->toDateString(), 10, '100000.00');  // ngoài kỳ

        /*
         * PHIẾU NHÁP TRONG KỲ — hàng chưa vào kho, tiền chưa phải đã chi.
         * Thử phá code đã chứng minh: bỏ điều kiện "đã ghi sổ" mà bài vẫn
         * xanh, vì trước đó mọi phiếu trong bài đều đã ghi sổ.
         */
        $this->phieuNhap(now()->subDays(1)->toDateString(), 5, '100000.00', StockReceiptStatus::Draft);
        $this->lo(now()->subDays(2)->toDateString(), '300000.00');

        $tq = app(PurchasingReport::class)
            ->trong(new KhoangThoiGian(KhoangThoiGian::nuaDemTruoc(29), null))
            ->tongQuan();

        $this->assertSame('200000.00', $tq['tien_hang']);
        $this->assertSame(1, $tq['so_phieu']);
        $this->assertSame('300000.00', $tq['tien_hoa']);
        $this->assertSame('500000.00', $tq['tong']);
    }

    #[Test]
    public function quan_tri_thay_du_cac_o_moi_va_dung_so(): void
    {
        $this->phieuNhap(now()->subDays(3)->toDateString(), 2, '100000.00');
        $this->lo(now()->subDays(2)->toDateString(), '300000.00');

        $html = $this->actingAs($this->nguoi(UserRole::Admin))
            ->get(route('admin.dashboard', ['ky' => '30']))
            ->assertOk()
            ->getContent();

        foreach (['Kho và thu mua', 'Tiền lấy hàng trong kỳ', 'Giá trị tồn kho', 'Lô hoa đang dùng',
            'Lần trả nhà cung cấp', 'Lãi gộp hàng', 'Lãi gộp hoa', 'Đã hoàn tiền cho khách', 'Phiếu đổi hàng'] as $nhan) {
            $this->assertStringContainsString($nhan, $html, 'Thiếu ô: ' . $nhan);
        }

        $this->assertMatchesRegularExpression('#Tiền lấy hàng trong kỳ</span>\s*<span class="admin-kpi__value">\s*500\.000#u', $html);
        $this->assertStringContainsString('1 phiếu nhập · 1 lô hoa', $html);
    }

    #[Test]
    public function nhan_vien_KHONG_thay_lai_va_hoan_tien_nhung_thay_kho_va_doi_hang(): void
    {
        // Nhân viên: đơn hàng, kho, đánh giá, báo cáo — không có tài chính.
        $html = $this->actingAs($this->nguoi(UserRole::Staff))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Kho và thu mua', $html);
        $this->assertStringContainsString('Phiếu đổi hàng', $html);
        $this->assertStringNotContainsString('Lãi gộp hàng', $html);
        $this->assertStringNotContainsString('Lãi gộp hoa', $html);
        $this->assertStringNotContainsString('Đã hoàn tiền cho khách', $html);
    }

    #[Test]
    public function lenh_lam_nong_mo_duoc_moi_trang_quan_tri(): void
    {
        $this->nguoi(UserRole::Admin);

        $this->artisan('quan-tri:lam-nong')
            ->expectsOutputToContain('/admin/dashboard')
            ->assertSuccessful();
    }
}
