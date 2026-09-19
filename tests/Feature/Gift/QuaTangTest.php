<?php

namespace Tests\Feature\Gift;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\GiftItem;
use App\Models\MemberTier;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGift;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Checkout\CheckoutBasket;
use App\Services\Checkout\CheckoutLine;
use App\Services\Gift\GiftResolver;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Quà tặng: quà kèm sản phẩm (luật riêng từng món), khuyến mại hình thức Tặng quà, quà trong giỏ và đơn. */
class QuaTangTest extends TestCase
{
    use RefreshDatabase;

    private function sp(string $gia = '300000.00', int $ton = 50): Product
    {
        return Product::factory()->for(Category::factory())->price($gia)->stock($ton)->create();
    }

    private function quyCach(Product $sp, string $ten): ProductVariant
    {
        return $sp->variants()->create(['name' => $ten, 'is_active' => true, 'stock_quantity' => 20, 'track_inventory' => true]);
    }

    private function vat(array $ghiDe = []): GiftItem
    {
        return GiftItem::create(array_merge(['name' => 'Túi phân bón nhỏ', 'kind' => 'qua_tang', 'stock_quantity' => 10, 'is_active' => true, 'value' => '15000'], $ghiDe));
    }

    private function quaKem(Product $sp, ?GiftItem $vat = null, array $luat = [], ?ProductVariant $qc = null): ProductGift
    {
        $pg = new ProductGift(array_merge(['per_quantity' => 1, 'gift_quantity' => 1, 'is_active' => true], $luat));
        $pg->product_id = $sp->id;
        $pg->product_variant_id = $qc?->id;
        $pg->gift_item_id = ($vat ?? $this->vat())->id;
        $pg->save();

        return $pg;
    }

    private function chuongTrinh(array $ghiDeCt = [], array $ghiDeVat = []): Promotion
    {
        static $so = 0;

        $km = Promotion::create(array_merge([
            'name' => 'Quà Trung thu', 'slug' => 'qua-' . ++$so, 'type' => 'tang_qua', 'discount_value' => 0,
            'gift_item_id' => $this->vat($ghiDeVat)->id, 'gift_quantity' => 1, 'status' => 'active', 'priority' => 0,
        ], $ghiDeCt));

        return $km;
    }

    private function gio(array $dong): CheckoutBasket
    {
        return new CheckoutBasket(collect(array_map(fn ($d) => new CheckoutLine($d[0], $d[1], $d[2]), $dong)));
    }

    private function nhan(CheckoutBasket $gio, ?User $u = null): array
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

    private function luatHopLe(array $ghiDe = []): array
    {
        return array_merge(['per_quantity' => 1, 'gift_quantity' => 1, 'khi_thieu_kho' => 'tang_phan_con', 'tra_hang' => 'kem_qua'], $ghiDe);
    }

    #[Test]
    public function mua_moi_n_tang_m_va_toi_da_moi_don(): void
    {
        $senDa = $this->sp();
        $khac = $this->sp();
        $moi2 = $this->quaKem($senDa, luat: ['per_quantity' => 2]);
        $coTran = $this->quaKem($senDa, $this->vat(['name' => 'Thẻ']), ['max_quantity' => 2]);

        $this->assertSame([], $this->nhan($this->gio([[$khac, null, 10]])));
        $this->assertSame(['san_pham-' . $coTran->id . ':1'], $this->nhan($this->gio([[$senDa, null, 1]])), 'Mua 1 chưa đủ "mỗi 2"');
        $this->assertSame(
            ['san_pham-' . $moi2->id . ':5', 'san_pham-' . $coTran->id . ':2'],
            $this->nhan($this->gio([[$senDa, null, 10]])),
            'Mua 10: mỗi 2 tặng 1 → 5; mỗi 1 tặng 1 tối đa 2 → 2',
        );
    }

