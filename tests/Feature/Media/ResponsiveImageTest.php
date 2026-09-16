<?php

namespace Tests\Feature\Media;

use App\Models\Category;
use App\Models\Product;
use App\Services\Media\ResponsiveImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Ảnh nhiều kích cỡ. */
class ResponsiveImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Cache::flush();
    }

    private function manifest(array $du): void
    {
        Storage::disk('public')->put(
            ResponsiveImage::MANIFEST,
            json_encode($du),
        );

        ResponsiveImage::quenManifest();
    }

    private function anh(): ResponsiveImage
    {
        return app(ResponsiveImage::class);
    }

    #[Test]
    public function srcset_liet_ke_du_cac_be_ngang(): void
    {
        $this->manifest([
            'products/hoa.jpg' => [
                'width' => 900,
                'height' => 1125,
                'webp' => [400 => 'rp/400/products/hoa.webp', 800 => 'rp/800/products/hoa.webp'],
            ],
        ]);

        $srcset = $this->anh()->webpSrcset('products/hoa.jpg');

        $this->assertStringContainsString('rp/400/products/hoa.webp 400w', $srcset);
        $this->assertStringContainsString('rp/800/products/hoa.webp 800w', $srcset);
    }

    #[Test]
    public function chua_sinh_ban_nao_thi_tra_ve_null(): void
    {
        $this->manifest([]);

        $this->assertNull($this->anh()->webpSrcset('products/chua-co.jpg'));
        $this->assertNull($this->anh()->info('products/chua-co.jpg'));
    }

    #[Test]
    public function duong_dan_rong_khong_lam_hong_gi(): void
    {
        $this->manifest([]);

        $this->assertNull($this->anh()->webpSrcset(null));
        $this->assertNull($this->anh()->info(null));
    }

    #[Test]
    public function KHONG_doc_kich_thuoc_tu_tep_anh(): void
    {
        $this->manifest([
            'products/khong-co-tep.jpg' => [
                'width' => 900,
                'height' => 1125,
                'webp' => [400 => 'rp/400/products/khong-co-tep.webp'],
            ],
        ]);

        $this->assertFalse(Storage::disk('public')->exists('products/khong-co-tep.jpg'));

        $info = $this->anh()->info('products/khong-co-tep.jpg');

        $this->assertSame(900, $info['width']);
        $this->assertSame(1125, $info['height']);
    }

    private function sanPham(string $anh = 'products/hoa.jpg'): Product
    {
        return Product::factory()
            ->for(Category::factory())
            ->price('300000.00')
            ->stock(5)
            ->create(['main_image' => $anh]);
    }

    #[Test]
    public function the_san_pham_dung_webp_va_co_kich_thuoc(): void
    {
        $this->manifest([
            'products/hoa.jpg' => [
                'width' => 900,
                'height' => 1125,
                'webp' => [400 => 'rp/400/products/hoa.webp'],
            ],
        ]);

        $sp = $this->sanPham();

        $html = $this->get('/san-pham')->assertOk()->getContent();

        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('rp/400/products/hoa.webp 400w', $html);

        $this->assertStringContainsString('width="900"', $html);
        $this->assertStringContainsString('height="1125"', $html);
    }

    #[Test]
    public function chua_co_ban_webp_thi_van_hien_anh_goc(): void
    {
        $this->manifest([]);

        $this->sanPham('products/moi-tai-len.jpg');

        $html = $this->get('/san-pham')->assertOk()->getContent();

        $this->assertStringContainsString('storage/products/moi-tai-len.jpg', $html);
        $this->assertStringNotContainsString('type="image/webp"', $html);
    }

    #[Test]
    public function anh_trong_the_san_pham_deu_tai_muon(): void
    {
        $this->manifest([]);
        $this->sanPham();

        $html = $this->get('/san-pham')->assertOk()->getContent();

        $this->assertStringContainsString('loading="lazy"', $html);
    }

    #[Test]
    public function anh_chinh_trang_chi_tiet_tai_ngay(): void
    {
        $this->manifest([
            'products/hoa.jpg' => [
                'width' => 900,
                'height' => 1125,
                'webp' => [800 => 'rp/800/products/hoa.webp'],
            ],
        ]);

        $sp = $this->sanPham();

        $html = $this->get('/san-pham/'.$sp->slug)->assertOk()->getContent();

        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('loading="eager"', $html);
    }
}
