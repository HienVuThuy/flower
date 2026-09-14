<?php

namespace Tests\Feature\Catalog;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\SocialProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bằng chứng xã hội, khan hiếm và "sắp mất mã" — chỉ từ dữ liệu thật.
 * ============================================================
 * Bất biến chung: không đủ căn cứ thì KHÔNG in gì. Không có số bịa.
 */
class BangChungMuaHangTest extends TestCase
{
    use RefreshDatabase;

    private function sp(array $ghiDe = []): Product
    {
        return Product::factory()->for(Category::factory())->create(array_merge([
            'status' => 'active', 'track_inventory' => true, 'stock_quantity' => 50,
        ], $ghiDe));
    }

    private function ban(Product $sp, int $sl, OrderStatus $tt = OrderStatus::Completed, int $ngayTruoc = 3, ?int $quyCach = null): void
    {
        $don = Order::create([
            'order_number' => 'FP-BC-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách', 'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường', 'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod', 'subtotal' => '100000.00', 'discount_total' => '0.00',
            'shipping_fee' => '0.00', 'coupon_discount' => '0.00', 'grand_total' => '100000.00',
        ]);
        $don->forceFill(['status' => $tt, 'payment_status' => PaymentStatus::Paid, 'created_at' => now()->subDays($ngayTruoc)])->save();
        $don->items()->create([
            'product_id' => $sp->id, 'product_variant_id' => $quyCach, 'product_name' => $sp->name,
            'quantity' => $sl, 'unit_base_price' => '100000.00', 'unit_price' => '100000.00', 'line_total' => '100000.00',
        ]);
    }

    /* ================= ĐÃ BÁN ================= */

    #[Test]
    public function da_ban_chi_dem_don_DA_GIAO_trong_30_ngay(): void
    {
        $sp = $this->sp();
        $this->ban($sp, 4);
        $this->ban($sp, 2);
        $this->ban($sp, 9, OrderStatus::Cancelled);       // huỷ: không phải đã bán
        $this->ban($sp, 7, OrderStatus::Pending);         // chưa giao
        $this->ban($sp, 5, OrderStatus::Completed, 45);   // quá 30 ngày

        $this->assertSame(6, app(SocialProof::class)->banGanDay($sp));

        $this->get(route('shop.products.show', $sp))
            ->assertOk()
            ->assertSee('Đã bán 6 trong 30 ngày qua');
    }

    #[Test]
    public function chua_ban_duoc_gi_thi_KHONG_in_da_ban_0(): void
    {
        $sp = $this->sp();

        $this->get(route('shop.products.show', $sp))->assertOk()->assertDontSee('Đã bán');
    }

    #[Test]
    public function co_danh_gia_ma_chua_ban_duoc_gi_thi_van_KHONG_in_da_ban_0(): void
    {
        /*
         * CÓ ĐÁNH GIÁ để khối "bằng chứng" được dựng — thử phá code đã chứng
         * minh: bài trên không có đánh giá nên cả khối không hiện, và in
         * "Đã bán 0" bên trong vẫn không lộ ra. Đơn gắn với đánh giá là đơn
         * ĐÃ HUỶ, nên số đã bán vẫn là 0.
         */
        $sp = $this->sp();
        $this->ban($sp, 2, OrderStatus::Cancelled);

        \App\Models\Review::create([
            'product_id' => $sp->id,
            'user_id' => User::factory()->create()->id,
            'order_id' => Order::latest('id')->value('id'),
            'rating' => 5,
            'comment' => 'Cây đẹp',
        ]);

        $this->get(route('shop.products.show', $sp))
            ->assertOk()
            ->assertSee('product-info__proof', false)
            ->assertDontSee('Đã bán');
    }

    /* ================= CHỈ CÒN ================= */

    #[Test]
    public function con_tu_5_tro_xuong_thi_noi_chi_con(): void
    {
        $this->get(route('shop.products.show', $this->sp(['stock_quantity' => 3])))
            ->assertOk()->assertSee('Chỉ còn 3 sản phẩm');
    }

    #[Test]
    public function con_nhieu_hoac_lam_theo_don_thi_KHONG_noi_chi_con(): void
    {
        $this->get(route('shop.products.show', $this->sp(['stock_quantity' => 6])))
            ->assertOk()->assertDontSee('Chỉ còn');

        $lamTheoDon = $this->sp(['stock_quantity' => 2, 'track_inventory' => false]);

        $this->get(route('shop.products.show', $lamTheoDon))
            ->assertOk()->assertDontSee('Chỉ còn');

        /*
         * KIỂM THẲNG DỊCH VỤ. Trang không dựng khối tồn kho cho hàng làm theo
         * đơn, nên kiểm qua trang không phân biệt được — thử phá code đã chứng
         * minh: bỏ điều kiện track_inventory mà bài vẫn xanh.
         */
        $this->assertNull(app(SocialProof::class)->chiCon($lamTheoDon, false));
    }

