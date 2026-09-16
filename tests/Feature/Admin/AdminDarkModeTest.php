<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Shop\DisplayScheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Giao diện tối của trang quản trị. */
class AdminDarkModeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    #[Test]
    public function chon_toi_thi_trang_quan_tri_toi_ngay_tu_may_chu(): void
    {
        $html = $this->actingAs($this->admin())
            ->withCookie(DisplayScheme::COOKIE, DisplayScheme::TOI)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#<html[^>]*data-scheme="toi"[^>]*data-bs-theme="dark"#s', $html);
    }

    #[Test]
    public function theo_he_thong_thi_de_trinh_duyet_quyet_va_co_nut_doi(): void
    {
        $html = $this->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#<html[^>]*data-scheme="auto"#s', $html);
        $this->assertDoesNotMatchRegularExpression('#<html[^>]*data-bs-theme=#s', $html);
        $this->assertStringContainsString("setAttribute('data-bs-theme'", $html);
        $this->assertStringContainsString('data-scheme-toggle', $html);
    }

    #[Test]
    public function trang_cua_hang_KHONG_bi_gan_bo_mau_toi_cua_bootstrap(): void
    {
        $html = $this->withCookie(DisplayScheme::COOKIE, DisplayScheme::TOI)
            ->get('/san-pham')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-bs-theme', $html);
    }
}
