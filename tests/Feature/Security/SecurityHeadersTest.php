<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Mọi trang web đều gửi kèm header bảo mật, kể cả trang lỗi và trang cần đăng nhập. */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function assertCoHeaderBaoMat($response): void
    {
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    #[Test]
    public function trang_cong_khai_co_header_bao_mat(): void
    {
        $this->assertCoHeaderBaoMat($this->get('/')->assertOk());
    }

    #[Test]
    public function trang_404_va_trang_can_dang_nhap_cung_co(): void
    {
        $this->assertCoHeaderBaoMat($this->get('/khong-co-trang-nay')->assertNotFound());
        $this->assertCoHeaderBaoMat($this->get(route('shop.orders.index'))->assertRedirect());
    }

    #[Test]
    public function trang_quan_tri_cung_co(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertCoHeaderBaoMat($this->actingAs($admin)->get(route('admin.dashboard'))->assertOk());
    }
}
