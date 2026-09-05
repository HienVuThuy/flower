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

/**
 * Ảnh nhiều kích cỡ.
 * ============================================================
 * ĐO ĐƯỢC TRƯỚC KHI LÀM: thư viện có 52 ảnh JPG, tổng 5,59 MB, bề ngang
 * trung bình 882px — nhưng thẻ sản phẩm vẽ chúng ở 117–300px. Một ảnh
 * 900×1125 hiển thị ở 117×146 là tải về gấp ~58 lần số điểm ảnh cần.
 *
 * Sau khi sửa, đo lại trang danh sách: 1152 KB → 339 KB (nhẹ hơn 71%).
 *
 * Các bài dưới đây canh những chỗ dễ hỏng lặng lẽ: thiếu bản WebP thì
 * phải lùi về ảnh gốc, và KHÔNG được đọc kích thước từ tệp ảnh lúc dựng
 * trang.
 */
class ResponsiveImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Cache::flush();
    }

    /** Dựng manifest giả như lệnh anh:toi-uu vẫn sinh ra. */
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

    // ================================================================
    // Đọc manifest
    // ================================================================

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
        /*
         * Trả null chứ không trả chuỗi rỗng: nơi gọi cần phân biệt "chưa
         * có bản tối ưu" (dùng thẳng ảnh gốc) với "có nhưng rỗng". Chuỗi
         * rỗng trong `srcset` làm trình duyệt không tải ảnh nào cả.
         */
        $this->manifest([]);

        $this->assertNull($this->anh()->webpSrcset('products/chua-co.jpg'));
        $this->assertNull($this->anh()->info('products/chua-co.jpg'));
    }

    #[Test]
    public function duong_dan_rong_khong_lam_hong_gi(): void
    {
        // Sản phẩm chưa có ảnh là chuyện bình thường, không phải lỗi.
        $this->manifest([]);

        $this->assertNull($this->anh()->webpSrcset(null));
        $this->assertNull($this->anh()->info(null));
    }

    #[Test]
    public function KHONG_doc_kich_thuoc_tu_tep_anh(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT ở đây.
         *
         * Cách hiển nhiên để biết width/height là gọi `getimagesize()`
         * lúc dựng trang. Nhưng trang danh sách có 12 thẻ sản phẩm, nên
         * đó là 12 lần mở tệp trên đĩa cho MỖI lượt xem — thay một vấn
         * đề hiệu năng bằng một vấn đề hiệu năng khác.
         *
         * Manifest có kích thước, còn tệp ảnh thì KHÔNG hề tồn tại trong
         * bài này. Nếu ai đó sửa sang đọc tệp, bài này ngã ngay.
         */
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

    // ================================================================
    // Thẻ hiển thị
    // ================================================================

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

        /*
         * width/height chống NHẢY BỐ CỤC: thiếu chúng, trình duyệt dựng
         * trang với chiều cao ảnh bằng 0 rồi đẩy mọi thứ xuống khi ảnh
         * về — người đang đọc bị nhảy chữ, người đang bấm thì bấm nhầm.
         */
        $this->assertStringContainsString('width="900"', $html);
        $this->assertStringContainsString('height="1125"', $html);
    }

    #[Test]
    public function chua_co_ban_webp_thi_van_hien_anh_goc(): void
    {
        /*
         * Ảnh vừa được tải lên mà chưa chạy `anh:toi-uu` là chuyện bình
         * thường. Khi đó KHÔNG được để trống chỗ ảnh — vẫn hiện bản gốc,
         * chỉ là nặng hơn.
         */
        $this->manifest([]);

        $this->sanPham('products/moi-tai-len.jpg');

        $html = $this->get('/san-pham')->assertOk()->getContent();

        $this->assertStringContainsString('storage/products/moi-tai-len.jpg', $html);
        $this->assertStringNotContainsString('type="image/webp"', $html);
    }

    #[Test]
    public function anh_trong_the_san_pham_deu_tai_muon(): void
    {
        /*
         * Thẻ sản phẩm nằm dưới màn hình đầu, nên tải muộn là đúng — nó
         * nhường băng thông cho phần khách đang nhìn.
         *
         * Ảnh chính ở trang chi tiết thì NGƯỢC LẠI, xem bài dưới.
         */
        $this->manifest([]);
        $this->sanPham();

        $html = $this->get('/san-pham')->assertOk()->getContent();

        $this->assertStringContainsString('loading="lazy"', $html);
    }

    #[Test]
    public function anh_chinh_trang_chi_tiet_tai_ngay(): void
    {
        /*
         * Ảnh này thường là phần tử lớn nhất màn hình, tức là thứ quyết
         * định mốc LCP — con số trình duyệt dùng để chấm "trang đã dùng
         * được chưa". Đặt lazy cho nó là tự làm chậm chính con số đó.
         */
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
