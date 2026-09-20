<?php

namespace Tests\Feature\Seo;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Thẻ chia sẻ, dữ liệu có cấu trúc, sitemap, robots và trang lỗi. */
class SeoVaTaiTrangTest extends TestCase
{
    use RefreshDatabase;

    private function sp(): Product
    {
        return Product::factory()->for(Category::factory())->price('250000.00')->stock(5)->create(['status' => 'active']);
    }

    #[Test]
    public function moi_trang_khach_co_canonical_va_the_chia_se(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="' . url('/') . '"', $html);
        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<meta property="og:image"', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringContainsString('Bỏ qua tới nội dung', $html, 'Liên kết cho người dùng bàn phím');
    }

    #[Test]
    public function trang_san_pham_co_du_lieu_co_cau_truc_dung_gia_va_ton_kho(): void
    {
        $sp = $this->sp();

        $html = $this->get(route('shop.products.show', $sp))->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $khop);
        $lds = array_map(fn ($j) => json_decode($j, true), $khop[1]);

        $sanPham = collect($lds)->firstWhere('@type', 'Product');
        $this->assertNotNull($sanPham, 'Phải có schema Product');
        $this->assertSame($sp->name, $sanPham['name']);
        $this->assertSame('250000.00', $sanPham['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $sanPham['offers']['availability']);

        $duongDan = collect($lds)->firstWhere('@type', 'BreadcrumbList');
        $this->assertNotNull($duongDan, 'Phải có đường dẫn phân cấp');
        $this->assertSame('Trang chủ', $duongDan['itemListElement'][0]['name']);

        $sp->update(['stock_quantity' => 0]);
        $this->assertStringContainsString('https://schema.org/OutOfStock', $this->get(route('shop.products.show', $sp))->getContent());
    }

    #[Test]
    public function sitemap_liet_ke_trang_cong_khai_va_robots_tro_toi_no(): void
    {
        $sp = $this->sp();
        $an = $this->sp();
        $an->update(['status' => 'draft']);

        $xml = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString(route('shop.products.show', $sp), $xml);
        $this->assertStringNotContainsString(route('shop.products.show', $an), $xml, 'Sản phẩm nháp không được lập chỉ mục');
        $this->assertStringNotContainsString('/admin', $xml);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap: ' . route('sitemap'));
    }

    #[Test]
    public function trang_404_co_giao_dien_cua_hang_va_o_tim(): void
    {
        $this->get('/duong-dan-khong-co-that')
            ->assertNotFound()
            ->assertSee('Không tìm thấy trang')
            ->assertSee('Về trang chủ');
    }

    #[Test]
    public function dia_chi_dang_nhap_bang_tieng_viet_va_dia_chi_cu_van_vao_duoc(): void
    {
        $this->get('/dang-nhap')->assertOk();
        $this->get('/dang-ky')->assertOk();

        $this->get('/login')->assertRedirect('/dang-nhap');
        $this->get('/register')->assertRedirect('/dang-ky');
    }

    #[Test]
    public function trang_khach_khong_nap_css_quan_tri(): void
    {
        $khach = $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('#/build/assets/app-[^"]+\.css#', $khach);
        $this->assertDoesNotMatchRegularExpression('#/build/assets/admin-[^"]+\.css#', $khach, 'Trang khách không tải CSS quản trị');
    }
}
