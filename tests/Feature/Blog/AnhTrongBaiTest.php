<?php

namespace Tests\Feature\Blog;

use App\Enums\UserRole;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\Media\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Thư viện ảnh của bài Cẩm nang: admin tải ảnh lên rồi chèn vào thân bài. */
class AnhTrongBaiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function bai(array $ghiDe = []): BlogPost
    {
        $post = new BlogPost(array_merge([
            'title' => 'Bài thử',
            'slug' => 'bai-thu',
            'body' => '<p>Nội dung thử.</p>',
            'published_at' => now()->subDay(),
        ], $ghiDe));

        $post->save();

        return $post;
    }

    private function anh(string $ten = 'cay.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($ten, 800, 600);
    }

    #[Test]
    public function admin_tai_duoc_nhieu_anh_len_mot_bai(): void
    {
        $post = $this->bai();

        $this->actingAs($this->admin())->put('/admin/cam-nang/' . $post->slug, [
            'title' => $post->title,
            'body' => '<p>Nội dung.</p>',
            'anh_bai' => [$this->anh('mot.jpg'), $this->anh('hai.jpg')],
        ])->assertRedirect();

        $anhs = $post->refresh()->images;

        $this->assertCount(2, $anhs);

        foreach ($anhs as $a) {
            Storage::disk('public')->assertExists($a->path);
            $this->assertStringStartsWith('blog/', $a->path);
        }
    }

    #[Test]
    public function chu_thich_duoc_luu_va_dung_lam_chu_thay_the(): void
    {
        $post = $this->bai();

        $this->actingAs($this->admin())->put('/admin/cam-nang/' . $post->slug, [
            'title' => $post->title,
            'body' => '<p>Nội dung.</p>',
            'anh_bai' => [$this->anh()],
        ]);

        $anh = $post->refresh()->images->sole();

        $this->actingAs($this->admin())->put('/admin/cam-nang/' . $post->slug, [
            'title' => $post->title,
            'body' => '<p>Nội dung.</p>',
            'chu_thich' => [$anh->id => 'Lưỡi hổ để bàn'],
        ])->assertRedirect();

        $anh->refresh();

        $this->assertSame('Lưỡi hổ để bàn', $anh->alt);
        $this->assertStringContainsString('alt="Lưỡi hổ để bàn"', $anh->maChen());
        $this->assertStringContainsString($anh->duongDan(), $anh->maChen());
    }

    #[Test]
    public function xoa_anh_thi_xoa_luon_tep(): void
    {
        $post = $this->bai();

        $this->actingAs($this->admin())->put('/admin/cam-nang/' . $post->slug, [
            'title' => $post->title,
            'body' => '<p>Nội dung.</p>',
            'anh_bai' => [$this->anh()],
        ]);

        $anh = $post->refresh()->images->sole();

        $this->actingAs($this->admin())->put('/admin/cam-nang/' . $post->slug, [
            'title' => $post->title,
            'body' => '<p>Nội dung.</p>',
            'xoa_anh' => [$anh->id],
        ])->assertRedirect();

        $this->assertCount(0, $post->refresh()->images);
        Storage::disk('public')->assertMissing($anh->path);
    }

    #[Test]
    public function xoa_bai_thi_xoa_ca_thu_vien_anh(): void
    {
        $post = $this->bai();

        $this->actingAs($this->admin())->put('/admin/cam-nang/' . $post->slug, [
            'title' => $post->title,
            'body' => '<p>Nội dung.</p>',
            'anh_bai' => [$this->anh()],
        ]);

        $duong = $post->refresh()->images->sole()->path;

        $this->actingAs($this->admin())->delete('/admin/cam-nang/' . $post->slug)->assertRedirect();

        Storage::disk('public')->assertMissing($duong);
        $this->assertSame(0, \App\Models\BlogPostImage::count());
    }

    #[Test]
    public function than_bai_GIU_anh_cua_cua_hang_nhung_BO_anh_trang_ngoai(): void
    {
        $sach = app(HtmlSanitizer::class)->lamSach(
            '<p>Chữ.</p>'
            .'<figure><img src="/storage/blog/cay.jpg" alt="Cây"><figcaption>Cây</figcaption></figure>'
            .'<img src="https://trang-khac.example/anh.jpg" alt="Ảnh ngoài">'
        );

        $this->assertStringContainsString('src="/storage/blog/cay.jpg"', $sach);
        $this->assertStringContainsString('alt="Cây"', $sach);
        $this->assertStringNotContainsString('trang-khac.example', $sach);
    }

    #[Test]
    public function anh_trong_than_bai_hien_ra_trang_doc(): void
    {
        $post = $this->bai([
            'slug' => 'bai-co-anh',
            'body' => '<p>Chữ.</p><figure><img src="/storage/blog/cay.jpg" alt="Cây"></figure>',
        ]);

        $this->get('/cam-nang/' . $post->slug)
            ->assertOk()
            ->assertSee('src="/storage/blog/cay.jpg"', false);
    }

    #[Test]
    public function khach_thuong_khong_tai_duoc_anh_len_bai(): void
    {
        $post = $this->bai();

        $this->actingAs(User::factory()->create())
            ->put('/admin/cam-nang/' . $post->slug, [
                'title' => $post->title,
                'body' => '<p>Nội dung.</p>',
                'anh_bai' => [$this->anh()],
            ])
            ->assertForbidden();

        $this->assertCount(0, $post->refresh()->images);
    }
}
