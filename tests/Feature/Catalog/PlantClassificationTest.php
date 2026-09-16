<?php

namespace Tests\Feature\Catalog;

use App\Enums\TaxonRank;
use App\Enums\TraitType;
use App\Models\Category;
use App\Models\PlantTaxon;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Phân loại cây: cây phân loại sinh học + nhãn sinh thái. */
class PlantClassificationTest extends TestCase
{
    use RefreshDatabase;

    private function sanPham(string $ten, ?PlantTaxon $taxon = null): Product
    {
        $p = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->create(['name' => $ten, 'status' => 'active']);

        if ($taxon) {
            $p->taxon_id = $taxon->id;
            $p->saveQuietly();
        }

        return $p->refresh();
    }

    private function nhanh(): array
    {
        $ho = PlantTaxon::create([
            'rank' => TaxonRank::Family, 'name' => 'Ráy',
            'scientific_name' => 'Araceae', 'slug' => 'ho-ray',
        ]);

        $chi = PlantTaxon::create([
            'parent_id' => $ho->id, 'rank' => TaxonRank::Genus, 'name' => 'Trầu bà lá xẻ',
            'scientific_name' => 'Monstera', 'slug' => 'chi-monstera',
        ]);

        $loai = PlantTaxon::create([
            'parent_id' => $chi->id, 'rank' => TaxonRank::Species, 'name' => 'Trầu bà lá xẻ',
            'scientific_name' => 'Monstera deliciosa', 'slug' => 'loai-monstera',
        ]);

        return [$ho, $chi, $loai];
    }

    #[Test]
    public function chon_bac_rong_phai_ra_ca_hang_gan_o_bac_hep_hon(): void
    {
        [$ho, $chi, $loai] = $this->nhanh();

        $this->sanPham('Monstera chậu gốm', $loai);

        $this->assertSame(1, Product::mainCatalog()->inTaxon($ho)->count(), 'Bậc Họ phải thấy hàng gắn ở bậc Loài.');
        $this->assertSame(1, Product::mainCatalog()->inTaxon($chi)->count());
        $this->assertSame(1, Product::mainCatalog()->inTaxon($loai)->count());
    }

    #[Test]
    public function duong_dan_phan_loai_di_tu_bac_rong_nhat_xuong(): void
    {
        [$ho, $chi, $loai] = $this->nhanh();

        $chuoi = $loai->chain();

        $this->assertSame(
            ['ho-ray', 'chi-monstera', 'loai-monstera'],
            $chuoi->pluck('slug')->all(),
            'Chuỗi phải đọc từ rộng xuống hẹp, không phải ngược lại.',
        );
    }

    #[Test]
    public function ten_khong_chua_san_chu_bac_de_khong_lap_khi_hien_canh_cot_bac(): void
    {
        [$ho] = $this->nhanh();

        $this->assertSame('Ráy', $ho->name);
        $this->assertSame('Họ Ráy', $ho->displayName());
        $this->assertSame('Họ Ráy (Araceae)', $ho->fullName());
        $this->assertStringNotContainsString('Họ Họ', $ho->fullName());
    }

    #[Test]
    public function vong_lap_trong_cay_phan_loai_khong_lam_treo_trang(): void
    {
        [$ho, $chi, $loai] = $this->nhanh();

        $ho->parent_id = $loai->id;
        $ho->save();

        $chuoi = $loai->fresh()->chain();

        $this->assertLessThanOrEqual(count(TaxonRank::cases()) + 1, $chuoi->count());
    }

    #[Test]
    public function loc_theo_nhan_sinh_thai_o_trang_san_pham(): void
    {
        $cayLeo = $this->sanPham('Trầu bà leo cột');
        $cayLeo->syncTraits(TraitType::GrowthForm, ['vine']);
        $cayLeo->syncTraits(TraitType::Color, ['green']);

        $hoaTrang = $this->sanPham('Bó cúc trắng');
        $hoaTrang->syncTraits(TraitType::GrowthForm, ['herb']);
        $hoaTrang->syncTraits(TraitType::Color, ['white']);

        $this->get('/san-pham?dang-song=vine')
            ->assertOk()
            ->assertSee('Trầu bà leo cột')
            ->assertDontSee('Bó cúc trắng');
    }

    #[Test]
    public function nhieu_tieu_chi_thi_cong_don_chu_khong_gop_lai(): void
    {
        $dung = $this->sanPham('Cúc trắng thân thảo');
        $dung->syncTraits(TraitType::GrowthForm, ['herb']);
        $dung->syncTraits(TraitType::Color, ['white']);

        $saiMau = $this->sanPham('Cúc vàng thân thảo');
        $saiMau->syncTraits(TraitType::GrowthForm, ['herb']);
        $saiMau->syncTraits(TraitType::Color, ['yellow']);

        $saiDang = $this->sanPham('Hồng trắng thân bụi');
        $saiDang->syncTraits(TraitType::GrowthForm, ['shrub']);
        $saiDang->syncTraits(TraitType::Color, ['white']);

        $this->get('/san-pham?dang-song=herb&mau=white')
            ->assertOk()
            ->assertSee('Cúc trắng thân thảo')
            ->assertDontSee('Cúc vàng thân thảo')
            ->assertDontSee('Hồng trắng thân bụi');
    }

    #[Test]
    public function gia_tri_la_tren_url_bi_bo_qua_chu_khong_lam_hong_trang(): void
    {
        $p = $this->sanPham('Cây thử');
        $p->syncTraits(TraitType::Color, ['green']);

        $this->get('/san-pham?mau=mau-khong-ton-tai')
            ->assertOk()
            ->assertSee('Cây thử');
    }

    #[Test]
    public function bo_loc_chi_bay_ra_nhung_gia_tri_thuc_su_co_hang(): void
    {
        $p = $this->sanPham('Cây thân leo duy nhất');
        $p->syncTraits(TraitType::GrowthForm, ['vine']);

        $res = $this->get('/san-pham');

        $res->assertOk();
        $res->assertSee('Cây thân leo');
        $res->assertDontSee('Họ cau dừa');
    }

    #[Test]
    public function trang_duyet_theo_loai_hien_hang_cua_ca_nhanh(): void
    {
        [$ho, , $loai] = $this->nhanh();

        $this->sanPham('Monstera chậu gốm', $loai);

        $this->get('/loai-cay')->assertOk()->assertSee('Ráy');

        $this->get('/loai-cay/ho-ray')
            ->assertOk()
            ->assertSee('Monstera chậu gốm')
            ->assertSee('Araceae');
    }

    #[Test]
    public function nhanh_khong_co_hang_van_mo_duoc_va_noi_ro_la_trong(): void
    {
        $this->nhanh();

        $this->get('/loai-cay/ho-ray')
            ->assertOk()
            ->assertSee('Chưa có hàng trong nhóm này');
    }

    #[Test]
    public function san_pham_khong_co_phan_loai_van_mo_duoc_binh_thuong(): void
    {
        $p = $this->sanPham('Hoa cầm tay cô dâu');

        $this->get('/san-pham/' . $p->slug)
            ->assertOk()
            ->assertDontSee('Phân loại &amp; đặc điểm', escape: false);
    }
}
