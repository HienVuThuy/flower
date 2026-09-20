<?php

namespace Tests\Feature\Coupon;

use App\Models\Coupon;
use App\Models\User;
use App\Services\Coupon\CouponWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Dọn ví voucher. */
class VoucherWalletCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function ma(array $ghiDe = []): Coupon
    {
        return Coupon::factory()->create(array_merge([
            'is_public' => true,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ], $ghiDe));
    }

    private function vaoVi(User $user, Coupon $coupon, int $daDung = 0): void
    {
        DB::table('coupon_user')->insert([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'claimed_at' => now()->subDays(10),
            'used_count' => $daDung,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function ma_da_het_han_van_bo_duoc_khoi_vi(): void
    {
        $user = User::factory()->create();
        $coupon = $this->ma(['ends_at' => now()->subDay()]);
        $this->vaoVi($user, $coupon);

        $this->actingAs($user)
            ->get('/voucher?het-han=1')
            ->assertOk()
            ->assertSee('Bỏ khỏi ví');

        $this->actingAs($user)
            ->delete('/voucher/' . $coupon->code . '/luu')
            ->assertRedirect();

        $this->assertDatabaseMissing('coupon_user', [
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
        ]);
    }

    #[Test]
    public function ma_da_het_luot_toan_he_thong_cung_bo_duoc(): void
    {
        $user = User::factory()->create();
        $coupon = $this->ma(['usage_limit' => 5]);
        $coupon->forceFill(['used_count' => 5])->save();
        $this->vaoVi($user, $coupon);

        $this->actingAs($user)->get('/voucher?het-han=1')->assertOk()->assertSee('Bỏ khỏi ví');

        $this->actingAs($user)->delete('/voucher/' . $coupon->code . '/luu')->assertRedirect();

        $this->assertDatabaseMissing('coupon_user', ['user_id' => $user->id, 'coupon_id' => $coupon->id]);
    }

    #[Test]
    public function ma_da_dung_thi_CHI_AN_khong_xoa(): void
    {
        $user = User::factory()->create();
        $coupon = $this->ma(['per_user_limit' => 1]);
        $this->vaoVi($user, $coupon, daDung: 1);

        $this->actingAs($user)->delete('/voucher/' . $coupon->code . '/luu')->assertRedirect();

        $row = DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->first();

        $this->assertNotNull($row, 'Đã xoá mất bằng chứng dùng mã.');
        $this->assertSame(1, (int) $row->used_count);
        $this->assertNotNull($row->hidden_at);
    }

    #[Test]
    public function ma_da_an_khong_con_trong_vi_nhung_van_dem_duoc_luot(): void
    {
        $user = User::factory()->create();
        $coupon = $this->ma(['per_user_limit' => 1]);
        $this->vaoVi($user, $coupon, daDung: 1);

        $wallet = app(CouponWallet::class);
        $wallet->discard($user, $coupon);

        $this->assertCount(0, $wallet->forUser($user), 'Mã đã ẩn vẫn chen vào ví.');
        $this->assertCount(1, $wallet->forUser($user, daAn: true));

        $this->assertTrue($wallet->userLimitReached($user, $coupon));
    }

    #[Test]
    public function co_duong_quay_lai_cho_ma_da_an(): void
    {
        $user = User::factory()->create();
        $coupon = $this->ma(['per_user_limit' => 1]);
        $this->vaoVi($user, $coupon, daDung: 1);

        app(CouponWallet::class)->discard($user, $coupon);

        $this->actingAs($user)->get('/voucher')->assertOk()->assertSee('1 mã đã ẩn');

        $this->actingAs($user)
            ->get('/voucher?da-an=1')
            ->assertOk()
            ->assertSee('Đưa lại về ví');

        $this->actingAs($user)
            ->patch('/voucher/' . $coupon->code . '/luu')
            ->assertRedirect();

        $this->assertCount(1, app(CouponWallet::class)->forUser($user));
    }

    #[Test]
    public function khong_moi_xem_muc_da_an_khi_chua_an_ma_nao(): void
    {
        $user = User::factory()->create();
        $this->vaoVi($user, $this->ma());

        $this->actingAs($user)->get('/voucher')->assertOk()->assertDontSee('mã đã ẩn');
    }

    #[Test]
    public function nguoi_khac_khong_bo_duoc_ma_trong_vi_cua_toi(): void
    {
        $toi = User::factory()->create();
        $coupon = $this->ma();
        $this->vaoVi($toi, $coupon);

        $nguoiKhac = User::factory()->create();

        $this->actingAs($nguoiKhac)->delete('/voucher/' . $coupon->code . '/luu')->assertRedirect();

        $this->assertDatabaseHas('coupon_user', [
            'user_id' => $toi->id,
            'coupon_id' => $coupon->id,
        ]);
    }

    #[Test]
    public function khach_vang_lai_khong_bo_duoc_ma_nao(): void
    {
        $coupon = $this->ma();

        $this->delete('/voucher/' . $coupon->code . '/luu')->assertRedirect('/dang-nhap');
        $this->patch('/voucher/' . $coupon->code . '/luu')->assertRedirect('/dang-nhap');
    }

    #[Test]
    public function bo_ma_chua_dung_thi_luu_lai_duoc_neu_doi_y(): void
    {
        $user = User::factory()->create();
        $coupon = $this->ma();
        $this->vaoVi($user, $coupon);

        $this->actingAs($user)->delete('/voucher/' . $coupon->code . '/luu');
        $this->assertDatabaseMissing('coupon_user', ['user_id' => $user->id, 'coupon_id' => $coupon->id]);

        $this->actingAs($user)->post('/voucher/' . $coupon->code . '/luu')->assertRedirect();
        $this->assertDatabaseHas('coupon_user', ['user_id' => $user->id, 'coupon_id' => $coupon->id]);
    }
}