    #[Test]
    public function qua_theo_quy_cach_chi_dem_dung_quy_cach_con_moi_quy_cach_cong_tat_ca(): void
    {
        $senDa = $this->sp();
        $nho = $this->quyCach($senDa, 'Chậu 12cm');
        $lon = $this->quyCach($senDa, 'Chậu 18cm');
        $chiLon = $this->quaKem($senDa, luat: [], qc: $lon);
        $moiQc = $this->quaKem($senDa, $this->vat(['name' => 'Sticker']));

        $this->assertSame(['san_pham-' . $moiQc->id . ':2'], $this->nhan($this->gio([[$senDa, $nho, 2]])));
        $this->assertSame(
            ['san_pham-' . $chiLon->id . ':3', 'san_pham-' . $moiQc->id . ':5'],
            $this->nhan($this->gio([[$senDa, $nho, 2], [$senDa, $lon, 3]])),
        );

        $theoDong = app(GiftResolver::class)->theoDong($this->gio([[$senDa, $nho, 2], [$senDa, $lon, 3]]));
        $this->assertSame([$chiLon->id], array_map(fn ($d) => $d['product_gift']->id, $theoDong[GiftResolver::khoaDong($senDa->id, $lon->id)]));
        $this->assertSame([$moiQc->id], array_map(fn ($d) => $d['product_gift']->id, $theoDong[GiftResolver::khoaDong($senDa->id, $nho->id)]));
    }

    #[Test]
    public function luat_thieu_kho_rieng_tung_mon_va_qua_tat(): void
    {
        $senDa = $this->sp();
        $tangPhanCon = $this->quaKem($senDa, $this->vat(['stock_quantity' => 2]));
        $doi = $this->quaKem($senDa, $this->vat(['name' => 'Bộ quà đôi', 'stock_quantity' => 2]), ['khi_thieu_kho' => 'khong_tang']);

        $this->assertSame(['san_pham-' . $tangPhanCon->id . ':2'], $this->nhan($this->gio([[$senDa, null, 5]])));

        $tangPhanCon->giftItem->update(['stock_quantity' => 0]);
        $this->assertSame([], $this->nhan($this->gio([[$senDa, null, 5]])));

        $tangPhanCon->giftItem->update(['stock_quantity' => 9]);
        $tangPhanCon->update(['is_active' => false]);
        $this->assertSame(['san_pham-' . $doi->id . ':1'], $this->nhan($this->gio([[$senDa, null, 1]])));
    }

    #[Test]
    public function gio_hang_hien_qua_duoi_mon_va_tu_tinh_lai_khi_doi_so_luong_hay_xoa(): void
    {
        $this->actingAs(User::factory()->create());
        $senDa = $this->sp();
        $this->quaKem($senDa);

        $this->post('/gio-hang', ['product_id' => $senDa->id, 'quantity' => 3]);
        $dong = \App\Models\CartItem::sole();

        $this->get(route('shop.cart.index'))->assertOk()
            ->assertSee('data-qua-dong="' . $dong->id . '"', false)
            ->assertSee('<span data-so-qua>3</span>', false);

        $sauKhiSua = $this->patchJson(route('shop.cart.update', $dong), ['quantity' => 1])->assertOk()->json('html');
        $this->assertSame(1, $dong->fresh()->quantity);
        $this->assertStringContainsString('<span data-so-qua>1</span>', $sauKhiSua);
        $this->assertStringNotContainsString('<span data-so-qua>3</span>', $sauKhiSua);

        $sauKhiXoa = $this->deleteJson(route('shop.cart.destroy', $dong))->assertOk()->json('html');
        $this->assertStringNotContainsString('data-qua-dong', (string) $sauKhiXoa);
    }

