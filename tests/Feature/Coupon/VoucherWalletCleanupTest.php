<?php

namespace Tests\Feature\Coupon;

use App\Models\Coupon;
use App\Models\User;
use App\Services\Coupon\CouponWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dọn ví voucher.
 * ============================================================
 * LỖI ĐÃ SỬA — và nó ẩn ở một chỗ mà đọc mã nguồn khó thấy.
 *
 * Nút "Bỏ khỏi ví" nằm bên trong nhánh `@elseif($saved)` của một chuỗi
 * điều kiện. Ba nhánh đứng TRƯỚC nó — "đã dùng hết lượt", "đã hết mã",
 * "hết hạn sử dụng" — bắt trước, nên một mã đã lưu mà hết hạn không bao
 * giờ chạy tới nhánh `$saved`.
 *
 * Kết quả: mã hết hạn nằm lại trong ví VĨNH VIỄN, không nút nào chạm tới
 * được. Mọi bài kiểm thử lúc đó đều xanh, vì không bài nào mở trang ví
 * với một mã đã lưu VÀ đã hết hạn.
 *
 * ============================================================
 * HAI ĐƯỜNG BỎ MÃ, VÀ CHÚNG KHÔNG THAY THẾ CHO NHAU
 *
 * Hàng `coupon_user` là bằng chứng khách đã dùng mã mấy lần, và
 * `per_user_limit` đếm dựa vào nó. Nên:
 *
 *   - mã CHƯA dùng -> xoá hàng thật (không có gì để giữ);
 *   - mã ĐÃ dùng   -> chỉ ẩn, hàng còn nguyên.
 *
 * Cho xoá thẳng cả hai thì một mã "mỗi người một lần" thành mã không
 * giới hạn cho ai biết bấm nút xoá.
 */
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

    /** Đặt mã vào ví, bỏ qua mọi luật của claim() — kể cả mã đã hết hạn. */
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
        /*
         * BÀI CHÍNH — đúng tình huống người dùng gặp.
         *
         * Lưu mã lúc còn hạn, chưa kịp dùng, mã hết hạn. Trước khi sửa,
         * thẻ chỉ hiện chữ "Hết hạn sử dụng" và không có nút nào.
         */
        $user = User::factory()->create();
        $coupon = $this->ma(['ends_at' => now()->subDay()]);
        $this->vaoVi($user, $coupon);

        /*
         * NÚT NẰM Ở MỤC "MÃ HẾT HIỆU LỰC", không còn ở ví chính.
         *
         * Ví chính nay chỉ giữ mã còn dùng được — xem VoucherController.
         * Khả năng bỏ mã KHÔNG mất đi, nó chuyển chỗ; có bài riêng canh
         * đúng việc thẻ này đã rời khỏi ví.
         */
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
        /*
         * BÀI CANH CHỐNG GIAN LẬN.
         *
         * Xoá hàng của một mã đã dùng là cho khách dùng lại từ đầu — một
         * mã "mỗi người một lần" thành mã không giới hạn.
         */
        $user = User::factory()->create();
        $coupon = $this->ma(['per_user_limit' => 1]);
        $this->vaoVi($user, $coupon, daDung: 1);

        $this->actingAs($user)->delete('/voucher/' . $coupon->code . '/luu')->assertRedirect();

        // Hàng CÒN NGUYÊN, chỉ được đánh dấu ẩn.
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

        // Giới hạn mỗi tài khoản VẪN tính — đó là cả lý do không xoá hàng.
        $this->assertTrue($wallet->userLimitReached($user, $coupon));
    }

    #[Test]
    public function co_duong_quay_lai_cho_ma_da_an(): void
    {
        /*
         * Một nút chỉ đi một chiều là cái bẫy: bấm nhầm rồi thì mã biến
         * mất và khách không biết nó đi đâu.
         */
        $user = User::factory()->create();
        $coupon = $this->ma(['per_user_limit' => 1]);
        $this->vaoVi($user, $coupon, daDung: 1);

        app(CouponWallet::class)->discard($user, $coupon);

        // Trang ví mời xem lại, không im lặng.
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
        // Bày một mục "đã ẩn (0)" cho mọi người là thêm thứ để đọc mà
        // không thêm thông tin nào.
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

        // Ví của tôi không suy suyển: hàng của người khác không có nên
        // lệnh xoá của họ không chạm được vào hàng của tôi.
        $this->assertDatabaseHas('coupon_user', [
            'user_id' => $toi->id,
            'coupon_id' => $coupon->id,
        ]);
    }

    #[Test]
    public function khach_vang_lai_khong_bo_duoc_ma_nao(): void
    {
        $coupon = $this->ma();

        $this->delete('/voucher/' . $coupon->code . '/luu')->assertRedirect('/login');
        $this->patch('/voucher/' . $coupon->code . '/luu')->assertRedirect('/login');
    }

    #[Test]
    public function bo_ma_chua_dung_thi_luu_lai_duoc_neu_doi_y(): void
    {
        // Xoá hẳn (thay vì ẩn) với mã chưa dùng chính là để chuyện này
        // làm được: khách bỏ nhầm thì lưu lại từ đầu, không kẹt gì cả.
        $user = User::factory()->create();
        $coupon = $this->ma();
        $this->vaoVi($user, $coupon);

        $this->actingAs($user)->delete('/voucher/' . $coupon->code . '/luu');
        $this->assertDatabaseMissing('coupon_user', ['user_id' => $user->id, 'coupon_id' => $coupon->id]);

        $this->actingAs($user)->post('/voucher/' . $coupon->code . '/luu')->assertRedirect();
        $this->assertDatabaseHas('coupon_user', ['user_id' => $user->id, 'coupon_id' => $coupon->id]);
    }
}
