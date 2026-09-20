<?php

namespace Tests\Feature\Points;

use App\Enums\PointReason;
use App\Models\Coupon;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\Coupon\CouponException;
use App\Services\Coupon\CouponService;
use App\Services\Points\PointException;
use App\Services\Points\PointLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Sổ điểm thưởng: cộng một lần, không âm, voucher đổi được là của riêng. */
class SoDiemThuongTest extends TestCase
{
    use RefreshDatabase;

    private function so(): PointLedger
    {
        return app(PointLedger::class);
    }

    #[Test]
    public function mot_viec_chi_cong_mot_lan(): void
    {
        $u = User::factory()->create();

        $this->assertTrue($this->so()->cong($u, 50, PointReason::DangBai, 'bai:7'));
        $this->assertFalse($this->so()->cong($u, 50, PointReason::DangBai, 'bai:7'), 'Duyệt lại cùng bài không cộng thêm');
        $this->assertTrue($this->so()->cong($u, 30, PointReason::DangBai, 'bai:8'));

        $khac = User::factory()->create();
        $this->assertTrue($this->so()->cong($khac, 50, PointReason::DangBai, 'bai:7'));

        $this->assertSame(80, $this->so()->soDu($u));
        $this->assertSame(50, $this->so()->soDu($khac));
    }

    #[Test]
    public function khong_cong_so_diem_am_hoac_0(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->so()->cong(User::factory()->create(), -100, PointReason::DangBai, 'bai:1');
    }

    #[Test]
    public function doi_voucher_tru_diem_va_ma_vao_vi_cua_rieng_nguoi_doi(): void
    {
        $u = User::factory()->create();
        $this->so()->cong($u, 250, PointReason::DangBai, 'bai:1');

        $ma = $this->so()->doiVoucher($u, 'giam-20k');

        $this->assertSame(50, $this->so()->soDu($u));
        $this->assertSame($u->id, (int) $ma->owner_user_id);
        $this->assertFalse($ma->is_public);
        $this->assertSame(1, $ma->usage_limit);
        $this->assertSame('20000.00', (string) $ma->value);
        $this->assertSame('150000.00', (string) $ma->min_order_amount);
        $this->assertTrue($ma->isRunning());
        $this->assertTrue(DB::table('coupon_user')->where(['user_id' => $u->id, 'coupon_id' => $ma->id])->exists());

        $dong = PointTransaction::where('reason', PointReason::DoiVoucher->value)->sole();
        $this->assertSame(-200, $dong->amount);
        $this->assertSame($ma->id, $dong->coupon_id);
    }

    #[Test]
    public function khong_du_diem_thi_khong_phat_ma_va_khong_tru(): void
    {
        $u = User::factory()->create();
        $this->so()->cong($u, 199, PointReason::DangBai, 'bai:1');

        try {
            $this->so()->doiVoucher($u, 'giam-20k');
            $this->fail('Phải từ chối khi thiếu 1 điểm');
        } catch (PointException) {
        }

        $this->assertSame(199, $this->so()->soDu($u));
        $this->assertSame(0, Coupon::count());
    }

    #[Test]
    public function doi_hai_lan_lien_khong_duoc_xuong_am(): void
    {
        $u = User::factory()->create();
        $this->so()->cong($u, 250, PointReason::DangBai, 'bai:1');

        $this->so()->doiVoucher($u, 'giam-20k');

        $this->expectException(PointException::class);
        $this->so()->doiVoucher($u, 'giam-20k');
    }

    #[Test]
    public function nguoi_khac_va_khach_vang_lai_khong_dung_duoc_ma_co_chu(): void
    {
        $chu = User::factory()->create();
        $this->so()->cong($chu, 500, PointReason::DangBai, 'bai:1');
        $ma = $this->so()->doiVoucher($chu, 'giam-20k');

        $dv = app(CouponService::class);

        Auth::login($chu);
        $this->assertSame($ma->id, $dv->resolve($ma->code, '200000.00')->id);

        Auth::login(User::factory()->create());
        try {
            $dv->resolve($ma->code, '200000.00');
            $this->fail('Người khác không được dùng mã có chủ');
        } catch (CouponException $e) {
            $this->assertSame('Mã giảm giá không tồn tại.', $e->getMessage());
        }

        Auth::logout();
        $this->expectException(CouponException::class);
        $dv->resolve($ma->code, '200000.00');
    }

    #[Test]
    public function xoa_tai_khoan_thi_ma_co_chu_mat_theo_chu_khong_thanh_ma_chung(): void
    {
        $chu = User::factory()->create();
        $this->so()->cong($chu, 500, PointReason::DangBai, 'bai:1');
        $ma = $this->so()->doiVoucher($chu, 'giam-20k');

        $chu->delete();

        $this->assertDatabaseMissing('coupons', ['id' => $ma->id]);
        $this->assertDatabaseMissing('point_transactions', ['user_id' => $chu->id]);
    }

    #[Test]
    public function trang_diem_thuong_hien_so_du_khoa_goi_chua_du_va_doi_duoc(): void
    {
        $u = User::factory()->create();
        $this->so()->cong($u, 300, PointReason::DangBai, 'bai:1', 'Bài "Ban công mùa thu"');

        $html = $this->actingAs($u)->get(route('shop.profile.edit', ['muc' => 'diem-thuong']))->assertOk()->getContent();

        $this->assertStringContainsString('data-so-du="300"', $html);
        $this->assertMatchesRegularExpression('#data-goi="giam-50k".*?<button[^>]*disabled#s', $html, 'Gói 450 điểm phải khoá');
        $this->assertDoesNotMatchRegularExpression('#data-goi="giam-20k"[^§]*?<button[^>]*disabled[^>]*>\s*Đổi 200#s', $html);
        $this->assertStringContainsString('Còn thiếu 150 điểm', $html);
        $this->assertStringContainsString('Bài &quot;Ban công mùa thu&quot;', $html);

        $this->actingAs($u)->post(route('shop.points.redeem'), ['goi' => 'giam-20k'])
            ->assertRedirect(route('shop.profile.edit', ['muc' => 'diem-thuong']))
            ->assertSessionHas('success');

        $this->assertSame(100, $this->so()->soDu($u));
    }

    #[Test]
    public function goi_la_hoac_thieu_diem_qua_bieu_mau_bi_tu_choi(): void
    {
        $u = User::factory()->create();
        $this->so()->cong($u, 100, PointReason::DangBai, 'bai:1');

        $this->actingAs($u)->post(route('shop.points.redeem'), ['goi' => 'giam-1trieu'])->assertSessionHasErrors('goi');
        $this->actingAs($u)->post(route('shop.points.redeem'), ['goi' => 'giam-20k'])->assertSessionHas('error');

        $this->assertSame(100, $this->so()->soDu($u));
        $this->assertSame(0, Coupon::count());
    }

    #[Test]
    public function khach_chua_dang_nhap_khong_doi_duoc(): void
    {
        $this->post(route('shop.points.redeem'), ['goi' => 'giam-20k'])->assertRedirect('/dang-nhap');
    }
}
