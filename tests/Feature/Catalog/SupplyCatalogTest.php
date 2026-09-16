<?php

namespace Tests\Feature\Catalog;

use App\Enums\CategoryKind;
use App\Enums\SellingForm;
use App\Enums\TraitType;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Bốn nhóm hàng phụ trợ, chia theo VIỆC KHÁCH ĐANG LÀM. */
class SupplyCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SupplyCatalogSeeder::class);
    }

    #[Test]
    public function co_du_bon_nhom_hang_phu_tro(): void
    {
        $slug = Category::where('kind', CategoryKind::Supply)->pluck('slug')->sort()->values()->all();

        $this->assertSame(
            ['chau-va-de-lot', 'phu-goc-tieu-canh', 'phu-kien', 'vat-tu-cham-soc'],
            $slug,
        );
    }

    #[Test]
    public function phu_kien_nay_la_do_trang_tri_chu_khong_phai_dung_cu(): void
    {
        $ten = Category::where('slug', 'phu-kien')->firstOrFail()
            ->products()->pluck('name');

        $this->assertStringContainsString('trang trí', Category::where('slug', 'phu-kien')->value('name'));

        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Giáng sinh')));
        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Tết')));

        $this->assertFalse($ten->contains(fn ($n) => str_contains($n, 'Bình tưới')));
        $this->assertFalse($ten->contains(fn ($n) => str_contains($n, 'Kéo cắt')));
    }

    #[Test]
    public function dung_cu_cham_cay_nam_cung_cho_voi_vat_tu(): void
    {
        $ten = Category::where('slug', 'vat-tu-cham-soc')->firstOrFail()
            ->products()->pluck('name');

        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Bình tưới')));
        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Kéo cắt')));
        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Đất trồng')));
    }

    #[Test]
    public function co_nhom_do_phu_goc_cho_nguoi_vua_mua_cay(): void
    {
        $ten = Category::where('slug', 'phu-goc-tieu-canh')->firstOrFail()
            ->products()->pluck('name');

        $this->assertGreaterThanOrEqual(4, $ten->count());
        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Đá trắng')));
        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Rêu')));
    }

    #[Test]
    public function moi_mon_phu_tro_deu_khai_dung_kem_loai_hang_nao(): void
    {
        $thieu = [];

        foreach (Product::whereHas('category', fn ($q) => $q->where('kind', CategoryKind::Supply))->get() as $p) {
            if ($p->traitValues(TraitType::AccessoryFor) === []) {
                $thieu[] = $p->name;
            }
        }

        $this->assertSame([], $thieu, 'Thiếu nhãn "dùng kèm": ' . implode(', ', $thieu));
    }

    #[Test]
    public function hang_phu_tro_khong_duoc_mang_hinh_thuc_ban_cua_cay(): void
    {
        $sai = Product::whereHas('category', fn ($q) => $q->where('kind', CategoryKind::Supply))
            ->where('selling_form', '!=', SellingForm::Other->value)
            ->pluck('name')
            ->all();

        $this->assertSame([], $sai);
    }

    #[Test]
    public function chay_lai_seeder_khong_nhan_doi_hang(): void
    {
        $truoc = Product::whereHas('category', fn ($q) => $q->where('kind', CategoryKind::Supply))->count();

        $this->seed(\Database\Seeders\SupplyCatalogSeeder::class);

        $this->assertSame(
            $truoc,
            Product::whereHas('category', fn ($q) => $q->where('kind', CategoryKind::Supply))->count(),
        );
    }
}
