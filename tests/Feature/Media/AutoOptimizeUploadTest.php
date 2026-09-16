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

/** Ảnh admin tải lên phải được tối ưu NGAY, không chờ ai gõ lệnh. */
class AutoOptimizeUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('Máy chạy kiểm thử chưa bật GD/WebP.');
        }

        Storage::fake('public');
    }

    private function anhThat(string $ten = 'thu.jpg'): UploadedFile
    {
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
        $path = app(ImageStore::class)->luu($this->anhThat(), 'products');

        $srcset = app(ResponsiveImage::class)->webpSrcset($path);

        $this->assertNotNull($srcset, 'Ảnh vừa tải lên chưa vào manifest.');
        $this->assertStringContainsString('400w', $srcset);
        $this->assertStringContainsString('800w', $srcset);
    }

    #[Test]
    public function admin_them_danh_muc_kem_anh_thi_anh_do_duoc_toi_uu(): void
    {
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
        $rac = UploadedFile::fake()->createWithContent('hong.jpg', 'day khong phai anh');

        $path = app(ImageStore::class)->luu($rac, 'products');

        $this->assertTrue(Storage::disk('public')->exists($path), 'Ảnh gốc phải lưu được dù không tối ưu nổi.');
        $this->assertNull(app(ResponsiveImage::class)->webpSrcset($path));
    }
}
