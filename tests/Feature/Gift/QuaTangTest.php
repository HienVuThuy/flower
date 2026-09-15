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
use App\Models\ProductGift;
use App\Models\User;
use App\Services\Checkout\CheckoutBasket;
use App\Services\Checkout\CheckoutLine;
use App\Services\Gift\GiftResolver;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Quà tặng: quà mặc định kèm sản phẩm, quà theo chương trình (tab Khuyến mại), và quà trong đơn.
 */
class QuaTangTest extends TestCase
{
    use RefreshDatabase;

    private function sp(string $gia = '300000.00', int $ton = 50): Product
    {
        return Product::factory()->for(Category::factory())->price($gia)->stock($ton)->create();
    }

    private function vat(array $ghiDe = []): GiftItem
    {
        return GiftItem::create(array_merge(['name' => 'Túi phân bón nhỏ', 'kind' => 'qua_tang', 'stock_quantity' => 10, 'is_active' => true, 'value' => '15000'], $ghiDe));
    }

    private function quaKem(Product $sp, ?GiftItem $vat = null, int $moi = 1, int $tang = 1): ProductGift
    {
        $pg = new ProductGift(['per_quantity' => $moi, 'gift_quantity' => $tang, 'is_active' => true]);
        $pg->product_id = $sp->id;
        $pg->gift_item_id = ($vat ?? $this->vat())->id;
        $pg->save();

        return $pg;
    }

    private function chuongTrinh(array $ghiDeCt = [], array $ghiDeVat = []): GiftCampaign
    {
        return GiftCampaign::create(array_merge([
            'name' => 'Quà Trung thu', 'kind' => 'chuong_trinh', 'gift_item_id' => $this->vat($ghiDeVat)->id,
            'gift_quantity' => 1, 'status' => 'active',
        ], $ghiDeCt));
    }

    private function gio(Product $sp, int $sl = 1): CheckoutBasket
    {
        return new CheckoutBasket(collect([new CheckoutLine($sp, null, $sl)]));
    }

