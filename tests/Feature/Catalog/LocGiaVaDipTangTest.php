<?php

namespace Tests\Feature\Catalog;

use App\Enums\FlowerSeason;
use App\Enums\ProductType;
use App\Enums\SellingForm;
use App\Enums\TraitType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\Catalog\FlowerCollections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Lọc theo giá, dịp tặng, mùa hoa, bộ sưu tập tự động — và các luồng dùng chung chúng. */
class LocGiaVaDipTangTest extends TestCase
{
    use RefreshDatabase;

    private Category $hoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hoa = Category::factory()->create(['name' => 'Hoa', 'slug' => 'hoa']);
    }

    private function hoa(string $ten, string $gia, array $dip = [], array $mua = []): Product
    {
        $sp = Product::factory()->for($this->hoa)->price($gia)->create([
            'name' => $ten,
            'product_type' => ProductType::Flower,
            'selling_form' => SellingForm::Bouquet,
        ]);

        $sp->syncTraits(TraitType::Occasion, $dip);
        $sp->syncTraits(TraitType::Season, $mua);
        $sp->save();

        return $sp;
    }

    private function tenTrenTrang(array $query): array
    {
        return $this->get(route('shop.products.index', $query))
            ->assertOk()
            ->viewData('products')
            ->pluck('name')
            ->all();
    }

    #[Test]
    public function loc_theo_khoang_gia_tinh_bang_gia_dang_ban_sau_khuyen_mai(): void
    {
        $this->hoa('Bó cúc', '250000.00');
        $hong = $this->hoa('Bó hồng', '600000.00');
        $this->hoa('Kệ hoa', '1500000.00');

        $km = Promotion::create(['name' => 'Giảm hồng', 'slug' => 'giam-hong', 'type' => 'percent',
            'discount_value' => 50, 'status' => 'active', 'priority' => 0]);
        $km->products()->attach($hong->id);

        $this->assertEqualsCanonicalizing(['Bó cúc', 'Bó hồng'], $this->tenTrenTrang(['gia-den' => '300.000']),
            'Hồng 600k giảm còn 300k phải lọt vào khoảng dưới 300k');
        $this->assertSame(['Kệ hoa'], $this->tenTrenTrang(['gia-tu' => 1000000]));
        $this->assertSame(['Bó cúc'], $this->tenTrenTrang(['gia-tu' => 200000, 'gia-den' => 280000]));
        $this->assertSame(['Bó cúc'], $this->tenTrenTrang(['gia-tu' => 280000, 'gia-den' => 200000]), 'Nhập ngược thì tự đảo');
    }

    #[Test]
    public function hang_lien_he_bao_gia_khong_lot_vao_khoang_gia_va_nhap_rac_bi_bo_qua(): void
    {
        $this->hoa('Lẵng báo giá', '0.00');
        $this->hoa('Bó cúc', '250000.00');

        $this->assertSame(['Bó cúc'], $this->tenTrenTrang(['gia-den' => 500000]));
        $this->assertCount(2, $this->tenTrenTrang(['gia-tu' => 'abc', 'gia-den' => ['x']]));
    }

    #[Test]
    public function khoang_gia_goi_san_chi_hien_khoang_co_hang_va_ghi_so_khong_ghi_re(): void
    {
        $this->hoa('Bó cúc', '250000.00');
        $this->hoa('Kệ hoa', '1500000.00');

        $res = $this->get(route('shop.products.index'))->assertOk();
        $khoang = $res->viewData('khoangGia');

        $this->assertCount(2, $khoang, 'Chỉ khoảng dưới 300k và từ 1 triệu có hàng');
        $this->assertSame([1, 1], array_column($khoang, 'so_luong'));
        $res->assertDontSee('giá rẻ');
        $res->assertSee('data-loc-gia', false);
    }

    #[Test]
    public function loc_theo_dip_tang_va_trang_co_tieu_de_canonical_rieng(): void
    {
        $this->hoa('Bó hồng', '600000.00', ['tinh-yeu', 'sinh-nhat']);
        $this->hoa('Hướng dương', '380000.00', ['sinh-nhat', 'sinh-vien']);
        $this->hoa('Bó cúc', '250000.00');

        $this->assertEqualsCanonicalizing(['Bó hồng', 'Hướng dương'], $this->tenTrenTrang(['dip' => 'sinh-nhat']));
        $this->assertSame(['Bó hồng'], $this->tenTrenTrang(['dip' => 'tinh-yeu']));

        $this->get(route('shop.products.index', ['dip' => 'sinh-vien']))
            ->assertSee('Hoa sinh viên &amp; tốt nghiệp', false)
            ->assertSee('<link rel="canonical" href="' . route('shop.products.index', ['dip' => 'sinh-vien']) . '">', false);
    }

    #[Test]
    public function hoa_cao_cap_la_hoa_co_gia_dang_ban_tu_nguong_khong_tinh_cay_dat_tien(): void
    {
        config(['catalog.cao_cap_tu' => 800000]);

        $this->hoa('Mẫu đơn', '890000.00');
        $this->hoa('Bó cúc', '250000.00');
        Product::factory()->for($this->hoa)->price('2800000.00')->create(['name' => 'Bonsai']);

        $this->assertSame(['Mẫu đơn'], $this->tenTrenTrang(['bo-suu-tap' => 'cao-cap']));
    }

    #[Test]
    public function hoa_theo_mua_gom_mua_nay_va_mua_ke_va_tu_doi_theo_thang(): void
    {
        $this->hoa('Cúc hoạ mi', '260000.00', [], ['dong']);
        $this->hoa('Đào Tết', '950000.00', [], ['xuan']);
        $this->hoa('Hồng', '600000.00');

        Carbon::setTestNow('2026-09-21 10:00:00');
        $this->assertSame(['Cúc hoạ mi'], $this->tenTrenTrang(['bo-suu-tap' => 'theo-mua']), 'Tháng 9: thu + đông');

        Carbon::setTestNow('2026-12-10 10:00:00');
        $this->assertEqualsCanonicalizing(['Cúc hoạ mi', 'Đào Tết'], $this->tenTrenTrang(['bo-suu-tap' => 'theo-mua']), 'Tháng 12: đông + xuân');

        $this->assertSame(FlowerSeason::Dong, FlowerSeason::cua(Carbon::parse('2027-01-15')));

        Carbon::setTestNow();
    }

    #[Test]
    public function trang_chu_chi_hien_dip_co_hang_va_nhac_dip_le_sap_toi(): void
    {
        $this->hoa('Hướng dương', '380000.00', ['chuc-mung']);

        Carbon::setTestNow('2026-09-21 10:00:00');

        $html = $this->get(route('welcome'))->assertOk()->getContent();

        $this->assertStringContainsString('data-theo-dip', $html);
        $this->assertStringContainsString('Hoa chúc mừng', $html);
        $this->assertStringNotContainsString('Hoa tình yêu', $html, 'Dịp chưa có hàng thì không hiện');
        $this->assertStringContainsString('Ngày Phụ nữ Việt Nam', $html, '20/10 còn 29 ngày');

        Carbon::setTestNow();
    }

    #[Test]
    public function danh_muc_chua_co_hang_bi_an_o_cua_hang(): void
    {
        $this->hoa('Bó cúc', '250000.00');
        $this->assertTrue(Category::where('slug', 'hoa-gia')->where('is_active', true)->exists(), 'Migration đã tạo danh mục Hoa giả');

        $this->get(route('shop.categories.index'))->assertOk()->assertDontSee('Hoa giả');
        $this->assertNotContains('hoa-gia', $this->get(route('shop.products.index'))->viewData('categories')->pluck('slug')->all());
        $this->get(route('shop.categories.show', 'hoa-gia'))->assertOk();
    }

    #[Test]
    public function hoa_gia_ban_duoi_dang_bo_hop_nhung_khong_phai_hoa_tuoi(): void
    {
        $this->assertContains(SellingForm::Bouquet, ProductType::Artificial->allowedSellingForms());
        $this->assertTrue(ProductType::Artificial->fitsCategoryKind(\App\Enums\CategoryKind::Plant));
        $this->assertFalse(ProductType::Artificial->fitsCategoryKind(\App\Enums\CategoryKind::Supply));
        $this->assertNotSame(ProductType::Flower, ProductType::Artificial, 'Không theo lô hoa tươi, không đổi trả vì héo');
    }

    #[Test]
    public function tim_hoa_sinh_nhat_ra_hang_da_gan_nhan_va_ai_biet_dip(): void
    {
        $this->hoa('Bó baby trắng', '450000.00', ['sinh-nhat']);
        $this->hoa('Bó cúc', '250000.00');

        $this->assertSame(['Bó baby trắng'], $this->tenTrenTrang(['q' => 'hoa sinh nhật']));

        $boi = app(\App\Services\AI\AdvisorContext::class)->xayDung('bó baby trắng', null);
        $this->assertStringContainsString('Dịp tặng: Sinh nhật', $boi);
    }

    #[Test]
    public function sitemap_co_trang_dip_va_bo_suu_tap_co_hang(): void
    {
        $this->hoa('Bó hồng', '600000.00', ['tinh-yeu']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(e(route('shop.products.index', ['dip' => 'tinh-yeu'])), $xml);
        $this->assertStringNotContainsString('dip=chia-buon', $xml);
    }

    #[Test]
    public function goi_y_ca_nhan_giai_thich_bang_dip_tang(): void
    {
        $this->assertSame(['tinh-yeu', 'sinh-nhat', 'chuc-mung', 'sinh-vien', 'chia-buon'], array_keys(TraitType::Occasion->options()));
        $this->assertContains(TraitType::Occasion, TraitType::filterable());
        $this->assertSame('dip', TraitType::Occasion->queryKey());

        $ly = (new \ReflectionMethod(\App\Services\Recommendation\TasteProfile::class, 'lyDoNhan'))
            ->invoke(app(\App\Services\Recommendation\TasteProfile::class), TraitType::Occasion, 'Sinh nhật');
        $this->assertSame('Cũng hợp dịp sinh nhật', $ly);
    }

    #[Test]
    public function dem_bo_suu_tap_bo_bo_trong(): void
    {
        $this->hoa('Bó cúc', '250000.00');

        $this->assertSame([], app(FlowerCollections::class)->soLuong());
    }
}
