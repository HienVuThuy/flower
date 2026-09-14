<?php

namespace Tests\Feature\Points;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Points\PointLedger;
use App\Services\Points\VisitStreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Chuỗi ngày ghé thăm: mỗi ngày (lịch Việt Nam) một lần, đứt thì về 1, thưởng ở mốc.
 */
class ChuoiNgayGheTest extends TestCase
{
    use RefreshDatabase;

    private function khach(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Customer;
        $u->save();

        return $u;
    }

    /** Mở trang chủ lúc $utc (giờ lưu). */
    private function ghe(User $u, string $utc): void
    {
        $this->travelTo(Carbon::parse($utc, 'UTC'));
        $this->actingAs($u->fresh())->get('/')->assertOk();
    }

    #[Test]
    public function moi_ngay_mot_lan_lien_tiep_thi_tang_dut_thi_ve_1(): void
    {
        $u = $this->khach();

        $this->ghe($u, '2026-09-10 03:00:00');
        $this->ghe($u, '2026-09-10 09:00:00');   // cùng ngày
        $this->assertSame(1, $u->fresh()->visit_streak);

        $this->ghe($u, '2026-09-11 03:00:00');
        $this->assertSame(2, $u->fresh()->visit_streak);

        $this->ghe($u, '2026-09-13 03:00:00');   // bỏ ngày 12
        $this->assertSame(1, $u->fresh()->visit_streak);
        $this->assertSame('2026-09-13', $u->fresh()->last_visit_on->toDateString());
    }

    #[Test]
    public function sang_ngay_theo_nua_dem_viet_nam_khong_theo_utc(): void
    {
        $u = $this->khach();

        // 14/09 16:50 UTC = 23:50 Hà Nội ngày 14; 17:10 UTC = 00:10 Hà Nội ngày 15 — cùng một ngày UTC.
        $this->ghe($u, '2026-09-14 16:50:00');
        $this->ghe($u, '2026-09-14 17:10:00');

        $this->assertSame(2, $u->fresh()->visit_streak);
        $this->assertSame('2026-09-15', $u->fresh()->last_visit_on->toDateString());
    }

    #[Test]
    public function thuong_o_moc_3_va_7_ngay_moi_moc_mot_lan(): void
    {
        $u = $this->khach();
        $so = app(PointLedger::class);

        foreach (range(1, 2) as $d) {
            $this->ghe($u, sprintf('2026-09-%02d 03:00:00', $d));
        }
        $this->assertSame(0, $so->soDu($u));

        $this->ghe($u, '2026-09-03 03:00:00');
        $this->ghe($u, '2026-09-03 08:00:00');   // mở lại trong ngày mốc: không thưởng lần hai
        $this->assertSame(10, $so->soDu($u));

        foreach (range(4, 7) as $d) {
            $this->ghe($u, sprintf('2026-09-%02d 03:00:00', $d));
        }
        $this->assertSame(40, $so->soDu($u));

        foreach (range(8, 14) as $d) {
            $this->ghe($u, sprintf('2026-09-%02d 03:00:00', $d));
        }
        $this->assertSame(70, $so->soDu($u), 'Ngày 14 là mốc 7 ngày tiếp theo');
    }

    #[Test]
    public function nhan_vien_va_yeu_cau_khong_phai_ghe_tham_thi_khong_tinh(): void
    {
        $nv = User::factory()->create();
        $nv->role = UserRole::Staff;
        $nv->save();

        $this->ghe($nv, '2026-09-10 03:00:00');
        $this->assertSame(0, $nv->fresh()->visit_streak);

        $u = $this->khach();
        $this->travelTo(Carbon::parse('2026-09-10 03:00:00', 'UTC'));
        $this->actingAs($u)->getJson('/api/goi-y-tim-kiem?q=cay');
        $this->actingAs($u)->post(route('shop.points.redeem'), ['goi' => 'giam-20k']);
        $this->assertSame(0, $u->fresh()->visit_streak);
        $this->assertNull($u->fresh()->last_visit_on);
    }

    #[Test]
    public function ho_so_noi_chuoi_va_moc_ke_tiep_mat_chuoi_thi_ve_0(): void
    {
        $u = $this->khach();

        $this->ghe($u, '2026-09-10 03:00:00');
        $this->ghe($u, '2026-09-11 03:00:00');

        $html = $this->actingAs($u->fresh())->get(route('shop.profile.edit', ['muc' => 'diem-thuong']))->getContent();
        $this->assertStringContainsString('data-chuoi="2"', $html);
        $this->assertStringContainsString('Còn 1 ngày nữa tới mốc 3 ngày: +10 điểm', $html);

        // Hai ngày không ghé: chuỗi đã mất — không hiện "2 ngày" như thể còn.
        $this->travelTo(Carbon::parse('2026-09-13 03:00:00', 'UTC'));
        $this->assertSame(0, app(VisitStreak::class)->hienTai($u->fresh()));
    }

    #[Test]
    public function moc_ke_tiep(): void
    {
        $this->assertSame(['ngay' => 3, 'diem' => 10], VisitStreak::mocTiepTheo(0));
        $this->assertSame(['ngay' => 7, 'diem' => 30], VisitStreak::mocTiepTheo(3));
        $this->assertSame(['ngay' => 14, 'diem' => 30], VisitStreak::mocTiepTheo(7));
    }
}
