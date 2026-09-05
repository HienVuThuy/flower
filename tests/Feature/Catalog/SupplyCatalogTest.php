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

/**
 * Bốn nhóm hàng phụ trợ, chia theo VIỆC KHÁCH ĐANG LÀM.
 * ============================================================
 * Cũ chỉ có hai nhóm và ranh giới là BỀN hay TIÊU HAO — sai với cách
 * khách đi mua. Bình tưới nằm cùng chỗ với chậu sứ, còn đá phủ gốc và
 * đồ trang trí Tết thì không có chỗ nào cả.
 *
 * Bài này giữ hai thứ dễ hỏng nhất khi có người thêm hàng về sau:
 *
 *   1. mọi món phụ trợ phải nằm trong đúng một trong bốn nhóm;
 *   2. mọi món phụ trợ phải khai "dùng kèm loại hàng nào" — thiếu nhãn
 *      đó thì nó không bao giờ hiện ra ở khối gợi ý mua kèm, và không
 *      có gì báo.
 */
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
        /*
         * ĐÂY LÀ ĐIỀU NGƯỜI DÙNG BÁO SAI.
         *
         * "Phụ kiện" cũ chứa chậu sứ, đĩa lót, bình tưới, kéo cắt cành —
         * toàn đồ dùng. Nhưng khi người Việt nói "phụ kiện" cho một chậu
         * cây thì họ nghĩ tới quả cầu Giáng sinh, nơ, đồ treo ngày Tết.
         *
         * Slug giữ nguyên (mọi liên kết đã chia sẻ vẫn sống), nghĩa thì
         * đổi.
         */
        $ten = Category::where('slug', 'phu-kien')->firstOrFail()
            ->products()->pluck('name');

        $this->assertStringContainsString('trang trí', Category::where('slug', 'phu-kien')->value('name'));

        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Giáng sinh')));
        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Tết')));

        // Dụng cụ đã chuyển đi nơi khác.
        $this->assertFalse($ten->contains(fn ($n) => str_contains($n, 'Bình tưới')));
        $this->assertFalse($ten->contains(fn ($n) => str_contains($n, 'Kéo cắt')));
    }

    #[Test]
    public function dung_cu_cham_cay_nam_cung_cho_voi_vat_tu(): void
    {
        // Người đi tìm bình tưới tìm ở nhóm chăm sóc, không tìm ở nhóm
        // đồ trang trí.
        $ten = Category::where('slug', 'vat-tu-cham-soc')->firstOrFail()
            ->products()->pluck('name');

        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Bình tưới')));
        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Kéo cắt')));
        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Đất trồng')));
    }

    #[Test]
    public function co_nhom_do_phu_goc_cho_nguoi_vua_mua_cay(): void
    {
        /*
         * Mua một chậu cây về thì mặt đất trơ ra một khoảng nâu, và gần
         * như ai cũng đi tìm thứ lấp nó. Trước đây cửa hàng không có
         * nhóm nào cho việc đó nên khách phải đi chỗ khác.
         */
        $ten = Category::where('slug', 'phu-goc-tieu-canh')->firstOrFail()
            ->products()->pluck('name');

        $this->assertGreaterThanOrEqual(4, $ten->count());
        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Đá trắng')));
        $this->assertTrue($ten->contains(fn ($n) => str_contains($n, 'Rêu')));
    }

    #[Test]
    public function moi_mon_phu_tro_deu_khai_dung_kem_loai_hang_nao(): void
    {
        /*
         * ĐIỀU DỄ QUÊN NHẤT khi thêm hàng về sau.
         *
         * Gợi ý "mua kèm" tra theo nhãn `accessory_for`. Món nào không
         * khai thì không bao giờ hiện ra ở khối đó — nó vẫn bán được nếu
         * khách tự tìm thấy, nên lỗi này không có biểu hiện nào và sẽ
         * nằm im rất lâu.
         */
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
        /*
         * Gán 'pot' cho chậu sứ thì accessoriesFor() sẽ gợi ý chính cái
         * chậu làm phụ kiện cho cái chậu — nó tra theo hình thức bán của
         * sản phẩm đang xem.
         */
        $sai = Product::whereHas('category', fn ($q) => $q->where('kind', CategoryKind::Supply))
            ->where('selling_form', '!=', SellingForm::Other->value)
            ->pluck('name')
            ->all();

        $this->assertSame([], $sai);
    }

    #[Test]
    public function chay_lai_seeder_khong_nhan_doi_hang(): void
    {
        // Seeder này sẽ phải chạy lại mỗi lần bổ sung nhóm hàng mới.
        $truoc = Product::whereHas('category', fn ($q) => $q->where('kind', CategoryKind::Supply))->count();

        $this->seed(\Database\Seeders\SupplyCatalogSeeder::class);

        $this->assertSame(
            $truoc,
            Product::whereHas('category', fn ($q) => $q->where('kind', CategoryKind::Supply))->count(),
        );
    }
}
