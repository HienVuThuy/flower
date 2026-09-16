<?php

namespace Tests\Feature\Blog;

use App\Services\Media\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Làm sạch HTML của bài Cẩm nang. */
class HtmlSanitizerTest extends TestCase
{
    private function sach(string $html): string
    {
        return app(HtmlSanitizer::class)->lamSach($html);
    }

    public static function payloadXss(): array
    {
        return [
            'thẻ script trần' => ['<p>Xin chào</p><script>alert(1)</script>'],
            'script viết hoa' => ['<p>Xin chào</p><SCRIPT>alert(1)</SCRIPT>'],
            'onerror trên img' => ['<img src=x onerror="alert(1)">'],
            'onload trên svg' => ['<svg onload="alert(1)"></svg>'],
            'onclick trên thẻ được phép' => ['<p onclick="alert(1)">Bấm đi</p>'],
            'onmouseover' => ['<strong onmouseover="alert(1)">Rê chuột</strong>'],
            'iframe' => ['<iframe src="https://vi.du"></iframe>'],
            'object' => ['<object data="x.swf"></object>'],
            'embed' => ['<embed src="x.swf">'],
            'style có url' => ['<p style="background:url(javascript:alert(1))">Chữ</p>'],
            'form ẩn' => ['<form action="/x"><input name="a"></form>'],
            'thẻ base' => ['<base href="https://ke-gian.vi.du/">'],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://ke-gian.vi.du">'],
        ];
    }

    #[Test]
    #[DataProvider('payloadXss')]
    public function moi_payload_xss_deu_bi_go(string $doc): void
    {
        $ket = $this->sach($doc);

        foreach (['<script', '<iframe', '<object', '<embed', '<svg', '<form', '<base', '<meta',
            'onerror', 'onload', 'onclick', 'onmouseover', 'javascript:', 'style='] as $xau) {
            $this->assertStringNotContainsStringIgnoringCase(
                $xau,
                $ket,
                "Còn sót \"{$xau}\" trong: {$ket}",
            );
        }
    }

    #[Test]
    public function noi_dung_script_khong_duoc_in_ra_thanh_chu(): void
    {
        $ket = $this->sach('<p>Trước</p><script>alert("xin chao")</script><p>Sau</p>');

        $this->assertStringNotContainsString('alert', $ket);
        $this->assertStringContainsString('Trước', $ket);
        $this->assertStringContainsString('Sau', $ket);
    }

    #[Test]
    public function the_khong_cho_phep_bi_go_nhung_GIU_LAI_chu_ben_trong(): void
    {
        $ket = $this->sach('<div><section><p>Nội dung quan trọng</p></section></div>');

        $this->assertStringNotContainsString('<div', $ket);
        $this->assertStringNotContainsString('<section', $ket);
        $this->assertStringContainsString('Nội dung quan trọng', $ket);
        $this->assertStringContainsString('<p>', $ket);
    }

    #[Test]
    public function giu_nguyen_the_can_cho_mot_bai_huong_dan(): void
    {
        $doc = '<h2>Cách tưới</h2>'
            .'<p>Tưới khi mặt đất <strong>khô</strong> khoảng <em>2cm</em>.</p>'
            .'<ul><li>Mùa hè: 2 lần/tuần</li><li>Mùa đông: 1 lần/tuần</li></ul>'
            .'<blockquote>Thà khô còn hơn úng.</blockquote>';

        $ket = $this->sach($doc);

        foreach (['<h2>', '<p>', '<strong>', '<em>', '<ul>', '<li>', '<blockquote>'] as $the) {
            $this->assertStringContainsString($the, $ket, "Đã gỡ mất {$the} — bài viết cần thẻ này.");
        }
    }

    #[Test]
    public function tieng_viet_co_dau_khong_bi_hong(): void
    {
        $ket = $this->sach('<p>Cây lưỡi hổ chịu bóng tốt, để ở góc phòng vẫn xanh.</p>');

        $this->assertStringContainsString('Cây lưỡi hổ chịu bóng tốt', $ket);
        $this->assertStringContainsString('vẫn xanh', $ket);
    }

    #[Test]
    public function href_javascript_bi_go_nhung_chu_van_con(): void
    {
        $ket = $this->sach('<p><a href="javascript:alert(1)">Bấm vào đây</a></p>');

        $this->assertStringNotContainsStringIgnoringCase('javascript:', $ket);
        $this->assertStringContainsString('Bấm vào đây', $ket);
    }

    #[Test]
    public function href_data_bi_go(): void
    {
        $ket = $this->sach('<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">Xem</a>');

        $this->assertStringNotContainsStringIgnoringCase('data:text/html', $ket);
    }

    #[Test]
    public function link_noi_bo_va_link_ngoai_deu_giu_duoc(): void
    {
        $noiBo = $this->sach('<p><a href="/san-pham/monstera">Xem cây này</a></p>');
        $this->assertStringContainsString('href="/san-pham/monstera"', $noiBo);

        $ngoai = $this->sach('<p><a href="https://vi.wikipedia.org/wiki/Monstera">Wikipedia</a></p>');
        $this->assertStringContainsString('https://vi.wikipedia.org', $ngoai);
    }

    #[Test]
    public function link_ra_ngoai_co_noopener(): void
    {
        $ket = $this->sach('<a href="https://vi.du">Ngoài</a>');

        $this->assertStringContainsString('noopener', $ket);
        $this->assertStringContainsString('_blank', $ket);
    }

    #[Test]
    public function the_img_bi_go_vi_anh_phai_di_qua_o_tai_len(): void
    {
        $ket = $this->sach('<p>Xem ảnh:</p><img src="https://noi-khac.vi.du/anh.jpg">');

        $this->assertStringNotContainsString('<img', $ket);
        $this->assertStringContainsString('Xem ảnh:', $ket);
    }

    #[Test]
    public function chuoi_rong_va_null_khong_lam_vo(): void
    {
        $this->assertSame('', $this->sach(''));
        $this->assertSame('', app(HtmlSanitizer::class)->lamSach(null));
        $this->assertSame('', $this->sach('   '));
    }

    #[Test]
    public function lam_sach_hai_lan_cho_ket_qua_giong_nhau(): void
    {
        $doc = '<h2>Tiêu đề</h2><p>Chữ <a href="https://vi.du">link ngoài</a> và <strong>đậm</strong>.</p>';

        $lan1 = $this->sach($doc);
        $lan2 = $this->sach($lan1);

        $this->assertSame($lan1, $lan2);
    }
}
