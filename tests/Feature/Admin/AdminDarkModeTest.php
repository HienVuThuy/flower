<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Shop\DisplayScheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Giao diện tối của trang quản trị.
 *
 * Màu và độ tương phản đã được đo trên trình duyệt (xem khối tối trong
 * admin/admin.css). Ở đây canh phần máy chủ: trang quản trị đọc CÙNG lựa chọn
 * sáng/tối với trang cửa hàng, và đặt sẵn thuộc tính từ khung hình đầu tiên.
 */
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
        /*
         * Đặt SẴN data-bs-theme từ máy chủ: để JavaScript đặt sau khi tải thì
         * bảng và ô nhập của Bootstrap nháy trắng trước rồi mới tối.
         */
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

        // "auto": máy chủ không biết máy người dùng đang sáng hay tối.
        $this->assertMatchesRegularExpression('#<html[^>]*data-scheme="auto"#s', $html);
        $this->assertDoesNotMatchRegularExpression('#<html[^>]*data-bs-theme=#s', $html);
        $this->assertStringContainsString("setAttribute('data-bs-theme'", $html);
        $this->assertStringContainsString('data-scheme-toggle', $html);
    }

    #[Test]
    public function trang_cua_hang_KHONG_bi_gan_bo_mau_toi_cua_bootstrap(): void
    {
        // Cửa hàng có bộ màu tối riêng cho từng component (core/dark.css).
        // Gắn thêm bộ của Bootstrap là đổi giao diện cửa hàng ngoài ý muốn.
        $html = $this->withCookie(DisplayScheme::COOKIE, DisplayScheme::TOI)
            ->get('/san-pham')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-bs-theme', $html);
    }
}
