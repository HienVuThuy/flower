<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Services\Coupon\CouponException;
use App\Services\Coupon\CouponService;
use App\Services\Order\OrderException;
use App\Services\Order\OrderService;
use App\Services\Shipping\GHNOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Các lỗ hổng nghiệp vụ được chỉ ra ở đợt rà soát ngoài. */
class LoHongNghiepVuTest extends TestCase
{
    use RefreshDatabase;

    private function nguoi(UserRole $vaiTro): User
    {
        $u = User::factory()->create();
        $u->role = $vaiTro;
        $u->save();

        return $u;
    }

    private function don(OrderStatus $trangThai, array $them = []): Order
    {
        $don = Order::create(array_merge([
            'order_number' => 'FP-LH-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '0.00',
            'coupon_discount' => '0.00',
            'grand_total' => '300000.00',
        ], $them));

        $don->forceFill(['status' => $trangThai, 'payment_status' => PaymentStatus::Unpaid] + array_intersect_key($them, array_flip(['ghn_order_code', 'shipping_status', 'to_district_id', 'to_ward_code'])))->save();

        return $don->fresh();
    }

    #[Test]
    public function nhan_vien_KHONG_danh_dau_duoc_da_thanh_toan(): void
    {
        $don = $this->don(OrderStatus::Confirmed);

        $this->actingAs($this->nguoi(UserRole::Staff))
            ->patch(route('admin.orders.payment', $don), ['payment_status' => PaymentStatus::Paid->value])
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Unpaid, $don->fresh()->payment_status);
    }

    #[Test]
    public function nhan_vien_KHONG_ghi_duoc_hoan_tien(): void
    {
        $don = $this->don(OrderStatus::Cancelled);
        $don->forceFill(['payment_status' => PaymentStatus::Paid])->save();

        $this->actingAs($this->nguoi(UserRole::Staff))
            ->post(route('admin.orders.refunds.store', $don), [
                'amount' => 300000, 'method' => 'tien_mat', 'reason' => 'order_cancelled',
            ])
            ->assertForbidden();

        $this->assertSame(0, $don->refunds()->count());
    }

    #[Test]
    public function nhan_vien_xem_don_KHONG_thay_nut_thanh_toan_va_form_hoan_tien(): void
    {
        $don = $this->don(OrderStatus::Confirmed);

        $this->actingAs($this->nguoi(UserRole::Staff))
            ->get(route('admin.orders.show', $don))
            ->assertOk()
            ->assertDontSee('action="' . route('admin.orders.payment', $don) . '"', false)
            ->assertSee('Ghi nhận thanh toán thuộc quyền tài chính.');
    }

    #[Test]
    public function quan_tri_van_danh_dau_duoc_da_thanh_toan(): void
    {
        $don = $this->don(OrderStatus::Confirmed);

        $this->actingAs($this->nguoi(UserRole::Admin))
            ->patch(route('admin.orders.payment', $don), ['payment_status' => PaymentStatus::Paid->value])
            ->assertRedirect();

        $this->assertSame(PaymentStatus::Paid, $don->fresh()->payment_status);
    }

    #[Test]
    public function KHONG_huy_duoc_don_con_van_don_GHN(): void
    {
        $don = $this->don(OrderStatus::Shipping, ['ghn_order_code' => 'GHN123', 'shipping_status' => 'delivering']);

        try {
            app(OrderService::class)->changeStatus($don, OrderStatus::Cancelled, 'khách đổi ý');
            $this->fail('Phải chặn huỷ đơn khi vận đơn GHN còn hiệu lực.');
        } catch (OrderException $e) {
            $this->assertStringContainsString('không huỷ được', $e->getMessage());
        }

        $this->assertSame(OrderStatus::Shipping, $don->fresh()->status);
    }

    #[Test]
    public function da_huy_van_don_GHN_thi_ghi_nhan_hoan_hang_duoc(): void
    {
        $don = $this->don(OrderStatus::Shipping, ['ghn_order_code' => 'GHN123', 'shipping_status' => 'cancel']);

        app(OrderService::class)->changeStatus($don, OrderStatus::Cancelled, 'khách đổi ý', hoanHang: true);

        $this->assertSame(OrderStatus::Cancelled, $don->fresh()->status);
    }

    #[Test]
    public function don_dang_giao_KHONG_huy_duoc_chi_ghi_nhan_hoan_hang(): void
    {
        $don = $this->don(OrderStatus::Shipping);

        try {
            app(OrderService::class)->changeStatus($don, OrderStatus::Cancelled, 'khách đổi ý');
            $this->fail('Đơn đang giao không được huỷ.');
        } catch (OrderException $e) {
            $this->assertStringContainsString('Đơn đang giao — không huỷ được', $e->getMessage());
        }

        $this->assertSame(OrderStatus::Shipping, $don->fresh()->status);

        app(OrderService::class)->changeStatus($don->fresh(), OrderStatus::Cancelled, 'khách từ chối nhận', hoanHang: true);

        $this->assertSame(OrderStatus::Cancelled, $don->fresh()->status);
    }

    #[Test]
    public function hoan_hang_qua_GHN_phai_cho_hang_ve_cua_hang(): void
    {
        $don = $this->don(OrderStatus::Shipping, ['ghn_order_code' => 'GHN9', 'shipping_status' => 'delivery_fail']);

        try {
            app(OrderService::class)->changeStatus($don, OrderStatus::Cancelled, 'giao hỏng', hoanHang: true);
            $this->fail('Chưa hoàn về thì chưa ghi nhận hoàn hàng.');
        } catch (OrderException $e) {
            $this->assertStringContainsString('chưa hoàn về cửa hàng', $e->getMessage());
        }

        $don->forceFill(['shipping_status' => 'returned'])->save();
        app(OrderService::class)->changeStatus($don->fresh(), OrderStatus::Cancelled, 'giao hỏng', hoanHang: true);

        $this->assertSame(OrderStatus::Cancelled, $don->fresh()->status);
    }

    #[Test]
    public function KHONG_tao_van_don_cho_don_da_huy_da_giao_hay_chua_xac_nhan(): void
    {
        Http::fake();

        foreach ([OrderStatus::Pending, OrderStatus::Cancelled, OrderStatus::Completed, OrderStatus::Shipping] as $tt) {
            $don = $this->don($tt, ['to_district_id' => 1442, 'to_ward_code' => '20109']);

            $kq = app(GHNOrderService::class)->create($don);

            $this->assertSame(-1, $kq['code'], 'Đơn "' . $tt->label() . '" không được tạo vận đơn.');
            $this->assertNull($don->fresh()->ghn_order_code);
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function da_dung_het_luot_rieng_thi_redeem_bi_tu_choi_va_luot_chung_cuon_lai(): void
    {
        $khach = $this->nguoi(UserRole::Customer);
        $ma = Coupon::factory()->create(['per_user_limit' => 1, 'usage_limit' => null, 'used_count' => 0]);

        DB::table('coupon_user')->insert([
            'user_id' => $khach->id, 'coupon_id' => $ma->id, 'claimed_at' => now(),
            'used_count' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $don = $this->don(OrderStatus::Pending, ['user_id' => $khach->id]);

        try {
            app(CouponService::class)->redeem($ma, $don);
            $this->fail('Phải từ chối khi tài khoản đã dùng hết lượt.');
        } catch (CouponException) {
        }

        $this->assertSame(1, (int) DB::table('coupon_user')->where('user_id', $khach->id)->value('used_count'));
        $this->assertSame(0, (int) $ma->fresh()->used_count, 'Lượt dùng chung phải cuộn lại cùng transaction');
    }

    #[Test]
    public function con_luot_thi_van_dung_duoc_ke_ca_ma_nhap_tay_chua_luu_vi(): void
    {
        $khach = $this->nguoi(UserRole::Customer);
        $ma = Coupon::factory()->create(['per_user_limit' => 2, 'usage_limit' => null, 'used_count' => 0]);
        $don = $this->don(OrderStatus::Pending, ['user_id' => $khach->id]);

        app(CouponService::class)->redeem($ma, $don);

        $this->assertSame(1, (int) DB::table('coupon_user')->where('user_id', $khach->id)->value('used_count'));
        $this->assertSame(1, (int) $ma->fresh()->used_count);
    }

    #[Test]
    public function trang_sua_giu_danh_muc_dang_an_va_nhom_thue_da_tat_cua_chinh_san_pham(): void
    {
        $dm = Category::factory()->create(['name' => 'Chậu cảnh cũ', 'is_active' => false]);
        $nhom = \App\Models\TaxClass::create(['code' => 'vat_10', 'name' => 'VAT 10% thử', 'rate' => '0.10000', 'is_active' => false]);
        $sp = Product::factory()->for($dm)->create(['tax_class_id' => $nhom->id]);

        $html = $this->actingAs($this->nguoi(UserRole::Admin))
            ->get(route('admin.products.edit', $sp))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#value="' . $dm->id . '"\s+selected#', $html);
        $this->assertStringContainsString('Chậu cảnh cũ (đang ẩn)', $html);
        $this->assertStringContainsString('VAT 10% thử (đã tắt', $html);
    }

    #[Test]
    public function san_pham_moi_KHONG_gan_duoc_danh_muc_dang_an(): void
    {
        $dm = Category::factory()->create(['is_active' => false]);

        $this->actingAs($this->nguoi(UserRole::Admin))
            ->post('/admin/products', [
                'category_id' => $dm->id, 'name' => 'Cây thử', 'slug' => 'cay-thu', 'product_code' => 'KT-1',
                'product_type' => 'plant', 'selling_form' => 'pot', 'base_price' => '100000', 'status' => 'active',
            ])
            ->assertSessionHasErrors('category_id');
    }
}
