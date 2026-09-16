<?php

namespace Tests\Feature\Coupon;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Mã hết hiệu lực RỜI KHỎI VÍ, không nằm lẫn trong đó. */
class VoucherWalletVisibilityTest extends TestCase
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
    public function ma_het_han_khong_con_trong_vi(): void
    {
        $user = User::factory()->create();
        $coupon = $this->ma(['ends_at' => now()->subDay(), 'name' => 'Ma da het han']);
        $this->vaoVi($user, $coupon);

        $this->actingAs($user)
            ->get('/voucher')
            ->assertOk()
            ->assertDontSee('Ma da het han')
            ->assertDontSee('Hết hạn sử dụng');
    }

    #[Test]
    public function ma_het_luot_toan_he_thong_khong_con_trong_vi(): void
    {
        $user = User::factory()->create();
        $coupon = $this->ma(['usage_limit' => 5, 'name' => 'Ma da het luot']);
        $coupon->forceFill(['used_count' => 5])->save();
        $this->vaoVi($user, $coupon);

        $this->actingAs($user)
            ->get('/voucher')
            ->assertOk()
            ->assertDontSee('Ma da het luot');
    }

    #[Test]
    public function ma_khach_da_dung_het_suat_khong_con_trong_vi(): void
    {
        $user = User::factory()->create();
        $coupon = $this->ma(['per_user_limit' => 1, 'name' => 'Ma da dung het suat']);
        $this->vaoVi($user, $coupon, daDung: 1);

        $this->actingAs($user)
            ->get('/voucher')
            ->assertOk()
            ->assertDontSee('Ma da dung het suat')
            ->assertDontSee('Bạn đã dùng hết lượt');
    }

    #[Test]
    public function ma_con_dung_duoc_van_nam_trong_vi(): void
    {
        $user = User::factory()->create();
        $coupon = $this->ma(['name' => 'Ma con dung duoc']);
        $this->vaoVi($user, $coupon);

        $this->actingAs($user)
            ->get('/voucher')
            ->assertOk()
            ->assertSee('Ma con dung duoc');
    }

    #[Test]
    public function ma_da_dung_mot_phan_van_nam_trong_vi(): void
    {
        $user = User::factory()->create();
        $coupon = $this->ma(['per_user_limit' => 3, 'name' => 'Ma dung con suat']);
        $this->vaoVi($user, $coupon, daDung: 1);

        $this->actingAs($user)
            ->get('/voucher')
            ->assertOk()
            ->assertSee('Ma dung con suat');
    }

    #[Test]
    public function co_duong_xem_lai_ma_het_hieu_luc(): void
    {
        $user = User::factory()->create();
        $this->vaoVi($user, $this->ma(['ends_at' => now()->subDay(), 'name' => 'Ma da het han']));

        $this->actingAs($user)
            ->get('/voucher')
            ->assertOk()
            ->assertSee('Xem 1 mã hết hiệu lực');

        $this->actingAs($user)
            ->get('/voucher?het-han=1')
            ->assertOk()
            ->assertSee('Ma da het han')
            ->assertSee('Mã hết hiệu lực (1)');
    }

    #[Test]
    public function KHONG_xoa_hang_du_lieu_chong_dung_qua_suat(): void
    {
        $user = User::factory()->create();
        $coupon = $this->ma(['per_user_limit' => 1]);
        $this->vaoVi($user, $coupon, daDung: 1);

        $this->actingAs($user)->get('/voucher')->assertOk();

        $this->assertDatabaseHas('coupon_user', [
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'used_count' => 1,
        ]);
    }

    #[Test]
    public function vi_chi_con_ma_chet_thi_bao_la_trong(): void
    {
        $user = User::factory()->create();
        $this->vaoVi($user, $this->ma(['ends_at' => now()->subDay()]));

        $this->actingAs($user)
            ->get('/voucher')
            ->assertOk()
            ->assertSee('Ví voucher đang trống.');
    }
}
