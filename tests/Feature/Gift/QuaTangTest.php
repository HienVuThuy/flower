<?php

namespace Tests\Feature\Gift;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\GiftCampaign;
use App\Models\GiftItem;
use App\Models\MemberTier;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Checkout\CheckoutBasket;
use App\Services\Checkout\CheckoutLine;
use App\Services\Gift\GiftResolver;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Quà tặng: hai nhánh (kèm sản phẩm, chương trình), điều kiện, và quà trong đơn.
 */
class QuaTangTest extends TestCase
{
    use RefreshDatabase;

    private function sp(string $gia = '300000.00', int $ton = 50): Product
    {
        return Product::factory()->for(Category::factory())->price($gia)->stock($ton)->create();
    }

    private function qua(array $ghiDeVat = [], array $ghiDeCt = []): GiftCampaign
    {
        $vat = GiftItem::create(array_merge(['name' => 'Túi vải Angevil', 'kind' => 'qua_tang', 'stock_quantity' => 10, 'is_active' => true, 'value' => '45000'], $ghiDeVat));

        return GiftCampaign::create(array_merge([
            'name' => 'Quà tháng 9', 'kind' => 'chuong_trinh', 'gift_item_id' => $vat->id,
            'gift_quantity' => 1, 'status' => 'active',
        ], $ghiDeCt));
    }

    private function gio(Product $sp, int $sl = 1): CheckoutBasket
    {
        return new CheckoutBasket(collect([new CheckoutLine($sp, null, $sl)]));
    }

    private function nhan(CheckoutBasket $gio, ?User $u): array
    {
        return app(GiftResolver::class)->choGio($gio, $u)->map(fn ($d) => $d['campaign']->id)->all();
    }

    private function donCua(User $u, OrderStatus $tt, string $tien = '100000.00'): Order
    {
        $d = Order::create([
            'order_number' => 'FP-QT-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách', 'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường', 'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod', 'subtotal' => $tien, 'discount_total' => '0.00',
            'shipping_fee' => '0.00', 'coupon_discount' => '0.00', 'grand_total' => $tien,
        ]);
        $d->forceFill(['user_id' => $u->id, 'status' => $tt, 'payment_status' => PaymentStatus::Paid])->save();

        return $d;
    }