    /** @return list<string> "nguon-id:soLuong" */
    private function nhan(CheckoutBasket $gio, ?User $u): array
    {
        return app(GiftResolver::class)->choGio($gio, $u)
            ->map(fn ($d) => $d['nguon'] . '-' . ($d['campaign']?->id ?? $d['product_gift']->id) . ':' . $d['quantity'])
            ->all();
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

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    /* ================= QUÀ KÈM SẢN PHẨM ================= */

    #[Test]
    public function qua_kem_theo_so_luong_mua_va_chi_san_pham_do(): void
    {
        $senDa = $this->sp();
        $khac = $this->sp();
        $pg = $this->quaKem($senDa, moi: 2, tang: 1);

        $this->assertSame([], $this->nhan($this->gio($senDa, 1), null), 'Mua 1 chưa đủ "mỗi 2"');
        $this->assertSame([], $this->nhan($this->gio($khac, 4), null));
        $this->assertSame(['san_pham-' . $pg->id . ':2'], $this->nhan($this->gio($senDa, 5), null), 'Mua 5, mỗi 2 tặng 1 → 2 quà');
    }

    #[Test]
    public function qua_con_it_hon_so_duoc_tang_thi_tang_phan_con_lai_het_thi_thoi(): void
    {
        $senDa = $this->sp();
        $vat = $this->vat(['stock_quantity' => 2]);
        $pg = $this->quaKem($senDa, $vat);

        $this->assertSame(['san_pham-' . $pg->id . ':2'], $this->nhan($this->gio($senDa, 5), null));

        $vat->update(['stock_quantity' => 0]);
        $this->assertSame([], $this->nhan($this->gio($senDa, 5), null));

        $vat->update(['stock_quantity' => 5, 'is_active' => false]);
        $this->assertSame([], $this->nhan($this->gio($senDa, 5), null));

        $vat->update(['is_active' => true]);
        $pg->update(['is_active' => false]);
        $this->assertSame([], $this->nhan($this->gio($senDa, 5), null));
    }

    #[Test]
    public function dat_hang_qua_nam_duoi_mon_hang_0d_tru_kho_huy_don_tra_kho(): void
    {
        $this->actingAs(User::factory()->create());
        $senDa = $this->sp('300000.00');
        $chau = $this->sp('50000.00', 5);
        $vatSanPham = GiftItem::create(['name' => 'Chậu sứ mini', 'kind' => 'do_vat', 'product_id' => $chau->id, 'is_active' => true]);
        $this->quaKem($senDa, $vatSanPham);
        $tui = $this->quaKem($senDa);

        $this->vaoThanhToan($senDa, 2);
        $this->get(route('shop.checkout.details'))->assertSee('data-qua="san_pham-' . $tui->id . '"', false);

        $this->post('/thanh-toan/dat-hang')->assertRedirect();
        $don = Order::latest('id')->firstOrFail();

        $hang = $don->items()->where('is_gift', false)->sole();
        $cacQua = $don->items()->where('is_gift', true)->get();
        $this->assertCount(2, $cacQua);
        $this->assertTrue($cacQua->every(fn ($q) => (int) $q->parent_item_id === $hang->id && (string) $q->line_total === '0.00' && $q->quantity === 2));
        $this->assertSame(bcadd('600000.00', (string) $don->shipping_fee, 2), (string) $don->grand_total, 'Quà không đổi tiền đơn');

        $this->assertSame(3, $chau->fresh()->stock_quantity);
        $this->assertSame(8, $tui->giftItem->fresh()->stock_quantity);

        $html = $this->get(route('shop.orders.show', $don))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#' . preg_quote($hang->product_name, '#') . '.*?data-dong-qua="' . $cacQua[0]->id . '"#s', $html, 'Quà nằm dưới món hàng');
        $this->assertStringContainsString('Quà miễn phí', $html);

        app(OrderService::class)->changeStatus($don->fresh(), OrderStatus::Cancelled, 'Khách đổi ý');

        $this->assertSame(5, $chau->fresh()->stock_quantity);
        $this->assertSame(10, $tui->giftItem->fresh()->stock_quantity);
    }

    #[Test]
    public function trang_san_pham_hien_mua_1_mat_hang_nhan_qua(): void
    {
        $senDa = $this->sp();
        $pg = $this->quaKem($senDa);

        $this->get(route('shop.products.show', $senDa))
            ->assertSee('Mua 1 mặt hàng – nhận quà miễn phí')
            ->assertSee('data-qua-kem="' . $pg->id . '"', false);

        $this->get(route('shop.products.show', $this->sp()))->assertDontSee('data-qua-san-pham', false);
    }

    /* ================= QUÀ THEO CHƯƠNG TRÌNH ================= */

    #[Test]
    public function chuong_trinh_don_toi_thieu_va_hang_thanh_vien(): void
    {
        $sp = $this->sp('300000.00');
        $hoa = MemberTier::where('code', 'hoa')->value('id');
        $theoDon = $this->chuongTrinh(['min_order_amount' => '500000']);
        $theoHang = $this->chuongTrinh(['name' => 'Quà hạng Hoa', 'min_member_tier_id' => $hoa]);

        $this->assertSame([], $this->nhan($this->gio($sp, 1), null));
        $this->assertSame(['chuong_trinh-' . $theoDon->id . ':1'], $this->nhan($this->gio($sp, 2), null));

        $khachHoa = User::factory()->create();
        $this->donCua($khachHoa, OrderStatus::Completed, '5000000.00');
        $this->assertContains('chuong_trinh-' . $theoHang->id . ':1', $this->nhan($this->gio($sp, 1), $khachHoa));
        $this->assertNotContains('chuong_trinh-' . $theoHang->id . ':1', $this->nhan($this->gio($sp, 1), User::factory()->create()));
    }

    #[Test]
    public function chuong_trinh_don_dau_tien_can_dang_nhap_va_don_huy_khong_tinh(): void
    {
        $sp = $this->sp();
        $ct = $this->chuongTrinh(['first_order_only' => true]);

        $this->assertSame([], $this->nhan($this->gio($sp), null));

        $moi = User::factory()->create();
        $this->donCua($moi, OrderStatus::Cancelled);
        $this->assertSame(['chuong_trinh-' . $ct->id . ':1'], $this->nhan($this->gio($sp), $moi));

        $this->donCua($moi, OrderStatus::Pending);
        $this->assertSame([], $this->nhan($this->gio($sp), $moi));
    }

    #[Test]
    public function chuong_trinh_het_suat_het_kho_ngung_ngoai_thoi_gian_thi_khong_tang(): void
    {
        $sp = $this->sp();

        $hetSuat = $this->chuongTrinh(['total_limit' => 3]);
        $hetSuat->forceFill(['used_count' => 3])->save();
        $this->chuongTrinh([], ['stock_quantity' => 0]);
        $this->chuongTrinh([], ['is_active' => false]);
        $this->chuongTrinh(['ends_at' => now()->subMinute()]);
        $this->chuongTrinh(['status' => 'draft']);

        $this->assertSame([], $this->nhan($this->gio($sp), null));
    }

    #[Test]
    public function chuong_trinh_moi_tai_khoan_mot_lan_dem_tu_don_that_huy_don_tra_suat(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $sp = $this->sp();
        $ct = $this->chuongTrinh(['per_user_limit' => 1, 'total_limit' => 5]);

        $this->vaoThanhToan($sp);
        $this->post('/thanh-toan/dat-hang');
        $don = Order::latest('id')->firstOrFail();

        $this->assertSame(1, $ct->fresh()->used_count);
        $this->assertNull($don->items()->where('is_gift', true)->sole()->parent_item_id, 'Quà chương trình không thuộc món nào');
        $this->assertSame([], $this->nhan($this->gio($sp), $u));

        app(OrderService::class)->changeStatus($don->fresh(), OrderStatus::Cancelled, 'Khách đổi ý');

        $this->assertSame(0, $ct->fresh()->used_count);
        $this->assertSame(10, $ct->giftItem->fresh()->stock_quantity);
    }

    #[Test]
    public function tranh_suat_cuoi_cung_luc_thi_co_so_du_lieu_phan_xu(): void
    {
        /*
         * ĐUA THẬT: GiftResolver đọc thấy còn suất (bản ghi cũ trong bộ nhớ),
         * nhưng trong cơ sở dữ liệu người khác đã lấy suất cuối — chốt UPDATE
         * có điều kiện của GiftGranter phải chặn.
         */
        $this->actingAs(User::factory()->create());
        $sp = $this->sp();
        $ct = $this->chuongTrinh(['total_limit' => 1]);
        $cu = $ct->fresh(['giftItem']);

        $this->app->instance(GiftResolver::class, new class($cu) extends GiftResolver
        {
            public function __construct(private GiftCampaign $cu)
            {
            }

            public function choGio(CheckoutBasket $basket, ?User $user): \Illuminate\Support\Collection
            {
                return collect([[
                    'nguon' => 'chuong_trinh', 'campaign' => $this->cu, 'product_gift' => null,
                    'item' => $this->cu->giftItem, 'quantity' => 1, 'for_product_id' => null,
                ]]);
            }
        });

        $this->vaoThanhToan($sp);
        GiftCampaign::whereKey($ct->id)->update(['used_count' => 1]);

        $this->post('/thanh-toan/dat-hang')->assertRedirect();
        $don = Order::latest('id')->firstOrFail();

        $this->assertSame(0, $don->items()->where('is_gift', true)->count(), 'Không lố suất');
        $this->assertSame(1, $ct->fresh()->used_count);
        $this->assertSame(10, $ct->giftItem->fresh()->stock_quantity, 'Không giữ được suất thì không trừ kho');
    }

    /* ================= QUẢN TRỊ ================= */

    #[Test]
    public function quan_tri_chon_san_pham_them_sua_bo_qua(): void
    {
        $this->actingAs($this->admin());
        $senDa = $this->sp();
        $phanBon = $this->sp('20000.00');

        $this->get(route('admin.product-gifts.open', ['product_id' => $senDa->id]))
            ->assertRedirect(route('admin.product-gifts.edit', $senDa));

        // Quà là sản phẩm có sẵn.
        $this->post(route('admin.product-gifts.store', $senDa), [
            'nguon' => 'san_pham', 'gift_product_id' => $phanBon->id, 'per_quantity' => 1, 'gift_quantity' => 1,
        ])->assertSessionHasNoErrors();

        // Tạo vật phẩm tặng riêng tại chỗ — bắt buộc số lượng.
        $this->post(route('admin.product-gifts.store', $senDa), [
            'nguon' => 'vat_pham_moi', 'name' => 'Thẻ chăm cây', 'kind' => 'qua_tang', 'per_quantity' => 1, 'gift_quantity' => 1,
        ])->assertSessionHasErrors('stock_quantity');
        $this->post(route('admin.product-gifts.store', $senDa), [
            'nguon' => 'vat_pham_moi', 'name' => 'Thẻ chăm cây', 'kind' => 'qua_tang', 'stock_quantity' => 30, 'per_quantity' => 2, 'gift_quantity' => 1,
        ])->assertSessionHasNoErrors();

        $cacQua = ProductGift::where('product_id', $senDa->id)->with('giftItem')->get();
        $this->assertCount(2, $cacQua);
        $this->assertSame($phanBon->id, $cacQua->firstWhere('giftItem.name', $phanBon->name)?->giftItem->product_id);

        $html = $this->get(route('admin.product-gifts.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-san-pham-co-qua="' . $senDa->id . '"', $html);

        // Sửa: bỏ tích "đang tặng", đổi số lượng, chỉnh tồn vật phẩm riêng.
        $the = $cacQua->firstWhere('giftItem.name', 'Thẻ chăm cây');
        $this->put(route('admin.product-gifts.update', [$senDa, $the]), ['per_quantity' => 1, 'gift_quantity' => 3, 'stock_quantity' => 12])
            ->assertSessionHasNoErrors();
        $the->refresh();
        $this->assertFalse($the->is_active);
        $this->assertSame(3, $the->gift_quantity);
        $this->assertSame(12, $the->giftItem->fresh()->stock_quantity);

        // Quà của sản phẩm khác không sửa được qua đường dẫn sản phẩm này.
        $this->put(route('admin.product-gifts.update', [$phanBon, $the]), ['per_quantity' => 1, 'gift_quantity' => 1])->assertNotFound();

        $this->delete(route('admin.product-gifts.destroy', [$senDa, $the]))->assertRedirect();
        $this->assertDatabaseMissing('product_gifts', ['id' => $the->id]);
    }

    #[Test]
    public function khuyen_mai_la_trang_tong_hop_co_tab_qua_theo_chuong_trinh(): void
    {
        $this->actingAs($this->admin());

        foreach (['admin.promotions.index', 'admin.gift-campaigns.index', 'admin.member-tiers.index'] as $ten) {
            $html = $this->get(route($ten))->assertOk()->getContent();
            $this->assertStringContainsString('data-tab-khuyen-mai', $html, $ten);
            $this->assertStringContainsString('href="' . route('admin.gift-campaigns.index') . '"', $html);
        }

        $vat = $this->vat();
        $this->post(route('admin.gift-campaigns.store'), [
            'name' => 'Quà Trung thu', 'gift_item_id' => $vat->id, 'gift_quantity' => 1, 'status' => 'active',
            'kind' => 'kem_san_pham', 'trigger_product_id' => $this->sp()->id,
        ])->assertSessionHasNoErrors();

        // Tab Khuyến mại chỉ tạo quà theo chương trình — quà kèm sản phẩm đi đường riêng.
        $ct = GiftCampaign::sole();
        $this->assertSame('chuong_trinh', $ct->kind->value);
        $this->assertNull($ct->trigger_product_id);

        $ct->forceFill(['used_count' => 2])->save();
        $this->delete(route('admin.gift-campaigns.destroy', $ct))->assertSessionHas('error');

        $nv = User::factory()->create();
        $nv->role = UserRole::Staff;
        $nv->save();
        $this->actingAs($nv)->get(route('admin.product-gifts.index'))->assertForbidden();
    }
}
