<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Đổi vai trò và khoá tài khoản. */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $ten = 'Quản trị viên'): User
    {
        $user = User::factory()->create(['name' => $ten]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    #[Test]
    public function admin_doi_duoc_vai_tro_cua_nguoi_khac(): void
    {
        $this->admin();
        $khach = User::factory()->create();

        $this->actingAs($this->admin('Người thao tác'))
            ->patch("/admin/users/{$khach->id}/vai-tro", ['role' => 'admin'])
            ->assertRedirect();

        $this->assertSame(UserRole::Admin, $khach->fresh()->role);
    }

    #[Test]
    public function khong_tu_doi_vai_tro_cua_chinh_minh(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/vai-tro", ['role' => 'customer'])
            ->assertRedirect();

        $this->assertSame(
            UserRole::Admin,
            $admin->fresh()->role,
            'Vai trò của chính người thao tác không được đổi.',
        );
    }

    #[Test]
    public function khong_ha_quyen_quan_tri_vien_dung_duoc_cuoi_cung(): void
    {
        $conLai = $this->admin('Người duy nhất còn dùng được');

        $daKhoa = $this->admin('Admin đang bị khoá');
        $daKhoa->locked_at = now();
        $daKhoa->save();

        $this->actingAs($daKhoa)
            ->patch("/admin/users/{$conLai->id}/vai-tro", ['role' => 'customer'])
            ->assertRedirect();

        $this->assertSame(UserRole::Admin, $conLai->fresh()->role);
    }

    #[Test]
    public function ha_quyen_duoc_mot_admin_dang_bi_khoa(): void
    {
        $daKhoa = $this->admin('Admin đang bị khoá');
        $daKhoa->locked_at = now();
        $daKhoa->save();

        $this->actingAs($this->admin('Người thao tác'))
            ->patch("/admin/users/{$daKhoa->id}/vai-tro", ['role' => 'customer'])
            ->assertRedirect();

        $this->assertSame(UserRole::Customer, $daKhoa->fresh()->role);
    }

    #[Test]
    public function vai_tro_khong_co_that_bi_tu_choi(): void
    {
        $khach = User::factory()->create();

        $this->actingAs($this->admin())
            ->patch("/admin/users/{$khach->id}/vai-tro", ['role' => 'sieu_admin'])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Customer, $khach->fresh()->role);
    }

    #[Test]
    public function khach_thuong_khong_doi_duoc_vai_tro_cua_ai(): void
    {
        $nanNhan = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch("/admin/users/{$nanNhan->id}/vai-tro", ['role' => 'admin'])
            ->assertForbidden();

        $this->assertSame(UserRole::Customer, $nanNhan->fresh()->role);
    }

    #[Test]
    public function khoa_tai_khoan_thi_khong_dang_nhap_duoc_nua(): void
    {
        $khach = User::factory()->create(['password' => bcrypt('MatKhau123!')]);

        $this->actingAs($this->admin())
            ->patch("/admin/users/{$khach->id}/khoa", [
                'khoa' => 1,
                'ly_do' => 'Gửi đánh giá rác',
            ])
            ->assertRedirect();

        $this->assertNotNull($khach->fresh()->locked_at);

        $this->post('/logout');

        $this->post('/login', [
            'email' => $khach->email,
            'password' => 'MatKhau123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function ly_do_khoa_duoc_noi_cho_chinh_nguoi_bi_khoa(): void
    {
        $khach = User::factory()->create(['password' => bcrypt('MatKhau123!')]);
        $khach->locked_at = now();
        $khach->lock_reason = 'Đặt hàng ảo nhiều lần';
        $khach->save();

        $this->post('/login', [
            'email' => $khach->email,
            'password' => 'MatKhau123!',
        ])->assertInvalid(['email' => 'Đặt hàng ảo nhiều lần']);
    }

    #[Test]
    public function phien_dang_mo_bi_day_ra_ngay_khi_bi_khoa(): void
    {
        $khach = User::factory()->create();

        $this->actingAs($khach)->get('/tai-khoan')->assertOk();

        $khach->locked_at = now();
        $khach->save();

        $this->get('/tai-khoan')->assertRedirect('/login');
        $this->assertGuest();
    }

    #[Test]
    public function khong_tu_khoa_chinh_minh(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/khoa", ['khoa' => 1])
            ->assertRedirect();

        $this->assertNull($admin->fresh()->locked_at);
    }

    #[Test]
    public function mo_khoa_thi_xoa_luon_ly_do_cu(): void
    {
        $khach = User::factory()->create();
        $khach->locked_at = now();
        $khach->lock_reason = 'Lý do của lần trước';
        $khach->save();

        $this->actingAs($this->admin())
            ->patch("/admin/users/{$khach->id}/khoa", ['khoa' => 0])
            ->assertRedirect();

        $khach->refresh();

        $this->assertNull($khach->locked_at);
        $this->assertNull($khach->lock_reason);
    }

    #[Test]
    public function khach_thuong_khong_khoa_duoc_ai(): void
    {
        $nanNhan = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch("/admin/users/{$nanNhan->id}/khoa", ['khoa' => 1])
            ->assertForbidden();

        $this->assertNull($nanNhan->fresh()->locked_at);
    }
}
