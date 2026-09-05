<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\Auth\EmailVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Auth\EmailVerificationException;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Xác thực email bằng mã OTP 6 chữ số.
 * ============================================================
 * Đây là một CƠ CHẾ BẢO MẬT, nên phần được canh chừng kỹ nhất không
 * phải "đường đi đúng chạy được" mà là những đường đi SAI phải bị chặn:
 * đoán mã, dùng lại mã cũ, mã hết hạn, nhờ hệ thống gửi thư rác.
 *
 * Mail::fake() ở mọi bài — không bài kiểm tra nào được gửi thư thật ra
 * ngoài. Nội dung mã thì lấy từ đối tượng Mailable đã bị chặn lại.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** Tài khoản chưa xác thực, đã đăng nhập. */
    private function unverified(): User
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($user);

        return $user;
    }

    /** Đặt sẵn một mã đã biết, đúng cách dịch vụ vẫn lưu. */
    private function seedCode(User $user, string $code = '135790', ?string $expiresAt = null): void
    {
        EmailVerificationCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'expires_at' => $expiresAt ?? now()->addMinutes(EmailVerifier::TTL_MINUTES),
                'sent_at' => now(),
                'attempts' => 0,
            ],
        );
    }

    // ================= ĐĂNG KÝ =================

    #[Test]
    public function dang_ky_xong_thi_chua_xac_thuc_va_duoc_dua_toi_trang_nhap_ma(): void
    {
        $this->post('/register', [
            'name' => 'Nguyễn Văn Kiểm Thử',
            'email' => 'kiemthu@example.com',
            'password' => 'MatKhau@12345',
            'password_confirmation' => 'MatKhau@12345',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'kiemthu@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at, 'Đăng ký xong chưa được coi là đã xác thực.');
        Mail::assertSent(EmailVerificationMail::class);
    }

    #[Test]
    public function ma_gui_di_gom_dung_6_chu_so(): void
    {
        $this->post('/register', [
            'name' => 'Nguyễn Văn Kiểm Thử',
            'email' => 'kiemthu@example.com',
            'password' => 'MatKhau@12345',
            'password_confirmation' => 'MatKhau@12345',
        ]);

        Mail::assertSent(EmailVerificationMail::class, function (EmailVerificationMail $mail) {
            return preg_match('/^\d{6}$/', $mail->code) === 1;
        });
    }

    #[Test]
    public function co_so_du_lieu_chi_luu_bam_chu_khong_luu_ma_goc(): void
    {
        // Ai đọc được cơ sở dữ liệu — bản sao lưu, log truy vấn — không
        // được phép xác thực hộ người khác.
        $this->post('/register', [
            'name' => 'Nguyễn Văn Kiểm Thử',
            'email' => 'kiemthu@example.com',
            'password' => 'MatKhau@12345',
            'password_confirmation' => 'MatKhau@12345',
        ]);

        $maGoc = null;
        Mail::assertSent(EmailVerificationMail::class, function (EmailVerificationMail $mail) use (&$maGoc) {
            $maGoc = $mail->code;

            return true;
        });

        $luu = EmailVerificationCode::first();

        $this->assertNotSame($maGoc, $luu->code_hash);
        $this->assertTrue(Hash::check($maGoc, $luu->code_hash), 'Băm phải khớp với mã đã gửi.');
    }

    // ================= NHẬP MÃ =================

    #[Test]
    public function go_dung_ma_thi_duoc_xac_thuc_va_ma_bi_xoa(): void
    {
        $user = $this->unverified();
        $this->seedCode($user);

        $this->post(route('verification.confirm'), ['code' => '135790'])
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame(0, EmailVerificationCode::count(), 'Mã đã dùng không được để lại.');
    }

    #[Test]
    public function ma_da_dung_khong_dung_lai_duoc(): void
    {
        $user = $this->unverified();
        $this->seedCode($user);
        $this->post(route('verification.confirm'), ['code' => '135790']);

        // Giả lập trạng thái chưa xác thực để thử dùng lại đúng mã cũ.
        $user->forceFill(['email_verified_at' => null])->save();

        $this->post(route('verification.confirm'), ['code' => '135790'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function go_sai_ma_thi_bao_loi_va_khong_xac_thuc(): void
    {
        $user = $this->unverified();
        $this->seedCode($user);

        $this->post(route('verification.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(1, EmailVerificationCode::first()->attempts);
    }

    #[Test]
    public function go_sai_qua_nhieu_lan_thi_ma_bi_huy(): void
    {
        // Chặn dò mã. Đếm theo TÀI KHOẢN nên đổi IP cũng không thoát —
        // throttle theo IP là lớp thứ hai, không phải lớp chính.
        $user = $this->unverified();
        $this->seedCode($user);

        for ($i = 0; $i < EmailVerifier::MAX_ATTEMPTS; $i++) {
            $this->post(route('verification.confirm'), ['code' => '000000']);
        }

        // Lần này gõ ĐÚNG mã, nhưng đã hết lượt.
        $this->post(route('verification.confirm'), ['code' => '135790'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(0, EmailVerificationCode::count(), 'Hết lượt thì mã phải bị huỷ.');
    }

    #[Test]
    public function ma_het_han_thi_khong_dung_duoc(): void
    {
        $user = $this->unverified();
        $this->seedCode($user, '135790', now()->subMinute());

        $this->post(route('verification.confirm'), ['code' => '135790'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function ma_bat_dau_bang_so_0_van_hop_le(): void
    {
        // Ép sang số nguyên là mất số 0 ở đầu và mã đúng bị coi là sai.
        $user = $this->unverified();
        $this->seedCode($user, '007355');

        $this->post(route('verification.confirm'), ['code' => '007355'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function ma_khong_du_6_chu_so_bi_chan_ngay_o_khau_kiem_tra(): void
    {
        $user = $this->unverified();
        $this->seedCode($user);

        $this->post(route('verification.confirm'), ['code' => '12ab'])
            ->assertSessionHasErrors('code');

        // Không được tính là một lần đoán: nó còn chưa tới được dịch vụ.
        $this->assertSame(0, EmailVerificationCode::first()->attempts);
    }

    // ================= GỬI LẠI =================

    #[Test]
    public function phai_cho_het_thoi_gian_moi_duoc_gui_lai_ma(): void
    {
        // Chặn dùng hệ thống làm máy gửi thư rác vào hộp thư người khác.
        $user = $this->unverified();
        $this->post(route('verification.send'));
        Mail::assertSentCount(1);

        $this->post(route('verification.send'))->assertSessionHas('error');
        Mail::assertSentCount(1);
    }

    #[Test]
    public function gui_lai_ma_thi_ma_cu_het_hieu_luc(): void
    {
        $user = $this->unverified();
        $this->seedCode($user, '111111');

        // Lùi thời gian để qua khỏi khoảng chờ gửi lại.
        EmailVerificationCode::where('user_id', $user->id)
            ->update(['sent_at' => now()->subMinutes(5)]);

        $this->post(route('verification.send'));

        $this->post(route('verification.confirm'), ['code' => '111111'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(1, EmailVerificationCode::count(), 'Chỉ được có MỘT mã sống cho mỗi tài khoản.');
    }

    #[Test]
    public function go_sai_ma_KHONG_duoc_dat_lai_dong_ho_cho_gui_lai(): void
    {
        /*
         * LỖI ĐÃ XẢY RA THẬT.
         *
         * Khoảng chờ gửi lại từng đo bằng `updated_at`, mà
         * increment('attempts') cũng chạm vào cột đó. Nghĩa là mỗi lần gõ
         * sai lại đẩy đồng hồ về 60 giây — đúng người đang cần mã mới
         * nhất lại là người bị chặn.
         *
         * Đo được trước khi sửa: thư gửi 5 phút trước -> chờ 0 giây;
         * gõ sai một lần -> chờ 59 giây.
         */
        $user = $this->unverified();
        $this->seedCode($user);

        // Thư đã gửi từ 5 phút trước: lẽ ra gửi lại được ngay.
        EmailVerificationCode::where('user_id', $user->id)
            ->update(['sent_at' => now()->subMinutes(5)]);

        $verifier = app(EmailVerifier::class);
        $this->assertSame(0, $verifier->secondsUntilResend($user));

        // Khách gõ sai một lần.
        $this->post(route('verification.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertSame(
            0,
            $verifier->secondsUntilResend($user),
            'Gõ sai không được làm khách phải chờ thêm mới xin được mã mới.',
        );
    }

    #[Test]
    public function go_sai_thi_giu_lai_ma_vua_go_de_khach_sua_dung_cho(): void
    {
        // Sai một chữ số trong sáu là chuyện thường. Xoá trắng ô thì họ
        // phải nhìn lại email và gõ lại cả sáu chữ.
        $user = $this->unverified();
        $this->seedCode($user);

        $this->post(route('verification.confirm'), ['code' => '135791'])
            ->assertSessionHasErrors('code')
            ->assertSessionHasInput('code', '135791');
    }

    #[Test]
    public function trang_nhap_ma_bao_loi_bang_khoi_do_ro_rang(): void
    {
        /*
         * Bài này DÒ CHUỖI trong HTML vì thứ đang được canh CHÍNH LÀ câu
         * chữ khách nhìn thấy.
         *
         * Trước đây lỗi chỉ là một dòng `text-danger small` nằm DƯỚI ô
         * nhập — dễ bị bỏ qua tới mức khách tưởng nút bấm không ăn rồi
         * bấm lại, đốt thêm một lượt thử.
         */
        $user = $this->unverified();
        $this->seedCode($user);

        $this->post(route('verification.confirm'), ['code' => '000000']);

        /*
         * from(...) — BẮT BUỘC.
         *
         * back() dựa vào Referer. Không đặt thì trong bài kiểm tra nó rơi
         * về '/', và ta đi soi trang chủ trong khi tưởng đang soi trang
         * nhập mã.
         */
        $html = $this->from(route('verification.notice'))
            ->followingRedirects()
            ->post(route('verification.confirm'), ['code' => '000000'])
            ->getContent();

        $this->assertStringContainsString('alert alert-danger', $html);
        $this->assertStringContainsString('Mã không đúng', $html);

        // Khối đỏ phải đứng TRƯỚC ô nhập trong trang.
        $this->assertLessThan(
            strpos($html, 'id="code"'),
            strpos($html, 'alert alert-danger'),
            'Thông báo lỗi phải nằm phía trên ô nhập, không phải dưới.',
        );
    }

    // ================= CHẶN TRANG =================

    #[Test]
    public function chua_xac_thuc_thi_khong_vao_duoc_trang_ca_nhan(): void
    {
        $this->unverified();

        foreach (['/tai-khoan', '/dia-chi', '/don-hang', '/yeu-thich', '/lich-cham-cay'] as $path) {
            $this->get($path)->assertRedirect(route('verification.notice'));
        }
    }

    #[Test]
    public function chua_xac_thuc_van_mua_hang_binh_thuong(): void
    {
        // Cửa hàng CHO PHÉP khách vãng lai đặt hàng. Khoá giỏ với người
        // đã đăng ký nhưng chưa xác thực, trong khi người không có tài
        // khoản mua thoải mái, là phạt đúng nhóm khách thân thiết hơn.
        $this->unverified();

        $this->get('/gio-hang')->assertOk();
        $this->get('/san-pham')->assertOk();
    }

    #[Test]
    public function xac_thuc_xong_thi_vao_duoc_trang_ca_nhan(): void
    {
        $user = $this->unverified();
        $this->seedCode($user);
        $this->post(route('verification.confirm'), ['code' => '135790']);

        $this->get('/tai-khoan')->assertOk();
        $this->get('/dia-chi')->assertOk();
    }

    #[Test]
    public function da_xac_thuc_roi_thi_khong_con_o_trang_nhap_ma(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('verification.notice'))->assertRedirect();
    }

    #[Test]
    public function khach_chua_dang_nhap_bi_dua_ve_trang_dang_nhap(): void
    {
        $this->get(route('verification.notice'))->assertRedirect('/login');
        $this->post(route('verification.confirm'), ['code' => '135790'])->assertRedirect('/login');
    }

    // ================= ĐƯỜNG LIÊN KẾT CÓ CHỮ KÝ =================

    #[Test]
    public function lien_ket_co_chu_ky_hop_le_van_xac_thuc_duoc(): void
    {
        // Cách mặc định của Laravel, giữ lại đúng như bài thực hành yêu cầu.
        $user = $this->unverified();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function lien_ket_sai_chu_ky_bi_tu_choi(): void
    {
        // Thiếu phép kiểm này thì ai đoán đúng {id} và {hash} là xác thực
        // hộ được người khác.
        $user = $this->unverified();

        $this->get(route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]))->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function lien_ket_het_han_bi_tu_choi(): void
    {
        $user = $this->unverified();

        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function gioi_han_5_lan_go_van_dung_khi_nhieu_request_cung_luc(): void
    {
        /*
         * Trước khi sửa, phép kiểm "còn lượt không" và phép cộng bộ đếm
         * là hai câu lệnh rời nhau — nhiều request cùng lúc đều đọc thấy
         * còn lượt và đều được đoán. Giới hạn 5 lần trở thành vô nghĩa,
         * mà nó là hàng rào duy nhất trước một mã sáu chữ số.
         *
         * Bài này không dựng được nhiều tiến trình, nên nó canh thứ đã
         * sửa được: bộ đếm do CƠ SỞ DỮ LIỆU chốt, nên lần thứ sáu bị
         * chặn dù bản ghi đang nằm trong bộ nhớ nói gì đi nữa.
         */
        $user = User::factory()->unverified()->create();
        $verifier = app(EmailVerifier::class);
        $verifier->send($user);

        // Năm lần đầu: sai mã, và bộ đếm phải nhích đúng từng lần.
        for ($i = 1; $i <= 5; $i++) {
            try {
                $verifier->confirm($user, '000000');
                $this->fail("Lần thứ {$i} đáng lẽ phải báo sai mã.");
            } catch (EmailVerificationException $e) {
                $this->assertStringContainsString('Mã không đúng', $e->getMessage());
            }

            $this->assertSame(
                $i,
                EmailVerificationCode::where('user_id', $user->id)->value('attempts'),
                "Sau lần thứ {$i}, bộ đếm phải là {$i}.",
            );
        }

        // Lần thứ sáu: hết lượt, và bản ghi mã bị xoá để buộc xin mã mới.
        try {
            $verifier->confirm($user, '000000');
            $this->fail('Lần thứ sáu đáng lẽ phải bị chặn.');
        } catch (EmailVerificationException $e) {
            $this->assertStringContainsString('quá nhiều lần', $e->getMessage());
        }

        $this->assertSame(
            0,
            EmailVerificationCode::where('user_id', $user->id)->count(),
            'Hết lượt thì bản ghi mã phải bị xoá, buộc khách xin mã mới.',
        );
    }

    #[Test]
    public function khong_gui_hai_ma_cung_luc(): void
    {
        /*
         * Hai cú bấm sát nhau: chỉ một cái được đi tiếp. Nếu cả hai cùng
         * gửi thì mã sinh sau ghi đè mã sinh trước, và khách nhập mã
         * trong thư họ thấy trước sẽ bị báo sai — không có cách nào đoán
         * ra vì sao.
         *
         * Giữ sẵn khoá ở đây thay cho "request kia đang chạy".
         */
        $user = User::factory()->unverified()->create();

        $khoa = Cache::lock('gui-ma-xac-thuc:'.$user->id, 10);
        $this->assertTrue($khoa->get(), 'Phải lấy được khoá khi chưa ai giữ.');

        try {
            $this->expectException(EmailVerificationException::class);
            app(EmailVerifier::class)->send($user);
        } finally {
            $khoa->release();
        }
    }
}