    private function vaoThanhToan(Product $sp, int $sl = 1): void
    {
        $this->post('/gio-hang', ['product_id' => $sp->id, 'quantity' => $sl])->assertRedirect();
        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử', 'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com', 'shipping_address' => '12 Đường Thử Nghiệm',
            'shipping_ward' => 'Phường 1', 'shipping_district' => 'Quận 3',
            'shipping_province' => 'Thành phố Hồ Chí Minh', 'payment_method' => 'cod',
            'address_id' => '', 'coupon_code' => '',
        ])->assertRedirect();
    }

    /* ================= ĐIỀU KIỆN ================= */

    #[Test]
    public function kem_san_pham_can_dung_san_pham_va_du_so_luong(): void
    {
        $cay = $this->sp();
        $khac = $this->sp();
        $ct = $this->qua(ghiDeCt: ['kind' => 'kem_san_pham', 'trigger_product_id' => $cay->id, 'trigger_min_quantity' => 2]);

        $this->assertSame([], $this->nhan($this->gio($cay, 1), null));
        $this->assertSame([], $this->nhan($this->gio($khac, 5), null));
        $this->assertSame([$ct->id], $this->nhan($this->gio($cay, 2), null));
    }

    #[Test]
    public function don_toi_thieu_va_hang_thanh_vien(): void
    {
        $sp = $this->sp('300000.00');
        $hoa = MemberTier::where('code', 'hoa')->value('id');
        $theoDon = $this->qua(ghiDeCt: ['min_order_amount' => '500000']);
        $theoHang = $this->qua(['name' => 'Chậu mini'], ['min_member_tier_id' => $hoa]);

        $this->assertSame([], $this->nhan($this->gio($sp, 1), null));
        $this->assertSame([$theoDon->id], $this->nhan($this->gio($sp, 2), null));

        $mam = User::factory()->create();
        $this->assertNotContains($theoHang->id, $this->nhan($this->gio($sp, 1), $mam));

        $khachHoa = User::factory()->create();
        $this->donCua($khachHoa, OrderStatus::Completed, '5000000.00');
        $this->assertContains($theoHang->id, $this->nhan($this->gio($sp, 1), $khachHoa));
    }

    #[Test]
    public function don_dau_tien_va_gioi_han_moi_tai_khoan_can_dang_nhap(): void
    {
        $sp = $this->sp();
        $dauTien = $this->qua(ghiDeCt: ['first_order_only' => true]);

        $this->assertSame([], $this->nhan($this->gio($sp), null), 'Khách vãng lai không kiểm được đơn đầu tiên');

        $moi = User::factory()->create();
        $this->donCua($moi, OrderStatus::Cancelled);
        $this->assertSame([$dauTien->id], $this->nhan($this->gio($sp), $moi), 'Đơn huỷ không tính là đã mua');

        $this->donCua($moi, OrderStatus::Pending);
        $this->assertSame([], $this->nhan($this->gio($sp), $moi));
    }

    #[Test]
    public function het_suat_het_kho_ngung_hoac_ngoai_thoi_gian_thi_khong_tang(): void
    {
        $sp = $this->sp();

        $hetSuat = $this->qua(ghiDeCt: ['total_limit' => 3]);
        $hetSuat->forceFill(['used_count' => 3])->save();
        $this->qua(['stock_quantity' => 0]);
        $this->qua(['is_active' => false]);
        $this->qua(ghiDeCt: ['ends_at' => now()->subMinute()]);
        $this->qua(ghiDeCt: ['status' => 'draft']);

        $this->assertSame([], $this->nhan($this->gio($sp), null));
    }

    /* ================= QUÀ TRONG ĐƠN ================= */

    #[Test]
    public function dat_hang_co_dong_qua_0d_tru_kho_qua_va_giu_suat(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $sp = $this->sp('300000.00');
        $ct = $this->qua(ghiDeCt: ['total_limit' => 5]);

        $this->vaoThanhToan($sp);
        $this->get(route('shop.checkout.details'))->assertSee('data-qua="' . $ct->id . '"', false);

        $this->post('/thanh-toan/dat-hang')->assertRedirect();
        $don = Order::latest('id')->firstOrFail();

        $dongQua = $don->items()->where('is_gift', true)->sole();
        $this->assertSame('0.00', (string) $dongQua->line_total);
        $this->assertSame($ct->id, $dongQua->gift_campaign_id);
        $this->assertSame(bcadd('300000.00', (string) $don->shipping_fee, 2), (string) $don->grand_total, 'Quà không đổi tiền đơn');

        $this->assertSame(9, $ct->giftItem->fresh()->stock_quantity);
        $this->assertSame(1, $ct->fresh()->used_count);

        $this->get(route('shop.orders.show', $don))->assertSee('data-dong-qua="' . $dongQua->id . '"', false);
    }

    #[Test]
    public function qua_la_san_pham_tru_kho_san_pham_huy_don_tra_lai_kho_va_suat(): void
    {
        $this->actingAs(User::factory()->create());
        $sp = $this->sp('300000.00');
        $chau = $this->sp('50000.00', 5);
        $ct = $this->qua(['name' => 'Chậu sứ mini', 'product_id' => $chau->id, 'stock_quantity' => null]);

        $this->vaoThanhToan($sp);
        $this->post('/thanh-toan/dat-hang');
        $don = Order::latest('id')->firstOrFail();

        $this->assertSame(4, $chau->fresh()->stock_quantity);

        app(OrderService::class)->changeStatus($don->fresh(), OrderStatus::Cancelled, 'Khách đổi ý');

        $this->assertSame(5, $chau->fresh()->stock_quantity);
        $this->assertSame(0, $ct->fresh()->used_count);
    }

    #[Test]
    public function huy_don_tra_kho_vat_pham_tang_rieng(): void
    {
        $this->actingAs(User::factory()->create());
        $sp = $this->sp();
        $ct = $this->qua();

        $this->vaoThanhToan($sp);
        $this->post('/thanh-toan/dat-hang');
        $don = Order::latest('id')->firstOrFail();

        app(OrderService::class)->changeStatus($don->fresh(), OrderStatus::Cancelled, 'Khách đổi ý');

        $this->assertSame(10, $ct->giftItem->fresh()->stock_quantity);
    }

    #[Test]
    public function het_suat_giua_chung_thi_don_van_dat_nhung_khong_co_qua(): void
    {
        $this->actingAs(User::factory()->create());
        $sp = $this->sp();
        $ct = $this->qua(ghiDeCt: ['total_limit' => 1]);

        $this->vaoThanhToan($sp);
        // Người khác vừa lấy suất cuối trong lúc khách đang xem lại đơn.
        GiftCampaign::whereKey($ct->id)->update(['used_count' => 1]);

        $this->post('/thanh-toan/dat-hang')->assertRedirect();
        $don = Order::latest('id')->firstOrFail();

        $this->assertSame(0, $don->items()->where('is_gift', true)->count());
        $this->assertSame(1, $ct->fresh()->used_count);
        $this->assertSame(10, $ct->giftItem->fresh()->stock_quantity);
    }

    #[Test]
    public function tranh_suat_cuoi_cung_luc_thi_co_so_du_lieu_phan_xu(): void
    {
        /*
         * ĐUA THẬT: GiftResolver đọc thấy còn suất (bản ghi cũ trong bộ nhớ),
         * nhưng trong cơ sở dữ liệu người khác đã lấy suất cuối. Thử phá code đã
         * chứng minh: bài trên không chạm tới chốt UPDATE có điều kiện của
         * GiftGranter, vì GiftResolver đã loại chương trình hết suất từ trước.
         */
        $this->actingAs(User::factory()->create());
        $sp = $this->sp();
        $ct = $this->qua(ghiDeCt: ['total_limit' => 1]);
        $cu = $ct->fresh(['giftItem']);

        $this->app->instance(GiftResolver::class, new class($cu) extends GiftResolver
        {
            public function __construct(private GiftCampaign $cu)
            {
            }

            public function choGio(CheckoutBasket $basket, ?User $user): \Illuminate\Support\Collection
            {
                return collect([['campaign' => $this->cu, 'quantity' => 1]]);
            }
        });

        $this->vaoThanhToan($sp);
        GiftCampaign::whereKey($ct->id)->update(['used_count' => 1]);

        $this->post('/thanh-toan/dat-hang')->assertRedirect();
        $don = Order::latest('id')->firstOrFail();

        $this->assertSame(0, $don->items()->where('is_gift', true)->count(), 'Không lố suất');
        $this->assertSame(1, $ct->fresh()->used_count);
        $this->assertSame(10, $ct->giftItem->fresh()->stock_quantity, 'Không mất suất thì không trừ kho');
    }

    #[Test]
    public function moi_tai_khoan_nhan_mot_lan_dem_tu_don_that(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $sp = $this->sp();
        $ct = $this->qua(ghiDeCt: ['per_user_limit' => 1]);

        $this->vaoThanhToan($sp);
        $this->post('/thanh-toan/dat-hang');

        $this->assertSame([], $this->nhan($this->gio($sp), $u));
        $this->assertSame([$ct->id], $this->nhan($this->gio($sp), User::factory()->create()));
    }

    #[Test]
    public function trang_san_pham_noi_truoc_qua_kem(): void
    {
        $cay = $this->sp();
        $ct = $this->qua(ghiDeCt: ['kind' => 'kem_san_pham', 'trigger_product_id' => $cay->id, 'total_limit' => 20]);

        $this->get(route('shop.products.show', $cay))
            ->assertSee('data-qua-san-pham="' . $ct->id . '"', false)
            ->assertSee('Còn 20 suất');

        $this->get(route('shop.products.show', $this->sp()))->assertDontSee('data-qua-san-pham', false);
    }

    /* ================= QUẢN TRỊ ================= */

    #[Test]
    public function quan_tri_chan_cau_hinh_vo_ly(): void
    {
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();
        $this->actingAs($admin);

        $this->post(route('admin.gift-items.store'), ['name' => 'Túi', 'kind' => 'qua_tang', 'is_active' => '1'])
            ->assertSessionHasErrors('stock_quantity');

        $vat = GiftItem::create(['name' => 'Túi', 'kind' => 'qua_tang', 'stock_quantity' => 3, 'is_active' => true]);

        $this->post(route('admin.gift-campaigns.store'), [
            'name' => 'Kèm cây', 'kind' => 'kem_san_pham', 'gift_item_id' => $vat->id, 'gift_quantity' => 1,
            'trigger_min_quantity' => 1, 'status' => 'active',
        ])->assertSessionHasErrors('trigger_product_id');

        $ct = GiftCampaign::create(['name' => 'Đã phát', 'kind' => 'chuong_trinh', 'gift_item_id' => $vat->id, 'status' => 'active']);
        $ct->forceFill(['used_count' => 2])->save();

        $this->delete(route('admin.gift-campaigns.destroy', $ct))->assertSessionHas('error');
        $this->assertDatabaseHas('gift_campaigns', ['id' => $ct->id]);
        $this->delete(route('admin.gift-items.destroy', $vat))->assertSessionHas('error');

        $nv = User::factory()->create();
        $nv->role = UserRole::Staff;
        $nv->save();
        $this->actingAs($nv)->get(route('admin.gift-campaigns.index'))->assertForbidden();
    }
}
