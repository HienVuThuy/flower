<?php

namespace Tests\Feature\Smoke;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Thanh điều hướng — canh chỗ nó hay hỏng. */
class HeaderNavTest extends TestCase
{
    use RefreshDatabase;

    private const TOI_DA_MUC_CHINH = 5;

    private function thanhChinh(string $html): string
    {
        $tu = strpos($html, '<nav class="site-header__nav">');
        $this->assertNotFalse($tu, 'Không tìm thấy thanh điều hướng — đã đổi tên lớp CSS?');

        $den = strpos($html, '</nav>', $tu);

        return substr($html, $tu, $den - $tu);
    }

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
