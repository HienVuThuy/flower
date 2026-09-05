<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Đổi vai trò và khoá tài khoản.
 * ============================================================
 * Đây là màn hình có hậu quả lớn nhất trong khu quản trị: một cú bấm
 * sai có thể khoá chính cửa hàng ở ngoài hệ thống của mình, và không
 * màn hình nào chữa được — chỉ còn cách sửa thẳng cơ sở dữ liệu.
 *
 * Nên phần lớn các bài ở đây canh những đường đi PHẢI BỊ CHẶN, chứ
 * không canh đường đi thành công.
 */
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

    // ================================================================
    // Đổi vai trò
    // ================================================================

    #[Test]
    public function admin_doi_duoc_vai_tro_cua_nguoi_khac(): void
    {
        // Đường đi đúng phải chạy được TRƯỚC, nếu không thì "chặn được
        // mọi thứ" cũng làm các bài chặn bên dưới xanh.
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
        /*
         * Admin duy nhất tự hạ mình xuống khách hàng là mất quyền vào
         * khu quản trị, và không còn ai mở lại được.
         */
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
        /*
         * Dựng đúng cảnh nguy hiểm: hai admin, nhưng một người đang bị
         * khoá nên không đăng nhập được. Hạ quyền người còn lại là
         * không còn ai vào được khu quản trị.
         *
         * Đây cũng là bài chứng minh vì sao phép đếm phải BỎ QUA admin
         * đang bị khoá: đếm cả họ thì hệ thống tưởng vẫn còn lối vào.
         */
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
        /*
         * Mặt trái của bài trên — và là chỗ phép đếm cũ làm sai.
         *
         * Admin đang bị khoá vốn đã không vào được; hạ quyền họ không
         * làm ai mất quyền truy cập. Chặn ở đây là chặn nhầm, và người
         * dùng sẽ đọc một thông báo nói sai sự thật.
         */
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
        // Chốt quan trọng nhất: không có nó thì mọi phép kiểm ở trên chỉ
        // là trang trí, vì bất kỳ ai cũng tự phong admin được.
        $nanNhan = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch("/admin/users/{$nanNhan->id}/vai-tro", ['role' => 'admin'])
            ->assertForbidden();

        $this->assertSame(UserRole::Customer, $nanNhan->fresh()->role);
    }

    // ================================================================
    // Khoá tài khoản
    // ================================================================

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
        /*
         * Chặn ai đó mà không nói vì sao thì họ chỉ nghĩ là trang web
         * hỏng, và việc tiếp theo họ làm là gọi điện hoặc lập tài khoản
         * mới — cả hai đều tốn công cửa hàng hơn một dòng giải thích.
         */
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
        /*
         * ĐÂY LÀ BÀI QUAN TRỌNG NHẤT trong nhóm khoá.
         *
         * Nếu chỉ chặn ở màn hình đăng nhập thì khoá KHÔNG có tác dụng
         * với đúng người đang cần chặn: họ đã đăng nhập rồi, phiên sống
         * nhiều ngày, và họ cứ thế gửi tiếp đánh giá rác.
         */
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
        // Tự khoá là bị đá ra ở lần bấm kế tiếp và không vào lại được
        // để mở khoá — không màn hình nào chữa được việc đó.
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/khoa", ['khoa' => 1])
            ->assertRedirect();

        $this->assertNull($admin->fresh()->locked_at);
    }

    #[Test]
    public function mo_khoa_thi_xoa_luon_ly_do_cu(): void
    {
        /*
         * Giữ lại lý do cũ thì lần khoá sau — nếu ai đó quên nhập lý do
         * mới — người dùng đọc được lời giải thích của một việc khác.
         */
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