    #[Test]
    public function dat_hang_qua_duoi_mon_0d_chup_sku_tru_kho_huy_don_tra_kho(): void
    {
        $this->actingAs(User::factory()->create());
        $senDa = $this->sp('300000.00');
        $chau = $this->sp('50000.00', 5);
        $chau->forceFill(['product_code' => 'CHAU-MINI-01'])->save();
        $vatSanPham = GiftItem::create(['name' => 'Chậu sứ mini', 'kind' => 'do_vat', 'product_id' => $chau->id, 'is_active' => true]);
        $this->quaKem($senDa, $vatSanPham);
        $tui = $this->quaKem($senDa);

        $this->vaoThanhToan($senDa, 2);
        $this->get(route('shop.checkout.details'))->assertSee('data-qua="san_pham-' . $tui->id . '"', false);

        $this->post('/thanh-toan/dat-hang')->assertRedirect();
        $don = Order::latest('id')->firstOrFail();

        $hang = $don->items()->where('is_gift', false)->sole();
        $cacQua = $don->items()->where('is_gift', true)->orderBy('id')->get();
        $this->assertCount(2, $cacQua);
        $this->assertTrue($cacQua->every(fn ($q) => (int) $q->parent_item_id === $hang->id && (string) $q->line_total === '0.00' && $q->quantity === 2));
        $this->assertSame('CHAU-MINI-01', $cacQua->firstWhere('product_id', $chau->id)->product_sku, 'Chụp SKU vào dòng quà');
        $this->assertSame(bcadd('600000.00', (string) $don->shipping_fee, 2), (string) $don->grand_total, 'Quà không đổi tiền đơn');

        $this->assertSame(3, $chau->fresh()->stock_quantity);
        $this->assertSame(8, $tui->giftItem->fresh()->stock_quantity);

        $html = $this->get(route('shop.orders.show', $don))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#' . preg_quote($hang->product_name, '#') . '.*?data-dong-qua="' . $cacQua[0]->id . '"#s', $html, 'Quà nằm dưới món hàng');

        app(OrderService::class)->changeStatus($don->fresh(), OrderStatus::Cancelled, 'Khách đổi ý');

        $this->assertSame(5, $chau->fresh()->stock_quantity);
        $this->assertSame(10, $tui->giftItem->fresh()->stock_quantity);
    }

    #[Test]
    public function trang_san_pham_noi_so_qua_va_chi_tiet(): void
    {
        $senDa = $this->sp();
        $mot = $this->quaKem($senDa);

        $this->get(route('shop.products.show', $senDa))
            ->assertSee('Mua 1 mặt hàng – nhận quà miễn phí')
            ->assertSee('data-qua-kem="' . $mot->id . '"', false);

        $this->quaKem($senDa, $this->vat(['name' => 'Thẻ chăm cây']));
        $this->quaKem($senDa, $this->vat(['name' => 'Sticker']));
        $this->get(route('shop.products.show', $senDa))->assertSee('Mua 1 mặt hàng – nhận 3 quà miễn phí');

        $this->get(route('shop.products.show', $this->sp()))->assertDontSee('data-qua-san-pham', false);
    }

    #[Test]
    public function chuong_trinh_don_toi_thieu_va_hang_thanh_vien(): void
    {
        $sp = $this->sp('300000.00');
        $hoa = MemberTier::where('code', 'hoa')->value('id');
        $theoDon = $this->chuongTrinh(['min_order_amount' => '500000']);
        $theoHang = $this->chuongTrinh(['name' => 'Quà hạng Hoa', 'min_member_tier_id' => $hoa]);

        $this->assertSame([], $this->nhan($this->gio([[$sp, null, 1]])));
        $goiY = app(GiftResolver::class)->goiYMuaThem($this->gio([[$sp, null, 1]]), null);
        $this->assertSame([[$theoDon->id, '200000.00']], $goiY->map(fn ($g) => [$g['khuyen_mai']->id, $g['con_thieu']])->all(), 'Chỉ gợi ý quà còn thiếu tiền, không gợi ý quà theo hạng');
        $this->assertSame(['chuong_trinh-' . $theoDon->id . ':1'], $this->nhan($this->gio([[$sp, null, 2]])));
        $this->assertTrue(app(GiftResolver::class)->goiYMuaThem($this->gio([[$sp, null, 2]]), null)->isEmpty());

        $khachHoa = User::factory()->create();
        $this->donCua($khachHoa, OrderStatus::Completed, '5000000.00');
        $this->assertContains('chuong_trinh-' . $theoHang->id . ':1', $this->nhan($this->gio([[$sp, null, 1]]), $khachHoa));
        $this->assertNotContains('chuong_trinh-' . $theoHang->id . ':1', $this->nhan($this->gio([[$sp, null, 1]]), User::factory()->create()));
    }

