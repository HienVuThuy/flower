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

/** Xác thực email bằng mã OTP 6 chữ số. */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function unverified(): User
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($user);

        return $user;
    }

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

    #[Test]
    public function dang_ky_xong_thi_chua_xac_thuc_va_duoc_dua_toi_trang_nhap_ma(): void
    {
        $this->post('/dang-ky', [
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
        $this->post('/dang-ky', [
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
        $this->post('/dang-ky', [
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
        $user = $this->unverified();
        $this->seedCode($user);

        for ($i = 0; $i < EmailVerifier::MAX_ATTEMPTS; $i++) {
            $this->post(route('verification.confirm'), ['code' => '000000']);
        }

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

        $this->assertSame(0, EmailVerificationCode::first()->attempts);
    }

    #[Test]
    public function phai_cho_het_thoi_gian_moi_duoc_gui_lai_ma(): void
    {
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
        $user = $this->unverified();
        $this->seedCode($user);

        EmailVerificationCode::where('user_id', $user->id)
            ->update(['sent_at' => now()->subMinutes(5)]);

        $verifier = app(EmailVerifier::class);
        $this->assertSame(0, $verifier->secondsUntilResend($user));

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
        $user = $this->unverified();
        $this->seedCode($user);

        $this->post(route('verification.confirm'), ['code' => '135791'])
            ->assertSessionHasErrors('code')
            ->assertSessionHasInput('code', '135791');
    }

    #[Test]
    public function trang_nhap_ma_bao_loi_bang_khoi_do_ro_rang(): void
    {
        $user = $this->unverified();
        $this->seedCode($user);

        $this->post(route('verification.confirm'), ['code' => '000000']);

        $html = $this->from(route('verification.notice'))
            ->followingRedirects()
            ->post(route('verification.confirm'), ['code' => '000000'])
            ->getContent();

        $this->assertStringContainsString('alert alert-danger', $html);
        $this->assertStringContainsString('Mã không đúng', $html);

        $this->assertLessThan(
            strpos($html, 'id="code"'),
            strpos($html, 'alert alert-danger'),
            'Thông báo lỗi phải nằm phía trên ô nhập, không phải dưới.',
        );
    }

    #[Test]
    public function chua_xac_thuc_thi_khong_vao_duoc_trang_ca_nhan(): void
    {
        $this->unverified();

        foreach (['/tai-khoan', '/dia-chi', '/don-hang', '/lich-cham-cay'] as $path) {
            $this->get($path)->assertRedirect(route('verification.notice'));
        }
    }

    #[Test]
    public function chua_xac_thuc_van_mua_hang_binh_thuong(): void
    {
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
        $this->get(route('verification.notice'))->assertRedirect('/dang-nhap');
        $this->post(route('verification.confirm'), ['code' => '135790'])->assertRedirect('/dang-nhap');
    }

    #[Test]
    public function lien_ket_co_chu_ky_hop_le_van_xac_thuc_duoc(): void
    {
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
        $user = User::factory()->unverified()->create();
        $verifier = app(EmailVerifier::class);
        $verifier->send($user);

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
