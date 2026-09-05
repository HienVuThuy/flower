<?php

namespace Tests\Feature\Media;

use App\Models\Category;
use App\Models\User;
use App\Services\Media\ImageStore;
use App\Services\Media\ResponsiveImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ảnh admin tải lên phải được tối ưu NGAY, không chờ ai gõ lệnh.
 * ============================================================
 * TRẠNG THÁI TRƯỚC KHI SỬA: việc sinh bản WebP chỉ nằm trong lệnh
 * `php artisan anh:toi-uu` chạy tay. Admin thêm sản phẩm và tải ảnh lên
 * thì ảnh đó được lưu nguyên bản JPEG — không bản WebP, không có trong
 * manifest.
 *
 * Và KHÔNG CÓ GÌ HỎNG NHÌN THẤY ĐƯỢC: `<x-site.image>` không tìm thấy
 * bản tối ưu thì dùng thẳng ảnh gốc, trang vẫn hiện bình thường. Ảnh đó
 * chỉ đơn giản là nặng gấp mấy lần những ảnh khác, mãi mãi.
 *
 * Đó là lý do phải có bài kiểm thử này chứ không thể "để ý là biết":
 * kiểu hỏng này không có biểu hiện nào trên màn hình.
 */
class AutoOptimizeUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('Máy chạy kiểm thử chưa bật GD/WebP.');
        }
    }

    /** Ảnh JPEG thật, đủ rộng để sinh được cả hai cỡ. */
    private function anhThat(string $ten = 'thu.jpg'): UploadedFile
    {
        // UploadedFile::fake()->image() dựng ảnh THẬT bằng GD, không
        // phải tệp rỗng — cần đúng như vậy vì ImageOptimizer đọc kích
        // thước bằng getimagesize().
        return UploadedFile::fake()->image($ten, 1200, 900);
    }

    #[Test]
    public function luu_anh_qua_ImageStore_thi_co_ngay_ban_webp(): void
    {
        $path = app(ImageStore::class)->luu($this->anhThat(), 'products');

        $disk = Storage::disk('public');

        $this->assertTrue($disk->exists($path), 'Ảnh gốc phải được lưu.');

        foreach (ResponsiveImage::WIDTHS as $w) {
            $ban = ResponsiveImage::FOLDER . "/{$w}/" . preg_replace('/\.jpg$/', '.webp', $path);

            $this->assertTrue($disk->exists($ban), "Thiếu bản {$w}px.");
        }
    }

    #[Test]
    public function anh_moi_vao_manifest_ngay_de_trang_dung_duoc_ban_webp(): void
    {
        /*
         * Sinh ra tệp WebP thôi CHƯA ĐỦ. Giao diện đọc manifest để dựng
         * srcset; ảnh có bản WebP mà không có trong manifest thì vẫn bị
         * phục vụ bằng ảnh gốc — hỏng đúng như cũ, chỉ tốn thêm chỗ trên
         * đĩa.
         */
        $path = app(ImageStore::class)->luu($this->anhThat(), 'products');

        $srcset = app(ResponsiveImage::class)->webpSrcset($path);

        $this->assertNotNull($srcset, 'Ảnh vừa tải lên chưa vào manifest.');
        $this->assertStringContainsString('400w', $srcset);
        $this->assertStringContainsString('800w', $srcset);
    }

    #[Test]
    public function admin_them_danh_muc_kem_anh_thi_anh_do_duoc_toi_uu(): void
    {
        // Đi qua ĐÚNG đường HTTP mà admin dùng, không gọi thẳng service:
        // lỗi cần bắt là "controller quên gọi", và gọi thẳng service thì
        // không bao giờ bắt được lỗi đó.
        $admin = User::factory()->create();
        $admin->role = \App\Enums\UserRole::Admin;
        $admin->save();

        $this->actingAs($admin)
            ->post('/admin/categories', [
                'name' => 'Danh mục thử ảnh',
                'kind' => 'plant',
                'is_active' => 1,
                'sort_order' => 1,
                'image' => $this->anhThat('danh-muc.jpg'),
            ])
            ->assertRedirect();

        $category = Category::where('name', 'Danh mục thử ảnh')->firstOrFail();

        $this->assertNotNull($category->image);
        $this->assertNotNull(
            app(ResponsiveImage::class)->webpSrcset($category->image),
            'Ảnh danh mục tải qua trang quản trị chưa được tối ưu.',
        );
    }

    #[Test]
    public function xoa_anh_thi_don_luon_ban_webp_va_muc_trong_manifest(): void
    {
        /*
         * Đi cùng cặp với việc sinh. Không dọn thì thư mục `rp/` giữ lại
         * bản WebP của những ảnh không còn ai dùng, và manifest phình ra
         * với những đường dẫn trỏ vào hư không.
         */
        $store = app(ImageStore::class);
        $path = $store->luu($this->anhThat(), 'products');

        $disk = Storage::disk('public');
        $ban400 = ResponsiveImage::FOLDER . '/400/' . preg_replace('/\.jpg$/', '.webp', $path);

        $this->assertTrue($disk->exists($ban400));

        $store->xoa($path);

        $this->assertFalse($disk->exists($path), 'Ảnh gốc chưa bị xoá.');
        $this->assertFalse($disk->exists($ban400), 'Bản WebP còn sót lại.');
        $this->assertNull(app(ResponsiveImage::class)->webpSrcset($path));
    }

    #[Test]
    public function toi_uu_hong_thi_anh_goc_van_phai_luu_duoc(): void
    {
        /*
         * Thiếu GD, ảnh lạ, hết chỗ trên đĩa — đều có thể xảy ra. Nhưng
         * ảnh gốc thì đã lưu xong, và giao diện tự lùi về dùng ảnh gốc.
         * Ném lỗi ra ngoài ở đây là làm hỏng cả việc tạo sản phẩm chỉ vì
         * một bước làm-cho-nhẹ-hơn.
         *
         * Giả lập bằng một tệp mang đuôi ảnh nhưng ruột không phải ảnh.
         */
        $rac = UploadedFile::fake()->createWithContent('hong.jpg', 'day khong phai anh');

        $path = app(ImageStore::class)->luu($rac, 'products');

        $this->assertTrue(Storage::disk('public')->exists($path), 'Ảnh gốc phải lưu được dù không tối ưu nổi.');
        $this->assertNull(app(ResponsiveImage::class)->webpSrcset($path));
    }
}
