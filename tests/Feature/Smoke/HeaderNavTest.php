<?php

namespace Tests\Feature\Smoke;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Thanh điều hướng — canh chỗ nó hay hỏng.
 * ============================================================
 * SỰ CỐ ĐÃ XẢY RA (07/09/2026): thêm "Cẩm nang" thành mục thứ SÁU ngoài
 * thanh chính. Đo ở khung 1280px — độ phân giải laptop phổ biến nhất:
 *
 *   thương hiệu 186 + điều hướng 613 + khối phải 556 = 1419px
 *   -> tràn ngang 139px, cả trang trượt sang hai bên.
 *
 * Không bài kiểm thử nào bắt được, vì PHPUnit không đo được bề rộng
 * hiển thị. Nhưng thứ ĐẾM ĐƯỢC ở phía máy chủ là SỐ MỤC, và số mục chính
 * là thứ gây ra bề rộng.
 *
 * Nên hai bài dưới đây không đo pixel — chúng canh hai bất biến rẻ tiền
 * mà nếu giữ được thì lỗi kia không tái diễn:
 *
 *   1. Thanh chính không quá năm mục.
 *   2. Mọi mục điều hướng đều có đường vào từ ngăn kéo di động.
 */
class HeaderNavTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Số mục tối đa ngoài thanh điều hướng chính.
     *
     * Bốn liên kết phẳng + menu "Khác". Đo được: năm mục vừa khít ở
     * 1280px (nội dung 1265px, không tràn); sáu mục thì tràn 139px.
     *
     * Con số này KHÔNG phải sở thích thẩm mỹ — nó là ngưỡng đo được của
     * bố cục hiện tại. Muốn thêm mục thứ sáu thì phải bớt chỗ ở nơi
     * khác trước, và sửa cả con số này kèm phép đo mới.
     */
    private const TOI_DA_MUC_CHINH = 5;

    /** Lấy phần HTML của thanh điều hướng chính. */
    private function thanhChinh(string $html): string
    {
        $tu = strpos($html, '<nav class="site-header__nav">');
        $this->assertNotFalse($tu, 'Không tìm thấy thanh điều hướng — đã đổi tên lớp CSS?');

        $den = strpos($html, '</nav>', $tu);

        return substr($html, $tu, $den - $tu);
    }

    /**
     * Phần HTML của ngăn kéo di động.
     *
     * KHÔNG cắt ở `</div>` đầu tiên: bên trong ngăn kéo có các thẻ
     * `<div class="mobile-drawer__heading">`, nên cách đó dừng ngay ở
     * tiêu đề nhóm thứ nhất và bỏ sót sáu liên kết phía sau — bài kiểm
     * thử khi ấy báo thiếu mục trong khi mục vẫn có. (Đã dính lỗi này
     * một lần.)
     *
     * Cắt tới `<main` — thẻ mở ngay sau header trong layout. Đó là mốc
     * chắc chắn nằm ngoài ngăn kéo, nên không cắt hụt mà cũng không lấn
     * sang chân trang (chân trang cũng có link Cẩm nang, lấy nhầm thì
     * bài xanh một cách vô nghĩa).
     */
    private function nganKeo(string $html): string
    {
        $tu = strpos($html, 'class="offcanvas-body"');
        $this->assertNotFalse($tu, 'Không tìm thấy ngăn kéo di động.');

        $den = strpos($html, '<main', $tu);
        $this->assertNotFalse($den, 'Không tìm thấy <main> — mốc cắt không còn đúng.');

        return substr($html, $tu, $den - $tu);
    }

    #[Test]
    public function thanh_chinh_khong_qua_nam_muc(): void
    {
        $nav = $this->thanhChinh($this->get('/')->assertOk()->getContent());

        /*
         * Nút "Khác" là một `<summary>` MANG LUÔN lớp `site-header__link`,
         * nên nó bị đếm hai lần nếu chỉ cộng thẳng — đã dính một lần, ra
         * 6 trong khi thanh chỉ có 5 mục.
         *
         * Trừ số nút menu ra khỏi số liên kết trước khi cộng lại.
         */
        $soMenu = substr_count($nav, 'site-header__more-toggle');
        $soLienKet = substr_count($nav, 'class="site-header__link ') - $soMenu;

        $this->assertLessThanOrEqual(
            self::TOI_DA_MUC_CHINH,
            $soLienKet + $soMenu,
            'Thanh điều hướng có quá ' . self::TOI_DA_MUC_CHINH . ' mục. '
            .'Đo ở 1280px: sáu mục làm trang tràn ngang 139px. '
            .'Đưa mục mới vào menu "Khác" thay vì để ngoài thanh chính.',
        );
    }

    #[Test]
    public function moi_muc_dieu_huong_deu_vao_duoc_tu_dien_thoai(): void
    {
        /*
         * BÀI QUAN TRỌNG HƠN BÀI TRÊN.
         *
         * Dưới 1200px thanh điều hướng ẩn HẲN, nên ngăn kéo là lối đi
         * DUY NHẤT. Thiếu một mục ở đó không phải là "khó tìm hơn" — với
         * người dùng điện thoại, mục đó không tồn tại.
         *
         * Lỗi thật đã tìm ra: ngăn kéo chỉ có bốn liên kết và thiếu sáu,
         * trong đó có Cẩm nang — mục vừa được chuyển khỏi thanh chính.
         * Nếu không kiểm, việc "dọn gọn thanh điều hướng" sẽ âm thầm xoá
         * một khu vực khỏi bản di động.
         */
        $html = $this->get('/')->assertOk()->getContent();
        $keo = $this->nganKeo($html);

        $batBuoc = [
            '/cam-nang' => 'Cẩm nang',
            '/goc-cay' => 'Góc cây của bạn',
            '/san-pham' => 'Hoa & cây cảnh',
            '/danh-muc' => 'Danh mục',
            '/voucher' => 'Voucher',
        ];

        foreach ($batBuoc as $duongDan => $ten) {
            $this->assertStringContainsString(
                $duongDan,
                $keo,
                "Ngăn kéo di động thiếu \"{$ten}\" ({$duongDan}). "
                .'Dưới 1200px đây là lối đi duy nhất — thiếu nghĩa là mục đó '
                .'không tồn tại với người dùng điện thoại.',
            );
        }
    }

    #[Test]
    public function menu_khac_tu_sang_khi_dang_o_trang_ben_trong(): void
    {
        /*
         * Không có dấu này thì khách đang ở trang Cẩm nang mà thanh điều
         * hướng trông như chưa chọn gì — họ không biết mình đang ở đâu
         * trong cấu trúc trang.
         */
        $trangChu = $this->thanhChinh($this->get('/')->assertOk()->getContent());
        $this->assertStringNotContainsString('site-header__more is-active', $trangChu);

        $camNang = $this->thanhChinh($this->get('/cam-nang')->assertOk()->getContent());
        $this->assertStringContainsString(
            'site-header__more is-active',
            $camNang,
            'Đang ở Cẩm nang nhưng menu "Khác" không sáng.',
        );
    }
}
