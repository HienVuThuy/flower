<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Admin thêm / sửa / xoá người dùng (lab08). */
class QuanLyNguoiDungTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    #[Test]
    public function tao_nguoi_dung_moi_voi_mat_khau_manh(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->assertSee('Tạo tài khoản');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Nhân viên Lan', 'email' => 'Lan@AngEvil.test',
                'password' => 'yeu', 'password_confirmation' => 'yeu', 'role' => 'staff',
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Nhân viên Lan', 'email' => 'Lan@AngEvil.test',
                'password' => 'MatKhau123', 'password_confirmation' => 'MatKhau123', 'role' => 'staff',
            ])
            ->assertRedirect();

        $moi = User::where('email', 'lan@angevil.test')->firstOrFail();
        $this->assertSame(UserRole::Staff, $moi->role);
        $this->assertTrue(Hash::check('MatKhau123', $moi->password));
        $this->assertNotNull($moi->email_verified_at);
    }

    #[Test]
    public function email_trung_bi_tu_choi(): void
    {
        $cu = User::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), [
                'name' => 'X', 'email' => $cu->email,
                'password' => 'MatKhau123', 'password_confirmation' => 'MatKhau123', 'role' => 'customer',
            ])
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function sua_ten_email_vai_tro_doi_email_thi_phai_xac_thuc_lai(): void
    {
        $khach = User::factory()->create(['role' => 'customer', 'name' => 'Cũ']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.users.edit', $khach))->assertOk()->assertSee('Cập nhật');

        $this->actingAs($admin)
            ->put(route('admin.users.update', $khach), ['name' => 'Mới', 'email' => 'moi@khach.test', 'role' => 'staff'])
            ->assertRedirect(route('admin.users.show', $khach));

        $khach->refresh();
        $this->assertSame('Mới', $khach->name);
        $this->assertSame(UserRole::Staff, $khach->role);
        $this->assertNull($khach->email_verified_at);
    }

    #[Test]
    public function khong_tu_ha_quyen_admin_cuoi_cung_qua_trang_sua(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), ['name' => 'A', 'email' => $admin->email, 'role' => 'customer'])
            ->assertSessionHas('error');

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    #[Test]
    public function xoa_tai_khoan_giu_don_cu(): void
    {
        $khach = User::factory()->create(['role' => 'customer']);
        $don = Order::create([
            'order_number' => 'X-1', 'user_id' => $khach->id, 'recipient_name' => 'K', 'recipient_phone' => '0912345678',
            'shipping_address' => '1', 'shipping_province' => 'Thành phố Hà Nội', 'payment_method' => 'cod',
            'subtotal' => '1', 'discount_total' => '0', 'shipping_fee' => '0', 'coupon_discount' => '0', 'grand_total' => '1',
        ]);
        $don->forceFill(['status' => OrderStatus::Completed->value])->save();

        $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $khach))
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull(User::find($khach->id));
        $this->assertNotNull(Order::find($don->id));
    }

    #[Test]
    public function khong_xoa_duoc_khi_con_don_do_dang_hoac_tu_xoa_minh(): void
    {
        $admin = $this->admin();
        $khach = User::factory()->create(['role' => 'customer']);
        Order::create([
            'order_number' => 'X-2', 'user_id' => $khach->id, 'recipient_name' => 'K', 'recipient_phone' => '0912345678',
            'shipping_address' => '1', 'shipping_province' => 'Thành phố Hà Nội', 'payment_method' => 'cod',
            'subtotal' => '1', 'discount_total' => '0', 'shipping_fee' => '0', 'coupon_discount' => '0', 'grand_total' => '1',
        ]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $khach))->assertSessionHas('error');
        $this->assertNotNull(User::find($khach->id));

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHas('error');
        $this->assertNotNull(User::find($admin->id));
    }

    #[Test]
    public function nhan_vien_khong_quan_ly_duoc_nguoi_dung(): void
    {
        $nhanVien = User::factory()->create(['role' => 'staff']);
        $khach = User::factory()->create(['role' => 'customer']);

        $this->actingAs($nhanVien)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($nhanVien)->delete(route('admin.users.destroy', $khach))->assertForbidden();
        $this->assertNotNull(User::find($khach->id));
    }
}
