<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Trang Hồ sơ chia thành ba mục. */
class ProfileTabsTest extends TestCase
{
    use RefreshDatabase;

    private function khach(): User
    {
        return User::factory()->create();
    }

    #[Test]
    public function mac_dinh_mo_muc_thong_tin(): void
    {
        $this->actingAs($this->khach())
            ->get('/tai-khoan')
            ->assertOk()
            ->assertSee('Thông tin cá nhân')
            ->assertDontSee('Thiết bị đang đăng nhập')
            ->assertDontSee('Nền sáng / tối');
    }

    #[Test]
    public function muc_bao_mat_gom_dung_ba_khoi_lien_quan(): void
    {
        $this->actingAs($this->khach())
            ->get('/tai-khoan?muc=bao-mat')
            ->assertOk()
            ->assertSee('Đổi mật khẩu')
            ->assertSee('Thiết bị đang đăng nhập')
            ->assertSee('Xoá tài khoản')
            ->assertDontSee('Thông tin cá nhân');
    }

    #[Test]
    public function muc_tuy_chon_gom_nen_sang_toi_va_thu_thong_bao(): void
    {
        $this->actingAs($this->khach())
            ->get('/tai-khoan?muc=tuy-chon')
            ->assertOk()
            ->assertSee('Nền sáng / tối')
            ->assertSee('Thư thông báo')
            ->assertDontSee('Đổi mật khẩu');
    }

    #[Test]
    public function tham_so_la_quay_ve_muc_mac_dinh_chu_khong_bao_loi(): void
    {
        $this->actingAs($this->khach())
            ->get('/tai-khoan?muc=khong-co-that')
            ->assertOk()
            ->assertSee('Thông tin cá nhân');
    }

    #[Test]
    public function khoi_tom_tat_hien_o_MOI_muc(): void
    {
        foreach (['thong-tin', 'bao-mat', 'tuy-chon'] as $muc) {
            $this->actingAs($this->khach())
                ->get("/tai-khoan?muc={$muc}")
                ->assertOk()
                ->assertSee(route('shop.orders.index'))
                ->assertSee(route('shop.reviews.mine'));
        }
    }

    #[Test]
    public function bieu_mau_loi_quay_ve_DUNG_muc_dang_mo(): void
    {
        $user = $this->khach();

        $this->actingAs($user)
            ->from('/tai-khoan?muc=bao-mat')
            ->put('/tai-khoan/mat-khau', [
                'current_password' => 'sai-be-bet',
                'password' => 'MatKhauMoi@123',
                'password_confirmation' => 'MatKhauMoi@123',
            ])
            ->assertRedirect('/tai-khoan?muc=bao-mat');
    }

    #[Test]
    public function moi_muc_deu_co_the_gui_duong_dan_cho_nguoi_khac(): void
    {
        $html = $this->actingAs($this->khach())
            ->get('/tai-khoan?muc=bao-mat')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-current="page"', $html);

        $this->assertMatchesRegularExpression(
            '#href="[^"]*muc=tuy-chon"#',
            $html,
            'Thanh mục phải là liên kết thật để mở tab mới và lưu dấu trang được.',
        );
    }

    #[Test]
    public function khong_con_the_card_long_trong_card(): void
    {
        foreach (['thong-tin', 'bao-mat', 'tuy-chon'] as $muc) {
            $html = $this->actingAs($this->khach())
                ->get("/tai-khoan?muc={$muc}")
                ->assertOk()
                ->getContent();

            $dom = new \DOMDocument();

            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            libxml_clear_errors();

            $long = (new \DOMXPath($dom))->query(
                '//*[contains(@class, "surface-card")]'
                .'//*[contains(@class, "surface-card")]'
            );

            $this->assertSame(
                0,
                $long->length,
                "Mục {$muc}: không được có khối surface-card nào nằm trong khối khác.",
            );
        }
    }
}