    #[Test]
    public function co_quy_cach_thi_KHONG_gop_ton_de_noi_chi_con(): void
    {
        // Tồn nằm ở từng quy cách — nói "chỉ còn 2" theo tồn chung là sai với quy cách khách chọn.
        $sp = $this->sp(['stock_quantity' => 2]);
        $this->assertNull(app(SocialProof::class)->chiCon($sp, true));
    }

    /* ================= QUY CÁCH PHỔ BIẾN ================= */

    #[Test]
    public function quy_cach_ban_chay_can_du_so_lieu_va_khong_hoa(): void
    {
        $sp = $this->sp();
        $nho = $sp->variants()->create(['name' => 'Chậu nhỏ', 'price' => '100000', 'is_active' => true, 'stock_quantity' => 10]);
        $lon = $sp->variants()->create(['name' => 'Chậu lớn', 'price' => '200000', 'is_active' => true, 'stock_quantity' => 10]);
        $dv = app(SocialProof::class);

        $this->ban($sp, 2, quyCach: $nho->id);
        $this->assertNull($dv->quyCachBanChay($sp), 'Dưới 3 cái chưa gọi là phổ biến');

        /*
         * HOÀ Ở TRÊN NGƯỠNG. Thử phá code đã chứng minh: bản đầu của bài này
         * hoà 2–2, dưới ngưỡng 3 — luật ngưỡng đã trả null trước khi tới luật
         * hoà, nên bỏ hẳn luật hoà mà bài vẫn xanh.
         */
        $this->ban($sp, 1, quyCach: $nho->id);
        $this->ban($sp, 3, quyCach: $lon->id);
        $this->assertNull($dv->quyCachBanChay($sp), 'Hoà 3–3 thì không có quán quân');

        $this->ban($sp, 1, quyCach: $lon->id);
        $this->assertSame($lon->id, $dv->quyCachBanChay($sp));
    }

    #[Test]
    public function trang_chon_san_quy_cach_pho_bien_va_gan_nhan(): void
    {
        $sp = $this->sp();
        $nho = $sp->variants()->create(['name' => 'Chậu nhỏ', 'price' => '100000', 'is_active' => true, 'stock_quantity' => 10, 'sort_order' => 1]);
        $lon = $sp->variants()->create(['name' => 'Chậu lớn', 'price' => '200000', 'is_active' => true, 'stock_quantity' => 10, 'sort_order' => 2]);
        $this->ban($sp, 4, quyCach: $lon->id);

        $html = $this->get(route('shop.products.show', $sp))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#value="' . $lon->id . '"[^>]*checked#s', $html, 'Chọn sẵn quy cách bán chạy');
        $this->assertDoesNotMatchRegularExpression('#value="' . $nho->id . '"[^>]*checked#s', $html);
        $this->assertSame(1, substr_count($html, 'Phổ biến nhất'));
    }

    #[Test]
    public function chua_du_so_lieu_thi_chon_o_dau_va_KHONG_gan_nhan(): void
    {
        $sp = $this->sp();
        $nho = $sp->variants()->create(['name' => 'Chậu nhỏ', 'price' => '100000', 'is_active' => true, 'stock_quantity' => 10, 'sort_order' => 1]);
        $sp->variants()->create(['name' => 'Chậu lớn', 'price' => '200000', 'is_active' => true, 'stock_quantity' => 10, 'sort_order' => 2]);

        $html = $this->get(route('shop.products.show', $sp))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#value="' . $nho->id . '"[^>]*checked#s', $html);
        $this->assertStringNotContainsString('Phổ biến nhất', $html);
    }

    /* ================= SẮP MẤT MÃ ================= */

    private function luuMa(User $u, Coupon $ma): void
    {
        DB::table('coupon_user')->insert([
            'user_id' => $u->id, 'coupon_id' => $ma->id, 'claimed_at' => now(),
            'used_count' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[Test]
    public function ma_sap_het_han_len_dau_vi_va_co_dong_nhac(): void
    {
        $u = User::factory()->create();
        $xa = Coupon::factory()->create(['code' => 'CONHAN30', 'name' => 'Còn hạn dài', 'ends_at' => now()->addDays(30), 'starts_at' => now()->subDay(), 'is_public' => true]);
        $gan = Coupon::factory()->create(['code' => 'SAPHET', 'name' => 'Sắp hết', 'ends_at' => now()->addHours(10), 'starts_at' => now()->subDay(), 'is_public' => true]);

        $this->luuMa($u, $gan);  // lưu trước
        $this->luuMa($u, $xa);   // lưu sau — thứ tự cũ sẽ để mã này lên đầu

        $html = $this->actingAs($u)->get(route('shop.vouchers.index'))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'CONHAN30'), strpos($html, 'SAPHET'), 'Mã sắp hết hạn phải đứng trước');
        $this->assertStringContainsString('Hết hạn sau 9 giờ — dùng trước khi mất mã', $html);
        $this->assertSame(1, substr_count($html, 'dùng trước khi mất mã'), 'Mã còn hạn 30 ngày không có dòng nhắc');
    }
}
