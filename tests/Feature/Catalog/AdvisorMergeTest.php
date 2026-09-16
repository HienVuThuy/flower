<?php

namespace Tests\Feature\Catalog;

use App\Enums\CareDifficulty;
use App\Enums\SellingForm;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Gộp "Chọn cây" vào trang sản phẩm. */
class AdvisorMergeTest extends TestCase
{
    use RefreshDatabase;

    private function cay(string $ten, CareDifficulty $doKho, SellingForm $hinhThuc = SellingForm::Pot): Product
    {
        return Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->create([
                'name' => $ten,
                'selling_form' => $hinhThuc,
                'care_info' => ['difficulty' => $doKho->value],
            ]);
    }

    private function duongDanNut(string $html): string
    {
        $tu = strpos($html, 'advisor-go');
        $this->assertNotFalse($tu, 'Không tìm thấy khối nút chuyển sang trang kết quả.');

        preg_match('/href="([^"]*san-pham[^"]*)"/', substr($html, $tu), $khop);

        return $khop[1] ?? '';
    }

    #[Test]
    public function trang_chon_cay_khong_tu_do_ket_qua_nua(): void
    {
        $this->cay('Lưỡi hổ để bàn', CareDifficulty::Easy);

        $html = $this->get('/chon-cay')->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'product-card',
            $html,
            'Trang Chọn cây lại tự hiển thị sản phẩm — bộ máy kết quả thứ hai đã quay lại.',
        );
    }

    #[Test]
    public function trang_chon_cay_van_hoi_du_cac_tieu_chi(): void
    {
        $this->cay('Lưỡi hổ để bàn', CareDifficulty::Easy);

        $this->get('/chon-cay')
            ->assertOk()
            ->assertSee('Đặt ở đâu')
            ->assertSee('Kinh nghiệm');
    }

    #[Test]
    public function nut_xem_ket_qua_mang_theo_dung_dieu_kien_da_chon(): void
    {
        $this->cay('Lưỡi hổ để bàn', CareDifficulty::Easy);

        $html = $this->get('/chon-cay?vi-tri=desk&kinh-nghiem=easy')->assertOk()->getContent();

        $this->assertStringContainsString('/san-pham?', $html);
        $this->assertStringContainsString('vi-tri=desk', $html);
        $this->assertStringContainsString('kinh-nghiem=easy', $html);
    }

    #[Test]
    public function tham_so_la_bi_bo_chu_khong_day_tiep_sang_trang_sau(): void
    {
        $this->cay('Lưỡi hổ để bàn', CareDifficulty::Easy);

        $html = $this->get('/chon-cay?vi-tri=khong-co-that&kinh-nghiem=easy')->assertOk()->getContent();

        $nut = $this->duongDanNut($html);

        $this->assertStringNotContainsString('khong-co-that', $nut);
        $this->assertStringContainsString('kinh-nghiem=easy', $nut);
    }

    #[Test]
    public function trang_san_pham_loc_duoc_theo_do_kho_cham_soc(): void
    {
        $this->cay('Lưỡi hổ dễ chăm', CareDifficulty::Easy);
        $this->cay('Bonsai khó chăm', CareDifficulty::Hard);

        $this->get('/san-pham?kinh-nghiem=easy')
            ->assertOk()
            ->assertSee('Lưỡi hổ dễ chăm')
            ->assertDontSee('Bonsai khó chăm');
    }

    #[Test]
    public function loc_do_kho_KHONG_tra_ve_hoa_cat_canh(): void
    {
        $this->cay('Cây trồng chậu dễ chăm', CareDifficulty::Easy, SellingForm::Pot);
        $this->cay('Bó hoa hồng', CareDifficulty::Easy, SellingForm::Bouquet);

        $this->get('/san-pham?kinh-nghiem=easy')
            ->assertOk()
            ->assertSee('Cây trồng chậu dễ chăm')
            ->assertDontSee('Bó hoa hồng');
    }

    #[Test]
    public function do_kho_la_tren_url_thi_bo_qua_chu_khong_404(): void
    {
        $this->cay('Lưỡi hổ dễ chăm', CareDifficulty::Easy);

        $this->get('/san-pham?kinh-nghiem=khong-co-that')
            ->assertOk()
            ->assertSee('Lưỡi hổ dễ chăm');
    }

    #[Test]
    public function bo_loc_chip_khong_bay_khi_bam_ap_dung(): void
    {
        $this->cay('Lưỡi hổ dễ chăm', CareDifficulty::Easy);

        $html = $this->get('/san-pham?kinh-nghiem=easy&category=khong-co-that')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            '<input type="hidden" name="kinh-nghiem" value="easy">',
            $html,
            'Bộ lọc độ khó không được mang theo khi gửi form — bấm "Áp dụng" sẽ làm nó bay.',
        );
    }

    #[Test]
    public function nut_xoa_bo_loc_nhan_ra_bo_loc_do_kho(): void
    {
        $this->cay('Lưỡi hổ dễ chăm', CareDifficulty::Easy);

        $this->get('/san-pham?kinh-nghiem=easy')->assertOk()->assertSee('Xóa bộ lọc');
    }

    #[Test]
    public function trang_san_pham_moi_di_chon_cay_khi_CHUA_loc_gi(): void
    {
        $this->cay('Lưỡi hổ dễ chăm', CareDifficulty::Easy);

        $this->get('/san-pham')->assertOk()->assertSee('Chưa biết chọn cây nào?');
    }

    #[Test]
    public function KHONG_moi_nua_khi_khach_da_loc(): void
    {
        $this->cay('Lưỡi hổ dễ chăm', CareDifficulty::Easy);

        $this->get('/san-pham?kinh-nghiem=easy')
            ->assertOk()
            ->assertDontSee('Chưa biết chọn cây nào?');
    }
}
