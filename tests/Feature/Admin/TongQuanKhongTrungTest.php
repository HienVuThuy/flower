<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tổng quan không vẽ lại biểu đồ của Phân tích.
 * ============================================================
 * Trước đây Tổng quan và Phân tích › Tổng hợp cùng vẽ "Doanh thu theo
 * ngày", cơ cấu trạng thái đơn và "Bán chạy" với cùng bộ chọn kỳ — hai
 * màn hình cùng làm một việc.
 */
class TongQuanKhongTrungTest extends TestCase
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
    public function tong_quan_KHONG_con_ba_bieu_do_trung(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Doanh thu theo ngày')
            ->assertDontSee('Đơn trong kỳ đang ở đâu')
            ->assertDontSee('Bán chạy trong kỳ');
    }

    #[Test]
    public function tong_quan_van_giu_viec_can_lam_bon_con_so_va_don_gan_day(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Việc cần làm')
            ->assertSee('Doanh thu thuần')
            ->assertSee('Đơn huỷ')
            ->assertSee('Đơn gần đây')
            ->assertSee('href="' . route('admin.analytics.index'), false);
    }

    #[Test]
    public function bieu_do_van_con_o_trang_phan_tich(): void
    {
        // Gỡ khỏi Tổng quan chứ không gỡ khỏi hệ thống.
        $this->actingAs($this->admin())
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Doanh thu theo ngày')
            ->assertSee('Cơ cấu trạng thái đơn')
            ->assertSee('Bán chạy nhất');
    }
}
