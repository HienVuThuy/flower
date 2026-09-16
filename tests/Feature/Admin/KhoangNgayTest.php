<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\ChonKy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Khoảng ngày tự chọn cho trang Tổng quan và Phân tích. */
class KhoangNgayTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function don(string $gioVN, string $tien = '100000.00'): Order
    {
        $sp = Product::factory()
            ->for(Category::factory())
            ->price($tien)
            ->create();

        $moc = Carbon::parse($gioVN, 'Asia/Ho_Chi_Minh')->setTimezone('UTC');

        $don = new Order();
        $don->forceFill([
            'order_number' => 'KT-' . $moc->format('ymdHis') . '-' . $sp->id,
            'recipient_name' => 'Khách thử khoảng',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => $tien,
            'grand_total' => $tien,
            'status' => OrderStatus::Completed,
            'created_at' => $moc,
            'updated_at' => $moc,
        ])->save();

        return $don;
    }

    private function soDon(array $thamSo): int
    {
        $a = app(AnalyticsService::class);
        ChonKy::tuThamSo($thamSo['ky'] ?? null, $thamSo['tu'] ?? null, $thamSo['den'] ?? null)
            ->apDung($a);

        return (int) $a->orderStats()['total'];
    }

    #[Test]
    public function chi_dem_don_nam_trong_khoang_da_chon(): void
    {
        $this->don('2026-08-31 10:00');
        $this->don('2026-09-01 10:00');
        $this->don('2026-09-05 10:00');
        $this->don('2026-09-08 10:00');

        $this->assertSame(2, $this->soDon(['ky' => 'tuy-chon', 'tu' => '2026-09-01', 'den' => '2026-09-07']));
    }

    #[Test]
    public function ngay_cuoi_duoc_tinh_TRON_NGAY(): void
    {
        $this->don('2026-09-07 23:59:59');

        $this->assertSame(1, $this->soDon(['ky' => 'tuy-chon', 'tu' => '2026-09-01', 'den' => '2026-09-07']));
    }

    #[Test]
    public function ngay_la_ngay_theo_lich_Viet_Nam(): void
    {
        $this->don('2026-09-08 00:30');

        $this->assertSame(0, $this->soDon(['ky' => 'tuy-chon', 'tu' => '2026-09-01', 'den' => '2026-09-07']));
        $this->assertSame(1, $this->soDon(['ky' => 'tuy-chon', 'tu' => '2026-09-08', 'den' => '2026-09-08']));
    }

    #[Test]
    public function chon_nguoc_thi_doi_cho_chu_khong_bat_lam_lai(): void
    {
        $this->don('2026-09-03 10:00');

        $this->assertSame(1, $this->soDon(['ky' => 'tuy-chon', 'tu' => '2026-09-07', 'den' => '2026-09-01']));
    }

    #[Test]
    public function tham_so_la_thi_lui_ve_moc_dung_san(): void
    {
        foreach ([
            ['tu' => '<script>', 'den' => '2026-09-07'],
            ['tu' => '2026-09-01', 'den' => 'tomorrow'],
            ['tu' => '2026-09-01', 'den' => null],
            ['tu' => '2026-02-31', 'den' => '2026-03-05'],
            ['tu' => ['2026-09-01'], 'den' => '2026-09-07'],
        ] as $xau) {
            $ky = ChonKy::tuThamSo('tuy-chon', $xau['tu'], $xau['den']);

            $this->assertFalse($ky->laTuyChon(), 'Nhận nhầm tham số lạ: ' . json_encode($xau));
            $this->assertSame('30', $ky->ma);
        }
    }

    #[Test]
    public function ngay_31_02_khong_bi_don_thanh_03_03(): void
    {
        $this->assertFalse(ChonKy::tuThamSo('tuy-chon', '2026-02-31', '2026-03-05')->laTuyChon());
    }

    #[Test]
    public function ky_truoc_cua_mot_khoang_dai_dung_bang_khoang_do(): void
    {
        $this->don('2026-09-03 10:00');
        $this->don('2026-09-10 10:00');
        $this->don('2026-08-20 10:00');

        $a = app(AnalyticsService::class);
        $ky = ChonKy::tuThamSo('tuy-chon', '2026-09-08', '2026-09-14');
        $ky->apDung($a);

        $this->assertSame(1, (int) $a->orderStats()['total']);

        $this->assertTrue($a->forPreviousPeriod($ky->ma));
        $this->assertSame(1, (int) $a->orderStats()['total'], 'Kỳ trước phải đúng 7 ngày ngay trước đó');
    }

    #[Test]
    public function moi_lien_ket_tren_trang_mang_theo_khoang(): void
    {
        $html = $this->actingAs($this->admin())
            ->get('/admin/phan-tich?ky=tuy-chon&tu=2026-09-01&den=2026-09-07')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('tu=2026-09-01', $html);
        $this->assertStringContainsString('den=2026-09-07', $html);

        $this->assertStringContainsString('value="2026-09-01"', $html);
        $this->assertStringContainsString('value="2026-09-07"', $html);

        $this->assertGreaterThanOrEqual(
            6,
            substr_count($html, 'tu=2026-09-01'),
            'Có liên kết trên trang không mang theo khoảng ngày',
        );
    }

    #[Test]
    public function tep_xuat_ra_ghi_dung_khoang_chu_khong_ghi_30_ngay(): void
    {
        $csv = $this->actingAs($this->admin())
            ->get('/admin/phan-tich/xuat/tai-ve?' . http_build_query([
                'ky' => 'tuy-chon',
                'tu' => '2026-09-01',
                'den' => '2026-09-07',
                'dinh_dang' => 'csv',
                'phan' => ['tong-quan'],
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('01/09/2026 – 07/09/2026', $csv);
        $this->assertStringNotContainsString('30 ngày qua', $csv);
    }

    #[Test]
    public function trang_Tong_quan_cung_nhan_khoang(): void
    {
        $this->don('2026-09-03 10:00');

        $this->actingAs($this->admin())
            ->get('/admin/dashboard?ky=tuy-chon&tu=2026-09-01&den=2026-09-07')
            ->assertOk()
            ->assertSee('2026-09-01', false);
    }

    #[Test]
    public function nhan_ky_doc_ra_dung_ngay_nguoi_dung_da_chon(): void
    {
        $ky = ChonKy::tuThamSo('tuy-chon', '2026-09-01', '2026-09-07');

        $this->assertSame('01/09/2026 – 07/09/2026', $ky->nhan());
        $this->assertSame('2026-09-07', $ky->oDen());

        $this->assertSame(
            ['ky' => 'tuy-chon', 'tu' => '2026-09-01', 'den' => '2026-09-07'],
            $ky->thamSo(),
        );
    }
}