    #[Test]
    public function chuong_trinh_don_dau_tien_can_dang_nhap_va_don_huy_khong_tinh(): void
    {
        $sp = $this->sp();
        $ct = $this->chuongTrinh(['first_order_only' => true]);

        $this->assertSame([], $this->nhan($this->gio([[$sp, null, 1]])));

        $moi = User::factory()->create();
        $this->donCua($moi, OrderStatus::Cancelled);
        $this->assertSame(['chuong_trinh-' . $ct->id . ':1'], $this->nhan($this->gio([[$sp, null, 1]]), $moi));

        $this->donCua($moi, OrderStatus::Pending);
        $this->assertSame([], $this->nhan($this->gio([[$sp, null, 1]]), $moi));
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

        $this->assertSame([], $this->nhan($this->gio([[$sp, null, 1]])));
    }

    #[Test]
    public function chuong_trinh_moi_tai_khoan_mot_lan_huy_don_tra_suat(): void
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
        $this->assertSame([], $this->nhan($this->gio([[$sp, null, 1]]), $u));

        app(OrderService::class)->changeStatus($don->fresh(), OrderStatus::Cancelled, 'Khách đổi ý');

        $this->assertSame(0, $ct->fresh()->used_count);
        $this->assertSame(10, $ct->giftItem->fresh()->stock_quantity);
    }

    #[Test]
    public function tranh_suat_cuoi_cung_luc_thi_co_so_du_lieu_phan_xu(): void
    {
        $this->actingAs(User::factory()->create());
        $sp = $this->sp();
        $ct = $this->chuongTrinh(['total_limit' => 1]);
        $cu = $ct->fresh(['giftItem']);

        $this->app->instance(GiftResolver::class, new class($cu) extends GiftResolver
        {
            public function __construct(private Promotion $cu)
            {
            }

            public function choGio(CheckoutBasket $basket, ?User $user): \Illuminate\Support\Collection
            {
                return collect([[
                    'nguon' => 'chuong_trinh', 'campaign' => $this->cu, 'product_gift' => null,
                    'item' => $this->cu->giftItem, 'quantity' => 1, 'for_product_id' => null,
                    'for_variant_id' => null, 'dong_cha' => null,
                ]]);
            }
        });

        $this->vaoThanhToan($sp);
        Promotion::whereKey($ct->id)->update(['used_count' => 1]);

        $this->post('/thanh-toan/dat-hang')->assertRedirect();
        $don = Order::latest('id')->firstOrFail();

        $this->assertSame(0, $don->items()->where('is_gift', true)->count(), 'Không lố suất');
        $this->assertSame(1, $ct->fresh()->used_count);
        $this->assertSame(10, $ct->giftItem->fresh()->stock_quantity);
    }

    #[Test]
    public function trang_quan_tri_them_quà_dung_truoc_danh_sach_va_trong_thi_nhe_nhang(): void
    {
        $this->actingAs($this->admin());
        $senDa = $this->sp();

        $html = $this->get(route('admin.product-gifts.edit', $senDa))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'data-danh-sach-qua'), strpos($html, 'data-them-qua'), 'Khối Thêm quà đứng trước danh sách');
        $this->assertStringContainsString('Chưa có quà tặng kèm. Hãy thêm quà ở phía trên.', $html);
        $this->assertMatchesRegularExpression('#data-qua-chon-quy-cach[^>]*disabled#', $html, 'Chưa chọn sản phẩm thì ô quy cách khoá');
    }

    #[Test]
    public function quan_tri_chan_quy_cach_khong_thuoc_dung_san_pham(): void
    {
        $this->actingAs($this->admin());
        $senDa = $this->sp();
        $phanBon = $this->sp('20000.00');
        $khac = $this->sp();
        $qcKhac = $this->quyCach($khac, 'Của sản phẩm khác');

        $this->post(route('admin.product-gifts.store', $senDa), $this->luatHopLe([
            'nguon' => 'san_pham', 'gift_product_id' => $phanBon->id, 'gift_variant_id' => $qcKhac->id,
        ]))->assertSessionHasErrors('gift_variant_id');

        $this->post(route('admin.product-gifts.store', $senDa), $this->luatHopLe([
            'nguon' => 'san_pham', 'gift_product_id' => $phanBon->id, 'trigger_variant_id' => $qcKhac->id,
        ]))->assertSessionHasErrors('trigger_variant_id');

        $this->assertDatabaseCount('product_gifts', 0);
    }

    #[Test]
    public function quan_tri_sua_moi_luat_tung_mon_va_khong_nhan_doi(): void
    {
        $this->actingAs($this->admin());
        $senDa = $this->sp();
        $lon = $this->quyCach($senDa, 'Chậu 18cm');
        $phanBon = $this->sp('20000.00');

        $this->post(route('admin.product-gifts.store', $senDa), $this->luatHopLe(['nguon' => 'san_pham', 'gift_product_id' => $phanBon->id]))
            ->assertSessionHasNoErrors();
        $this->post(route('admin.product-gifts.store', $senDa), $this->luatHopLe(['nguon' => 'san_pham', 'gift_product_id' => $phanBon->id, 'gift_quantity' => 2]));
        $this->assertSame(1, ProductGift::count());
        $this->assertSame(2, ProductGift::sole()->gift_quantity);

        $this->post(route('admin.product-gifts.store', $senDa), $this->luatHopLe(['nguon' => 'san_pham', 'gift_product_id' => $phanBon->id, 'trigger_variant_id' => $lon->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, ProductGift::count());

        $pg = ProductGift::whereNull('product_variant_id')->sole();

        $this->put(route('admin.product-gifts.update', [$senDa, $pg]), [
            'per_quantity' => 2, 'gift_quantity' => 1, 'max_quantity' => 3,
            'khi_thieu_kho' => 'khong_tang', 'tra_hang' => 'khong_thu_hoi', 'cho_doi_hang' => '1',
        ])->assertSessionHasNoErrors();

        $pg->refresh();
        $this->assertSame([2, 1, 3], [$pg->per_quantity, $pg->gift_quantity, $pg->max_quantity]);
        $this->assertSame('khong_tang', $pg->khi_thieu_kho->value);
        $this->assertSame('khong_thu_hoi', $pg->tra_hang->value);
        $this->assertTrue($pg->cho_doi_hang);
        $this->assertFalse($pg->is_active);

        $this->put(route('admin.product-gifts.update', [$senDa, $pg]), $this->luatHopLe(['trigger_variant_id' => $lon->id]))
            ->assertSessionHasErrors('trigger_variant_id');

        $this->put(route('admin.product-gifts.update', [$phanBon, $pg]), $this->luatHopLe())->assertNotFound();

        $this->delete(route('admin.product-gifts.destroy', [$senDa, $pg]))->assertRedirect();
        $this->assertDatabaseMissing('product_gifts', ['id' => $pg->id]);
    }

    #[Test]
    public function chuong_trinh_co_san_pham_chi_tang_khi_gio_co_san_pham_do(): void
    {
        $senDa = $this->sp();
        $khac = $this->sp();
        $km = $this->chuongTrinh();
        $km->products()->attach($senDa->id);

        $this->assertSame([], $this->nhan($this->gio([[$khac, null, 1]])));
        $this->assertSame(['chuong_trinh-' . $km->id . ':1'], $this->nhan($this->gio([[$senDa, null, 1], [$khac, null, 1]])));
    }

    #[Test]
    public function khuyen_mai_tang_qua_khong_doi_gia_san_pham(): void
    {
        $sp = $this->sp('300000.00');
        $giamRieng = $this->sp('300000.00');
        $km = $this->chuongTrinh();
        $km->products()->attach($sp->id);
        $km->products()->attach($giamRieng->id, ['discount_type' => 'percent', 'discount_value' => 50]);

        $this->assertFalse($sp->fresh()->price()->isDiscounted(), 'Dòng theo chương trình tặng quà thì không giảm giá');
        $this->assertSame('150000.00', $giamRieng->fresh()->price()->finalPrice, 'Dòng chọn giảm riêng thì được giảm');
        $this->assertSame('Tặng Túi phân bón nhỏ', $km->headlineDiscount());
    }

    #[Test]
    public function chuong_trinh_giam_gia_co_dong_tang_qua_rieng(): void
    {
        $chau = $this->sp('300000.00');
        $lan = $this->sp('500000.00');
        $binhTuoi = $this->sp('40000.00', 20);
        $km = Promotion::create(['name' => 'Tuần lễ cây xanh', 'slug' => 'tuan-le', 'type' => 'percent', 'discount_value' => 10, 'status' => 'active', 'priority' => 0]);
        $km->products()->attach($chau->id);
        $km->products()->attach($lan->id, ['discount_type' => 'tang_qua', 'gift_item_id' => GiftItem::tuSanPham($binhTuoi)->id, 'gift_quantity' => 2]);

        $this->assertSame('270000.00', $chau->fresh()->price()->finalPrice);
        $this->assertFalse($lan->fresh()->price()->isDiscounted(), 'Sản phẩm tặng quà thì giữ giá gốc');
        $this->assertSame('Giảm đến 10% · có quà kèm', $km->fresh()->headlineDiscount());

        $this->assertSame([], $this->nhan($this->gio([[$chau, null, 1]])));

        $qua = app(GiftResolver::class)->choGio($this->gio([[$chau, null, 1], [$lan, null, 1]]), null)->sole();
        $this->assertSame([$km->id, 2, $binhTuoi->id], [$qua['campaign']->id, $qua['quantity'], $qua['item']->product_id]);
        $this->assertSame(GiftResolver::khoaDong($lan->id, null), $qua['dong_cha'], 'Quà riêng nằm dưới đúng món');

        request()->attributes->remove('san_pham_co_qua');
        $this->assertArrayHasKey($lan->id, GiftResolver::sanPhamCoQua());
        $this->assertArrayNotHasKey($chau->id, GiftResolver::sanPhamCoQua());
    }

    #[Test]
    public function nhieu_qua_cung_chuong_trinh_mot_don_chi_tinh_mot_suat(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->sp();
        $b = $this->sp();
        $km = $this->chuongTrinh(['total_limit' => 5]);
        $km->products()->attach($a->id);
        $km->products()->attach($b->id, ['gift_item_id' => $this->vat(['name' => 'Sỏi trang trí'])->id, 'gift_quantity' => 1]);

        $this->post('/gio-hang', ['product_id' => $a->id, 'quantity' => 1])->assertRedirect();
        $this->vaoThanhToan($b);
        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        $don = Order::latest('id')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Túi phân bón nhỏ', 'Sỏi trang trí'], $don->items()->where('is_gift', true)->pluck('product_name')->all());
        $this->assertSame(1, $km->fresh()->used_count);

        app(OrderService::class)->changeStatus($don->fresh(), OrderStatus::Cancelled, 'Khách đổi ý');
        $this->assertSame(0, $km->fresh()->used_count);
    }

    #[Test]
    public function quan_tri_chon_qua_rieng_tung_san_pham_ke_ca_lay_san_pham_dang_ban(): void
    {
        $this->actingAs($this->admin());
        $chau = $this->sp();
        $lan = $this->sp();
        $phanBon = $this->sp('20000.00');
        $vat = $this->vat();
        $km = Promotion::create(['name' => 'Tuần lễ', 'slug' => 'tuan-le-qt', 'type' => 'percent', 'discount_value' => 10, 'status' => 'draft', 'priority' => 0]);

        $this->put(route('admin.promotions.sync-products', $km), ['products' => [
            ['id' => $chau->id, 'discount_type' => 'tang_qua', 'qua' => ''],
        ]])->assertSessionHasErrors('products.0.qua');

        $this->put(route('admin.promotions.sync-products', $km), ['products' => [
            ['id' => $chau->id, 'discount_type' => '', 'discount_value' => ''],
            ['id' => $lan->id, 'discount_type' => 'tang_qua', 'qua' => 'sp:' . $phanBon->id, 'gift_quantity' => 3],
            ['id' => $phanBon->id, 'discount_type' => 'tang_qua', 'qua' => 'vp:' . $vat->id],
        ]])->assertSessionHasNoErrors();

        $dong = $km->products()->get()->keyBy('id');
        $this->assertNull($dong[$chau->id]->pivot->gift_item_id);
        $this->assertEquals(270000, (float) $dong[$chau->id]->pivot->promotional_price);
        $this->assertSame($phanBon->id, GiftItem::find($dong[$lan->id]->pivot->gift_item_id)->product_id, 'Sản phẩm đang bán được biến thành vật phẩm quà');
        $this->assertSame(3, (int) $dong[$lan->id]->pivot->gift_quantity);
        $this->assertSame($vat->id, (int) $dong[$phanBon->id]->pivot->gift_item_id);

        $this->get(route('admin.promotions.edit', $km))->assertOk()
            ->assertSee('Sản phẩm áp dụng và ưu đãi từng sản phẩm')
            ->assertSee('Tặng 3 × ' . $phanBon->name);

        $this->put(route('admin.promotions.update', $km), [
            'name' => 'Tuần lễ', 'type' => 'tang_qua', 'status' => 'draft', 'priority' => 0, 'gift_quantity' => 1,
        ])->assertSessionHasErrors('gift_item_id');
    }

    #[Test]
    public function khuyen_mai_la_trang_tong_hop_va_tao_chuong_trinh_tang_qua(): void
    {
        $this->actingAs($this->admin());

        foreach (['admin.promotions.index', 'admin.member-tiers.index'] as $ten) {
            $this->get(route($ten))->assertOk()->assertSee('data-tab-khuyen-mai', false)->assertDontSee('Quà theo chương trình');
        }

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.gift-campaigns.index'));

        $vat = $this->vat();

        $this->post(route('admin.promotions.store'), [
            'name' => 'Quà Trung thu', 'type' => 'tang_qua', 'status' => 'active', 'priority' => 0,
            'gift_item_id' => $vat->id, 'gift_quantity' => 2, 'min_order_amount' => 500000, 'first_order_only' => '1', 'total_limit' => 10,
        ])->assertSessionHasNoErrors();

        $km = Promotion::sole();
        $this->assertSame('tang_qua', $km->type->value);
        $this->assertSame([2, '500000.00', true, 10], [$km->gift_quantity, $km->min_order_amount, $km->first_order_only, $km->total_limit]);
        $this->assertSame('0.00', $km->discount_value);

        $this->get(route('admin.promotions.edit', $km))->assertOk()->assertSee('Sản phẩm áp dụng và ưu đãi từng sản phẩm');

        $km->forceFill(['used_count' => 2])->save();

        $this->put(route('admin.promotions.update', $km), [
            'name' => 'Quà Trung thu', 'type' => 'tang_qua', 'gift_item_id' => $vat->id, 'gift_quantity' => 1,
            'total_limit' => 1, 'status' => 'active', 'priority' => 0,
        ])->assertSessionHasErrors('total_limit');

        $this->delete(route('admin.promotions.destroy', $km))->assertSessionHas('error');
        $this->assertModelExists($km);

        $giam = Promotion::create(['name' => 'Giảm', 'slug' => 'giam', 'type' => 'percent', 'discount_value' => 10, 'status' => 'draft', 'priority' => 0]);
        $this->put(route('admin.promotions.update', $giam), [
            'name' => 'Giảm', 'type' => 'percent', 'discount_value' => 15, 'status' => 'draft', 'priority' => 0,
            'gift_item_id' => $vat->id, 'min_order_amount' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertNull($giam->fresh()->gift_item_id, 'Giảm giá thì không có quà chung');
        $this->assertSame('1.00', $giam->fresh()->min_order_amount, 'Điều kiện nhận quà vẫn lưu cho các dòng tặng quà riêng');

        $nv = User::factory()->create();
        $nv->role = UserRole::Staff;
        $nv->save();
        $this->actingAs($nv)->get(route('admin.product-gifts.index'))->assertForbidden();
    }
}
