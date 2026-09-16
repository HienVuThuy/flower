<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** In phiếu soạn hàng + phiếu giao hàng cho một đơn. */
class InPhieuDonTest extends TestCase
{
    use RefreshDatabase;

    private function nguoi(UserRole $vaiTro): User
    {
        $u = User::factory()->create();
        $u->role = $vaiTro;
        $u->save();

        return $u;
    }

    private function don(PaymentStatus $thanhToan = PaymentStatus::Unpaid, array $them = []): Order
    {
        $don = Order::create(array_merge([
            'order_number' => 'FP-IN-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Chị Mai',
            'recipient_phone' => '0987654321',
            'shipping_address' => '12 Hàng Hoa',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '25000.00',
            'coupon_discount' => '0.00',
            'grand_total' => '325000.00',
            'delivery_note' => 'Giao trước 9 giờ, gọi trước khi tới',
        ], $them));

        $don->forceFill(['status' => OrderStatus::Confirmed, 'payment_status' => $thanhToan])->save();

        $don->items()->create([
            'product_name' => 'Bó hồng đỏ',
            'variant_name' => '20 bông',
            'quantity' => 2,
            'unit_base_price' => '150000.00',
            'unit_price' => '150000.00',
            'line_total' => '300000.00',
        ]);

        return $don->fresh('items');
    }

    private function trangIn(Order $don, ?User $ai = null)
    {
        return $this->actingAs($ai ?? $this->nguoi(UserRole::Admin))
            ->get(route('admin.orders.print', $don));
    }

    private function to(string $html, string $nhan): string
    {
        $dau = strpos($html, 'aria-label="' . $nhan . '"');
        $this->assertNotFalse($dau, 'Không thấy tờ ' . $nhan);

        return substr($html, $dau, strpos($html, '</section>', $dau) - $dau);
    }

    #[Test]
    public function in_du_hai_to_voi_thong_tin_can_de_lam_viec(): void
    {
        $don = $this->don();

        $html = $this->trangIn($don)->assertOk()->getContent();

        $soan = $this->to($html, 'Phiếu soạn hàng');
        $giao = $this->to($html, 'Phiếu giao hàng');

        $this->assertStringContainsString($don->order_number, $soan);
        $this->assertStringContainsString('Bó hồng đỏ', $soan);
        $this->assertStringContainsString('Giao trước 9 giờ', $soan);

        $this->assertStringContainsString('Chị Mai', $giao);
        $this->assertStringContainsString('0987654321', $giao);
        $this->assertStringContainsString('12 Hàng Hoa', $giao);
    }

    #[Test]
    public function chua_thanh_toan_thi_phieu_giao_ghi_DUNG_so_tien_phai_thu(): void
    {
        $html = $this->trangIn($this->don())->getContent();

        $this->assertStringContainsString('THU CỦA KHÁCH: 325.000', $this->to($html, 'Phiếu giao hàng'));
    }

    #[Test]
    public function DA_THANH_TOAN_thi_phieu_giao_KHONG_thu_tien(): void
    {
        $html = $this->trangIn($this->don(PaymentStatus::Paid))->getContent();
        $giao = $this->to($html, 'Phiếu giao hàng');

        $this->assertStringContainsString('KHÔNG THU TIỀN', $giao);
        $this->assertStringNotContainsString('THU CỦA KHÁCH', $giao);
    }

    #[Test]
    public function phieu_soan_hang_KHONG_co_gia(): void
    {
        $html = $this->trangIn($this->don())->getContent();
        $soan = $this->to($html, 'Phiếu soạn hàng');

        $this->assertStringNotContainsString('₫', $soan);
        $this->assertStringNotContainsString('150.000', $soan);
        $this->assertStringNotContainsString('300.000', $soan);
        $this->assertStringNotContainsString('325.000', $soan);
    }

    #[Test]
    public function trang_in_khong_mang_khung_quan_tri(): void
    {
        $this->trangIn($this->don())
            ->assertOk()
            ->assertDontSee('admin-sidebar', false)
            ->assertSee('window.print()', false);
    }

    #[Test]
    public function trang_chi_tiet_don_co_nut_in(): void
    {
        $don = $this->don();

        $this->actingAs($this->nguoi(UserRole::Admin))
            ->get(route('admin.orders.show', $don))
            ->assertOk()
            ->assertSee(route('admin.orders.print', $don), false);
    }

    #[Test]
    public function nhan_vien_don_hang_in_duoc_khach_thi_khong(): void
    {
        $don = $this->don();

        $this->trangIn($don, $this->nguoi(UserRole::Staff))->assertOk();
        $this->trangIn($don, $this->nguoi(UserRole::Customer))->assertForbidden();
    }
}
