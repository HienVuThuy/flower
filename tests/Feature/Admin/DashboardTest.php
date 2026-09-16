<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Shop\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Số liệu kinh doanh ở trang Tổng quan. */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function order(OrderStatus $status, string $tien, int $ngayTruoc = 1): Order
    {
        $order = Order::create([
            'order_number' => 'FP-DB-'.strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => $tien,
            'discount_total' => '0.00',
            'shipping_fee' => '0.00',
            'coupon_discount' => '0.00',
            'grand_total' => $tien,
        ]);

        $order->forceFill([
            'status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'created_at' => now()->subDays($ngayTruoc),
        ])->save();

        return $order;
    }

    private function xem(string $url)
    {
        return $this->actingAs($this->admin())->get($url);
    }

    #[Test]
    public function tong_quan_va_phan_tich_noi_cung_mot_con_so_doanh_thu(): void
    {
        $this->order(OrderStatus::Completed, '1200000.00', ngayTruoc: 2);
        $this->order(OrderStatus::Completed, '800000.00', ngayTruoc: 3);

        $this->order(OrderStatus::Pending, '5000000.00', ngayTruoc: 1);
        $this->order(OrderStatus::Cancelled, '9000000.00', ngayTruoc: 1);

        $mong = Money::format('2000000');

        $this->xem('/admin/dashboard?ky=30')->assertOk()->assertSee($mong);
        $this->xem('/admin/phan-tich?ky=30')->assertOk()->assertSee($mong);
    }

    #[Test]
    public function doanh_thu_chi_tinh_don_da_giao(): void
    {
        $this->order(OrderStatus::Shipping, '700000.00');
        $this->order(OrderStatus::Completed, '300000.00');

        $this->xem('/admin/dashboard?ky=30')
            ->assertOk()
            ->assertSee(Money::format('300000'))
            ->assertDontSee(Money::format('1000000'));
    }

    #[Test]
    public function doi_ky_thi_doi_cua_so_thoi_gian(): void
    {
        $this->order(OrderStatus::Completed, '450000.00', ngayTruoc: 20);
        $this->order(OrderStatus::Completed, '450000.00', ngayTruoc: 21);

        $this->xem('/admin/dashboard?ky=30')->assertSee(Money::format('900000'));
        $this->xem('/admin/dashboard?ky=7')->assertDontSee(Money::format('900000'));
    }

    #[Test]
    public function nut_ky_dang_chon_duoc_to_dam_o_moi_trang_co_o_chon_ky(): void
    {
        $trang = [
            '/admin/dashboard?ky=7' => '7 ngày qua',
            '/admin/phan-tich?ky=30' => '30 ngày qua',
            '/admin/ton-kho?ky=90' => '90 ngày qua',
        ];

        foreach ($trang as $url => $nhan) {
            $html = $this->xem($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression(
                '#class="btn btn-sm btn-primary-brand">\s*' . preg_quote($nhan, '#') . '\s*</a>#u',
                $html,
                "Trang {$url} không tô đậm nút \"{$nhan}\".",
            );
        }
    }

    #[Test]
    public function form_xuat_du_lieu_tich_san_dung_ky_dang_xem(): void
    {
        $html = $this->xem('/admin/phan-tich/xuat?ky=7')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#value="7"\s+checked#', $html);
    }

    #[Test]
    public function moc_ky_truoc_in_theo_kieu_viet_va_co_don_vi_tien(): void
    {
        $this->order(OrderStatus::Completed, '1000000.00', ngayTruoc: 2);
        $this->order(OrderStatus::Completed, '13810000.00', ngayTruoc: 40);

        $this->xem('/admin/dashboard?ky=30')
            ->assertOk()
            ->assertSee('so với ' . Money::format('13810000') . ' kỳ trước')
            ->assertDontSee('13,810,000');
    }

    #[Test]
    public function moc_ky_truoc_cua_so_dem_cung_in_kieu_viet(): void
    {
        $this->blade('<x-admin.trend :now="2400" :before="1500" />')
            ->assertSee('so với 1.500 kỳ trước')
            ->assertSee('60,0%');
    }

    #[Test]
    public function tham_so_ky_la_thi_lui_ve_mac_dinh_chu_khong_no(): void
    {
        $this->xem('/admin/dashboard?ky=khong-ton-tai')
            ->assertOk()
            ->assertSee('30 ngày qua');
    }

    #[Test]
    public function ky_toan_bo_KHONG_hien_phan_so_sanh(): void
    {
        $this->order(OrderStatus::Completed, '150000.00', ngayTruoc: 400);

        $this->xem('/admin/dashboard?ky=all')
            ->assertOk()
            ->assertSee(Money::format('150000'))
            ->assertDontSee('so với');
    }

    #[Test]
    public function chua_co_don_da_giao_thi_noi_chua_tinh_duoc_chu_khong_in_0d(): void
    {
        $this->order(OrderStatus::Pending, '400000.00');

        $this->xem('/admin/dashboard?ky=30')
            ->assertOk()
            ->assertSee('chưa tính được');
    }

    #[Test]
    public function moi_lien_ket_tren_trang_deu_dung_dieu_huong_khong_tai_lai(): void
    {
        $html = $this->xem('/admin/dashboard')->assertOk()->getContent();

        preg_match_all('#<a\s([^>]*href="[^"]*/admin/[^"]*"[^>]*)>#', $html, $khop);

        $thieu = array_values(array_filter(
            $khop[1],
            fn (string $the) => ! str_contains($the, 'data-admin-link'),
        ));

        $this->assertSame([], $thieu, 'Còn liên kết admin chưa gắn data-admin-link.');
    }

    #[Test]
    public function khong_con_bon_the_dem_danh_muc_san_pham_khach_hang(): void
    {
        $this->xem('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('stat-card', escape: false);
    }

    #[Test]
    public function trang_noi_ro_so_lieu_tinh_luc_may_gio(): void
    {
        $html = $this->xem('/admin/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Số liệu lúc', $html);
        $this->assertStringContainsString('Làm mới', $html);

        $this->assertStringContainsString('+07:00', $html);
    }
}
