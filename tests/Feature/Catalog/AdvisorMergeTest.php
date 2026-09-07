<?php

namespace Tests\Feature\Catalog;

use App\Enums\CareDifficulty;
use App\Enums\SellingForm;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gộp "Chọn cây" vào trang sản phẩm.
 * ============================================================
 * VẤN ĐỀ ĐÃ SỬA: hai trang trả lời cùng một câu hỏi bằng hai bộ máy.
 *
 * Sáu trong bảy tiêu chí của trang "Chọn cây" trùng với bộ lọc trang sản
 * phẩm, và cả hai đổ kết quả ra cùng một thẻ sản phẩm. Nhưng trang chọn
 * cây thiếu hẳn sắp xếp, lọc giá và phân trang — nên khách trả lời xong
 * vẫn phải sang trang kia làm nốt.
 *
 * Nay "Chọn cây" là CỬA VÀO: giữ cách hỏi theo ngôn ngữ người mua, còn
 * kết quả thì để một nơi duy nhất lo. Xem QĐ-172.
 *
 * Bốn bất biến được canh ở đây, mỗi cái tương ứng một cách hỏng:
 *
 *   1. Trang chọn cây KHÔNG tự đổ kết quả nữa (bộ máy thứ hai quay lại).
 *   2. Nút chuyển tiếp mang ĐÚNG những tham số đã chọn (mất điều kiện).
 *   3. Bộ lọc độ khó chăm sóc loại hoa cắt cành (mất vế thứ hai của luật).
 *   4. Bấm "Áp dụng" không làm bay bộ lọc chip (lỗi có sẵn từ trước).
 */
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

    /** Đường dẫn trên nút "Xem cây phù hợp" của trang Chọn cây. */
    private function duongDanNut(string $html): string
    {
        $tu = strpos($html, 'advisor-go');
        $this->assertNotFalse($tu, 'Không tìm thấy khối nút chuyển sang trang kết quả.');

        preg_match('/href="([^"]*san-pham[^"]*)"/', substr($html, $tu), $khop);

        return $khop[1] ?? '';
    }

    /* ================= 1. KHÔNG CÒN BỘ MÁY KẾT QUẢ THỨ HAI ================= */

    #[Test]
    public function trang_chon_cay_khong_tu_do_ket_qua_nua(): void
    {
        /*
         * Thẻ sản phẩm trên trang này nghĩa là bộ máy kết quả thứ hai đã
         * quay lại — và cùng với nó là mọi thứ đã gộp bỏ: xếp hạng riêng,
         * không phân trang, không sắp xếp.
         */
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
        /*
         * Gộp kết quả KHÔNG được làm mất CÁCH HỎI — đó là thứ duy nhất
         * trang này có mà trang sản phẩm không có.
         *
         * Kiểm bằng NHÃN NGƯỜI ĐỌC THẤY, không bằng tên tham số URL: tên
         * tham số xuất hiện trong mọi link chip nên nó có mặt kể cả khi
         * câu hỏi đã bị xoá khỏi trang.
         */
        $this->cay('Lưỡi hổ để bàn', CareDifficulty::Easy);

        $this->get('/chon-cay')
            ->assertOk()
            ->assertSee('Đặt ở đâu')
            ->assertSee('Kinh nghiệm');
    }

    /* ================= 2. CHUYỂN TIẾP ĐÚNG THAM SỐ ================= */

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
        /*
         * Controller lọc lại qua enum trước khi dựng đường dẫn. Bê thẳng
         * `$request->query()` thì một tham số rác trên URL đi tiếp sang
         * trang kết quả, và ở đó nó lại bị bỏ lần nữa — hai lần kiểm cho
         * một việc, và không nơi nào chịu trách nhiệm.
         */
        $this->cay('Lưỡi hổ để bàn', CareDifficulty::Easy);

        $html = $this->get('/chon-cay?vi-tri=khong-co-that&kinh-nghiem=easy')->assertOk()->getContent();

        /*
         * ĐO TRÊN CHÍNH NÚT CHUYỂN TIẾP, không đo cả trang.
         *
         * Các link chip dùng `fullUrlWithQuery()` nên chúng giữ nguyên
         * chuỗi truy vấn hiện tại — kể cả phần rác. Khẳng định "cả trang
         * không chứa chuỗi rác" là đo nhầm chỗ, và nó đỏ trong khi mã
         * chạy đúng.
         */
        $nut = $this->duongDanNut($html);

        $this->assertStringNotContainsString('khong-co-that', $nut);
        $this->assertStringContainsString('kinh-nghiem=easy', $nut);
    }

    /* ================= 3. LỌC ĐỘ KHÓ CHĂM SÓC ================= */

    #[Test]
    public function trang_san_pham_loc_duoc_theo_do_kho_cham_soc(): void
    {
        // Đây là tiêu chí DUY NHẤT mà trang chọn cây có còn trang sản
        // phẩm thì không — nó phải chuyển sang được, nếu không thì gộp
        // xong là mất một chức năng.
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
        /*
         * VẾ THỨ HAI CỦA LUẬT, và là vế một bản chép tay sẽ quên.
         *
         * Hoa cắt cành không có khái niệm "dễ chăm" hay "khó chăm" —
         * chúng tàn sau vài ngày dù chăm kiểu gì. Để lọt vào thì bộ lọc
         * "tôi mới trồng cây" trả về một đống bó hoa, đúng thứ khách
         * KHÔNG hỏi.
         *
         * Luật nằm ở Product::scopeWithCareDifficulty() — một nơi sở hữu
         * duy nhất, dùng chung với PlantAdvisor.
         */
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
        // Người ta chép link cho nhau; một tham số hỏng không đáng để cả
        // trang biến mất.
        $this->cay('Lưỡi hổ dễ chăm', CareDifficulty::Easy);

        $this->get('/san-pham?kinh-nghiem=khong-co-that')
            ->assertOk()
            ->assertSee('Lưỡi hổ dễ chăm');
    }

    /* ================= 4. BỘ LỌC CHIP SỐNG SÓT QUA "ÁP DỤNG" ================= */

    #[Test]
    public function bo_loc_chip_khong_bay_khi_bam_ap_dung(): void
    {
        /*
         * LỖI CÓ SẴN TỪ TRƯỚC, tìm ra khi thêm bộ lọc độ khó.
         *
         * Các bộ lọc chip là thẻ `<a href>` chứ không phải ô nhập, nên
         * khi khách bấm "Áp dụng" (gửi form GET) trình duyệt chỉ gửi `q`
         * và `sort`. Đo được: mọi bộ lọc chip biến mất, và không có gì
         * báo.
         *
         * Sửa bằng input ẩn dựng từ chính danh sách tham số của
         * controller.
         */
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
        /*
         * Danh sách tham số dùng chung giữa input ẩn, nút "Xoá bộ lọc" và
         * khối mời "Chọn cây". Ba bản chép tay thì chúng lệch nhau, và
         * lệch ở đây nghĩa là khách lọc xong không có cách nào quay lại.
         */
        $this->cay('Lưỡi hổ dễ chăm', CareDifficulty::Easy);

        $this->get('/san-pham?kinh-nghiem=easy')->assertOk()->assertSee('Xóa bộ lọc');
    }

    /* ================= LỐI VÀO ================= */

    #[Test]
    public function trang_san_pham_moi_di_chon_cay_khi_CHUA_loc_gi(): void
    {
        $this->cay('Lưỡi hổ dễ chăm', CareDifficulty::Easy);

        $this->get('/san-pham')->assertOk()->assertSee('Chưa biết chọn cây nào?');
    }

    #[Test]
    public function KHONG_moi_nua_khi_khach_da_loc(): void
    {
        // Người đã chọn bộ lọc là người biết mình muốn gì; mời họ đi trả
        // lời câu hỏi là mời họ quay lại điểm xuất phát.
        $this->cay('Lưỡi hổ dễ chăm', CareDifficulty::Easy);

        $this->get('/san-pham?kinh-nghiem=easy')
            ->assertOk()
            ->assertDontSee('Chưa biết chọn cây nào?');
    }
}
