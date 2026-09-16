<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductType;
use App\Enums\SellingForm;
use App\Enums\TaxonRank;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\PlantTaxon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Gán "loài cây" cho sản phẩm trong trang quản trị. */
class ProductTaxonTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function nut(TaxonRank $bac, string $ten, ?PlantTaxon $cha = null, ?string $khoaHoc = null): PlantTaxon
    {
        return PlantTaxon::create([
            'parent_id' => $cha?->id,
            'rank' => $bac,
            'name' => $ten,
            'scientific_name' => $khoaHoc,
            'slug' => \Illuminate\Support\Str::slug($bac->value . ' ' . $ten),
        ]);
    }

    private function duLieu(array $ghiDe = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create()->id,
            'name' => 'Chậu sen đá kiểm thử',
            'slug' => 'chau-sen-da-kiem-thu',
            'product_code' => 'KT-' . strtoupper(bin2hex(random_bytes(3))),
            'product_type' => ProductType::Plant->value,
            'selling_form' => SellingForm::Pot->value,
            'base_price' => '150000',
            'track_inventory' => '1',
            'stock_quantity' => '10',
            'status' => 'active',
        ], $ghiDe);
    }

    #[Test]
    public function tao_san_pham_gan_duoc_loai_cay(): void
    {
        $loai = $this->nut(TaxonRank::Species, 'Sen đá phấn', null, 'Echeveria elegans');

        $this->actingAs($this->admin())
            ->post('/admin/products', $this->duLieu(['taxon_id' => $loai->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($loai->id, Product::where('slug', 'chau-sen-da-kiem-thu')->firstOrFail()->taxon_id);
    }

    #[Test]
    public function sua_san_pham_doi_duoc_loai_cay_va_bo_trong_duoc(): void
    {
        $cu = $this->nut(TaxonRank::Genus, 'Sen đá', null, 'Echeveria');
        $moi = $this->nut(TaxonRank::Genus, 'Sống đời', null, 'Kalanchoe');

        $du = $this->duLieu(['taxon_id' => $cu->id]);
        $this->actingAs($this->admin())->post('/admin/products', $du)->assertSessionHasNoErrors();
        $sp = Product::where('slug', $du['slug'])->firstOrFail();

        $this->put(route('admin.products.update', $sp), array_merge($du, ['taxon_id' => $moi->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($moi->id, $sp->fresh()->taxon_id);

        $this->put(route('admin.products.update', $sp), array_merge($du, ['taxon_id' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($sp->fresh()->taxon_id);
    }

    #[Test]
    public function id_loai_cay_khong_ton_tai_bi_tu_choi(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/products', $this->duLieu(['taxon_id' => 999999]))
            ->assertSessionHasErrors('taxon_id');
    }

    #[Test]
    public function o_chon_chi_co_bac_Ho_Chi_Loai(): void
    {
        $gioi = $this->nut(TaxonRank::Kingdom, 'Thực vật');
        $ho = $this->nut(TaxonRank::Family, 'Lá bỏng', $gioi, 'Crassulaceae');

        $html = $this->actingAs($this->admin())
            ->get('/admin/products/create')
            ->assertOk()
            ->assertSee('name="taxon_id"', false)
            ->getContent();

        $dau = strpos($html, 'id="taxon_id"');
        $this->assertNotFalse($dau);
        $oChon = substr($html, $dau, strpos($html, '</select>', $dau) - $dau);

        $this->assertStringContainsString('value="' . $ho->id . '"', $oChon);
        $this->assertStringNotContainsString('value="' . $gioi->id . '"', $oChon);
    }

    #[Test]
    public function trang_sua_danh_dau_dung_loai_dang_gan(): void
    {
        $loai = $this->nut(TaxonRank::Species, 'Sen đá phấn', null, 'Echeveria elegans');

        $du = $this->duLieu(['taxon_id' => $loai->id]);
        $this->actingAs($this->admin())->post('/admin/products', $du);
        $sp = Product::where('slug', $du['slug'])->firstOrFail();

        $html = $this->get(route('admin.products.edit', $sp))
            ->assertOk()
            ->assertSee('Echeveria elegans')
            ->getContent();

        $dau = strpos($html, 'id="taxon_id"');
        $oChon = substr($html, $dau, strpos($html, '</select>', $dau) - $dau);

        $this->assertMatchesRegularExpression('#value="' . $loai->id . '"\s+selected#', $oChon);
    }

    #[Test]
    public function san_pham_moi_gan_loai_hien_o_trang_loai_cay(): void
    {
        $loai = $this->nut(TaxonRank::Genus, 'Sen đá', null, 'Echeveria');

        $du = $this->duLieu(['taxon_id' => $loai->id, 'name' => 'Sen đá mới về']);
        $this->actingAs($this->admin())->post('/admin/products', $du)->assertSessionHasNoErrors();

        $this->get(route('shop.taxa.show', $loai))
            ->assertOk()
            ->assertSee('Sen đá mới về');
    }
}
