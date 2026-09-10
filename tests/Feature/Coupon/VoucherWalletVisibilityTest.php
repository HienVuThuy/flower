<?php

namespace Tests\Feature\Coupon;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mã hết hiệu lực RỜI KHỎI VÍ, không nằm lẫn trong đó.
 * ============================================================
 * Trước đây chúng vẫn hiện, chỉ chuyển xám. Ví dùng vài tháng là đầy mã
 * hết hạn, và mã còn dùng được chìm vào giữa chúng — đúng thứ ví voucher
 * sinh ra để khỏi phải lọc bằng mắt.
 *
 * BA CÁCH CHẾT, phải bắt đủ cả ba (luật ở CouponWallet::conDungDuoc):
 *
 *   1. hết hạn / bị ngừng
 *   2. hết lượt trên toàn hệ thống
 *   3. khách đã dùng hết suất của mình
 *
 * Bỏ sót một vế thì mã loại đó vẫn nằm lại, và không có gì báo.
 *
 * KHÔNG XOÁ HÀNG DỮ LIỆU. `coupon_user` là bằng chứng chống dùng quá
 * suất; xoá đi là mở lại đúng lỗ hổng đó. Chúng chỉ rời khỏi danh sách
 * chính, và vẫn xem lại được qua `?het-han=1`.
 */
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

    /* ================= BA CÁCH CHẾT ================= */

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
        /*
         * VẾ DỄ QUÊN NHẤT: mã vẫn còn hạn, hệ thống vẫn còn lượt — chỉ
         * riêng khách này đã hết suất. Lọc bằng `isRunning()` đơn thuần
         * sẽ bỏ sót đúng trường hợp này.
         */
        $user = User::factory()->create();
        $coupon = $this->ma(['per_user_limit' => 1, 'name' => 'Ma da dung het suat']);
        $this->vaoVi($user, $coupon, daDung: 1);

        $this->actingAs($user)
            ->get('/voucher')
            ->assertOk()
            ->assertDontSee('Ma da dung het suat')
            ->assertDontSee('Bạn đã dùng hết lượt');
    }

    /* ================= MÃ CÒN DÙNG ĐƯỢC THÌ PHẢI Ở LẠI ================= */

    #[Test]
    public function ma_con_dung_duoc_van_nam_trong_vi(): void
    {
        /*
         * VẾ NGƯỢC LẠI, và là vế quan trọng hơn: dọn quá tay thì khách
         * mất mã đang dùng được, và đó là lỗi tệ hơn hẳn việc ví hơi bừa.
         */
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
        // Dùng 1/3 suất thì vẫn còn 2 lần nữa — chưa chết.
        $user = User::factory()->create();
        $coupon = $this->ma(['per_user_limit' => 3, 'name' => 'Ma dung con suat']);
        $this->vaoVi($user, $coupon, daDung: 1);

        $this->actingAs($user)
            ->get('/voucher')
            ->assertOk()
            ->assertSee('Ma dung con suat');
    }

    /* ================= VẪN XEM LẠI ĐƯỢC ================= */

    #[Test]
    public function co_duong_xem_lai_ma_het_hieu_luc(): void
    {
        /*
         * "Mã của tôi biến đâu mất?" là câu hỏi sẽ được hỏi. Dọn đi mà
         * không để đường quay lại là biến một tính năng dọn dẹp thành
         * một vụ mất dữ liệu trong mắt khách.
         */
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
        /*
         * Hàng `coupon_user` là thứ `per_user_limit` đếm dựa vào. Dọn
         * khỏi giao diện mà xoá luôn hàng thì một mã "mỗi người một lần"
         * thành mã không giới hạn cho ai biết đợi nó hết hạn.
         */
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
        /*
         * Ví chỉ có mã hết hạn phải đọc ra là TRỐNG, kèm lời mời lưu mã
         * mới — chứ không phải một khoảng trắng không giải thích gì.
         */
        $user = User::factory()->create();
        $this->vaoVi($user, $this->ma(['ends_at' => now()->subDay()]));

        $this->actingAs($user)
            ->get('/voucher')
            ->assertOk()
            ->assertSee('Ví voucher đang trống.');
    }
}
