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

/**
 * Phân loại cây: cây phân loại sinh học + nhãn sinh thái.
 * ============================================================
 * HAI CÁCH PHÂN LOẠI, HAI CƠ CHẾ LƯU KHÁC NHAU, và bài này giữ cho cả
 * hai đúng:
 *
 *   - Nhãn sinh thái (môi trường, dạng sống, dáng, màu) là nhãn PHẲNG,
 *     nằm trong `product_traits` — nhiều nhãn một sản phẩm.
 *   - Phân loại sinh học là một CÂY có thứ bậc, nằm ở `plant_taxa`.
 *
 * Điều dễ hỏng nhất và cũng quan trọng nhất: chọn một bậc RỘNG phải ra
 * NHIỀU hàng hơn, không phải ít hơn. Sản phẩm gắn ở bậc Loài, còn khách
 * thì bấm vào bậc Họ.
 */
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

    /** Một nhánh Họ → Chi → Loài. */
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
        /*
         * ĐIỀU QUAN TRỌNG NHẤT TỆP NÀY.
         *
         * Sản phẩm gắn ở bậc Loài. Khách bấm vào "Họ Ráy" và phải thấy
         * nó. Nếu chỉ khớp đúng nút được chọn thì bậc càng cao càng ít
         * kết quả — ngược hẳn với thứ người dùng mong đợi, và trang Họ
         * sẽ luôn trống.
         */
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
        /*
         * LỖI ĐÃ SỬA. Bản đầu lưu thẳng "Họ Ráy" vào cột `name`. Ở chỗ
         * tên đứng một mình thì đọc đúng, nhưng đường dẫn phân loại ở
         * trang sản phẩm có cột bậc riêng bên cạnh — và nó thành
         * "Họ | Họ Ráy", bảy dòng bảy lần lặp.
         */
        [$ho] = $this->nhanh();

        $this->assertSame('Ráy', $ho->name);
        $this->assertSame('Họ Ráy', $ho->displayName());
        $this->assertSame('Họ Ráy (Araceae)', $ho->fullName());
        $this->assertStringNotContainsString('Họ Họ', $ho->fullName());
    }

    #[Test]
    public function vong_lap_trong_cay_phan_loai_khong_lam_treo_trang(): void
    {
        /*
         * `parent_id` là một cột bình thường và một lần sửa tay có thể
         * tạo ra A→B→A. Không chặn thì trang sản phẩm quay vòng cho tới
         * khi hết bộ nhớ, và nguyên nhân nằm ở một hàng trong bảng khác.
         */
        [$ho, $chi, $loai] = $this->nhanh();

        // Tạo vòng: Họ trỏ ngược xuống Loài.
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
        /*
         * Chọn "màu trắng" VÀ "cây thân thảo" phải ra cây thân thảo hoa
         * trắng — không phải hợp của hai danh sách. Đó là cách mọi bộ lọc
         * thương mại điện tử hoạt động, và trả về hợp thì bộ lọc càng
         * chọn nhiều càng ra nhiều kết quả, tức là vô dụng.
         */
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
        // Người ta chép link cho nhau, và một tham số hỏng không đáng để
        // cả trang biến mất.
        $p = $this->sanPham('Cây thử');
        $p->syncTraits(TraitType::Color, ['green']);

        $this->get('/san-pham?mau=mau-khong-ton-tai')
            ->assertOk()
            ->assertSee('Cây thử');
    }

    #[Test]
    public function bo_loc_chi_bay_ra_nhung_gia_tri_thuc_su_co_hang(): void
    {
        /*
         * Dựng bộ lọc thẳng từ enum thì nó liệt kê đủ mười dạng sống, kể
         * cả những thứ cửa hàng chưa từng bán — và khách bấm vào nhận
         * màn hình trống. Một lựa chọn dẫn tới ngõ cụt là một lựa chọn
         * không nên hiện ra.
         */
        $p = $this->sanPham('Cây thân leo duy nhất');
        $p->syncTraits(TraitType::GrowthForm, ['vine']);

        $res = $this->get('/san-pham');

        $res->assertOk();
        $res->assertSee('Cây thân leo');
        // "Họ cau dừa" có trong enum nhưng cửa hàng không bán con nào.
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
        // Trang phải chịu được câu trả lời "không có gì", không phải lỗi.
        $this->nhanh();

        $this->get('/loai-cay/ho-ray')
            ->assertOk()
            ->assertSee('Chưa có hàng trong nhóm này');
    }

    #[Test]
    public function san_pham_khong_co_phan_loai_van_mo_duoc_binh_thuong(): void
    {
        /*
         * Bó hoa cưới phối nhiều loài thì KHÔNG có phân loại sinh học,
         * và đó là sự thật chứ không phải dữ liệu thiếu. Khối "Phân loại
         * & đặc điểm" phải tự ẩn thay vì hiện một bảng rỗng — hoặc tệ
         * hơn, đổ lỗi null.
         */
        $p = $this->sanPham('Hoa cầm tay cô dâu');

        $this->get('/san-pham/' . $p->slug)
            ->assertOk()
            ->assertDontSee('Phân loại &amp; đặc điểm', escape: false);
    }
}
