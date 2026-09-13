<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Hồ sơ một khách hàng trong trang quản trị.
 * ============================================================
 * Trước đây danh sách người dùng chỉ có con số; khách gọi hỏi "đơn của
 * tôi đâu" thì nhân viên phải sang trang Đơn hàng tự lọc.
 */
class HoSoKhachHangTest extends TestCase
{
    use RefreshDatabase;

    private function nguoi(UserRole $vaiTro, string $ten = 'Người thử'): User
    {
        $u = User::factory()->create(['name' => $ten]);
        $u->role = $vaiTro;
        $u->save();

        return $u;
    }

    private function don(User $khach, OrderStatus $trangThai, string $tong): Order
    {
        $don = Order::create([
            'order_number' => 'FP-HS-' . strtoupper(bin2hex(random_bytes(3))),
            'user_id' => $khach->id,
            'recipient_name' => $khach->name,
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => $tong,
            'discount_total' => '0.00',
            'shipping_fee' => '0.00',
            'coupon_discount' => '0.00',
            'grand_total' => $tong,
        ]);

        $don->forceFill(['status' => $trangThai, 'payment_status' => PaymentStatus::Unpaid])->save();

        return $don;
    }

    private function hoSo(User $khach)
    {
        return $this->actingAs($this->nguoi(UserRole::Admin, 'Quản trị'))
            ->get(route('admin.users.show', $khach));
    }

    #[Test]
    public function thay_du_don_cua_khach_va_dan_toi_tung_don(): void
    {
        $khach = $this->nguoi(UserRole::Customer, 'Chị Hoa');
        $d1 = $this->don($khach, OrderStatus::Completed, '300000.00');
        $d2 = $this->don($khach, OrderStatus::Pending, '120000.00');

        $this->hoSo($khach)
            ->assertOk()
            ->assertSee('Chị Hoa')
            ->assertSee(route('admin.orders.show', $d1), false)
            ->assertSee(route('admin.orders.show', $d2), false);
    }

    #[Test]
    public function KHONG_lan_don_cua_khach_khac(): void
    {
        $khach = $this->nguoi(UserRole::Customer, 'Chị Hoa');
        $nguoiKhac = $this->nguoi(UserRole::Customer, 'Anh Nam');
        $donKhac = $this->don($nguoiKhac, OrderStatus::Completed, '999000.00');

        $this->hoSo($khach)
            ->assertOk()
            ->assertDontSee($donKhac->order_number);
    }

    #[Test]
    public function DA_CHI_chi_tinh_don_da_giao_giong_danh_sach(): void
    {
        /*
         * Hai trang hai định nghĩa thì nhân viên đọc hai con số khác
         * nhau cho cùng một khách.
         */
        $khach = $this->nguoi(UserRole::Customer, 'Chị Hoa');
        $this->don($khach, OrderStatus::Completed, '300000.00');
        $this->don($khach, OrderStatus::Completed, '200000.00');
        $this->don($khach, OrderStatus::Cancelled, '700000.00');
        $this->don($khach, OrderStatus::Pending, '50000.00');

        $html = $this->hoSo($khach)->assertOk()->getContent();

        $this->assertStringContainsString('500.000', $html);
        $this->assertStringNotContainsString('1.250.000', $html);
        $this->assertStringContainsString('Trên tổng 4 đơn đã đặt', $html);
    }

    #[Test]
    public function dem_don_huy_rieng(): void
    {
        $khach = $this->nguoi(UserRole::Customer, 'Chị Hoa');
        $this->don($khach, OrderStatus::Cancelled, '100000.00');
        $this->don($khach, OrderStatus::Cancelled, '100000.00');
        $this->don($khach, OrderStatus::Pending, '100000.00');

        $html = $this->hoSo($khach)->assertOk()->getContent();

        /*
         * ĐỌC ĐÚNG Ô "ĐƠN HUỶ", không tìm chữ "2" ở đâu đó sau nhãn.
         *
         * Thử phá code đã chứng minh: đếm nhầm trạng thái mà bài vẫn xanh,
         * vì chữ "2" nào phía sau (ngày tháng, số trang) cũng khớp.
         */
        $this->assertMatchesRegularExpression(
            '#Đơn huỷ</span>\s*<span class="admin-kpi__value">\s*<span[^>]*>2</span>#u',
            $html,
        );
    }

    #[Test]
    public function hien_dia_chi_da_luu(): void
    {
        $khach = $this->nguoi(UserRole::Customer, 'Chị Hoa');

        Address::create([
            'user_id' => $khach->id,
            'recipient_name' => 'Chị Hoa',
            'recipient_phone' => '0909111222',
            'address_line' => '45 Phố Hoa',
            'province' => 'Thành phố Hà Nội',
        ]);

        $this->hoSo($khach)->assertOk()->assertSee('45 Phố Hoa')->assertSee('0909111222');
    }

    #[Test]
    public function khach_chua_dat_gi_thi_noi_ro(): void
    {
        $this->hoSo($this->nguoi(UserRole::Customer, 'Chị Hoa'))
            ->assertOk()
            ->assertSee('Khách này chưa đặt đơn nào.')
            ->assertSee('Chưa lưu địa chỉ nào.');
    }

    #[Test]
    public function danh_sach_nguoi_dung_dan_toi_ho_so(): void
    {
        $khach = $this->nguoi(UserRole::Customer, 'Chị Hoa');

        /*
         * CÓ DẤU NHÁY ĐÓNG. Thử phá code đã chứng minh: bỏ hẳn liên kết mà
         * bài vẫn xanh, vì địa chỉ hồ sơ `/admin/users/5` là một phần của
         * địa chỉ form đổi vai trò `/admin/users/5/vai-tro` ngay cùng dòng.
         */
        $this->actingAs($this->nguoi(UserRole::Admin, 'Quản trị'))
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('href="' . route('admin.users.show', $khach) . '"', false);
    }

    #[Test]
    public function chi_quyen_he_thong_xem_duoc(): void
    {
        // Cùng quyền với danh sách người dùng.
        $khach = $this->nguoi(UserRole::Customer, 'Chị Hoa');

        $this->actingAs($this->nguoi(UserRole::Staff, 'Nhân viên'))
            ->get(route('admin.users.show', $khach))
            ->assertForbidden();
    }
}
