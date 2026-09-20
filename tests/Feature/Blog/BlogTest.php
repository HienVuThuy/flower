<?php

namespace Tests\Feature\Blog;

use App\Enums\UserRole;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cẩm nang — blog. */
class BlogTest extends TestCase
{
    use RefreshDatabase;

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

    #[Test]
    public function ban_nhap_khong_lo_ra_ngoai_du_biet_dung_slug(): void
    {
        $this->bai(['slug' => 'ban-nhap', 'published_at' => null]);

        $this->get('/cam-nang/ban-nhap')->assertNotFound();
        $this->get('/cam-nang')->assertOk()->assertDontSee('Bài thử');
    }

    #[Test]
    public function bai_hen_gio_chua_toi_ngay_thi_chua_hien(): void
    {
        $this->bai(['slug' => 'tuan-sau', 'published_at' => now()->addWeek()]);

        $this->get('/cam-nang/tuan-sau')->assertNotFound();
        $this->get('/cam-nang')->assertOk()->assertDontSee('Bài thử');
    }

    #[Test]
    public function bai_da_dang_thi_doc_duoc_va_dem_luot_xem(): void
    {
        $post = $this->bai(['title' => 'Cây chịu bóng', 'slug' => 'cay-chiu-bong']);

        $this->get('/cam-nang/cay-chiu-bong')->assertOk()->assertSee('Cây chịu bóng');

        $this->assertSame(1, $post->fresh()->view_count);
    }

    #[Test]
    public function loc_theo_chuyen_muc_bang_duong_dan(): void
    {
        $cm = BlogCategory::create(['name' => 'Chăm cây', 'slug' => 'cham-cay']);

        $this->bai(['title' => 'Bài trong mục', 'slug' => 'trong-muc', 'blog_category_id' => $cm->id]);
        $this->bai(['title' => 'Bài ngoài mục', 'slug' => 'ngoai-muc']);

        $this->get('/cam-nang?chuyen-muc=cham-cay')
            ->assertOk()
            ->assertSee('Bài trong mục')
            ->assertDontSee('Bài ngoài mục');
    }

    #[Test]
    public function chuyen_muc_khong_ton_tai_thi_hien_tat_ca_chu_khong_404(): void
    {
        $this->bai(['title' => 'Vẫn hiện']);

        $this->get('/cam-nang?chuyen-muc=khong-co-that')->assertOk()->assertSee('Vẫn hiện');
    }

    #[Test]
    public function the_mo_ta_lay_dung_thu_tu_lui(): void
    {
        $post = $this->bai([
            'slug' => 'co-tom-tat',
            'excerpt' => 'Đây là tóm tắt.',
            'body' => '<p>Nội dung dài hơn nhiều.</p>',
        ]);

        $this->assertSame('Đây là tóm tắt.', $post->metaDescription());

        $post->meta_description = 'Mô tả SEO riêng.';
        $this->assertSame('Mô tả SEO riêng.', $post->metaDescription());

        $khac = $this->bai(['slug' => 'khong-tom-tat', 'body' => '<p>Chỉ có nội dung.</p>']);
        $this->assertSame('Chỉ có nội dung.', $khac->metaDescription());
    }

    #[Test]
    public function noi_dung_bai_di_qua_HtmlSanitizer_LUC_LUU(): void
    {
        $this->actingAs($this->admin())->post('/admin/cam-nang', [
            'title' => 'Bài có mã độc',
            'body' => '<p>Chữ thật</p><script>alert(1)</script><p onclick="alert(2)">Nữa</p>',
        ])->assertRedirect();

        $body = BlogPost::where('title', 'Bài có mã độc')->value('body');

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('alert(1)', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringContainsString('Chữ thật', $body);
    }

    #[Test]
    public function slug_KHONG_doi_khi_sua_tieu_de(): void
    {
        $post = $this->bai(['title' => 'Tiêu đề cũ', 'slug' => 'tieu-de-cu']);

        $this->actingAs($this->admin())->put('/admin/cam-nang/' . $post->slug, [
            'title' => 'Tiêu đề đã đổi hoàn toàn',
            'body' => '<p>Nội dung.</p>',
        ])->assertRedirect();

        $post->refresh();

        $this->assertSame('Tiêu đề đã đổi hoàn toàn', $post->title);
        $this->assertSame('tieu-de-cu', $post->slug, 'Slug đã đổi — mọi link cũ vừa chết.');
    }

    #[Test]
    public function slug_moi_khong_trung_ke_ca_voi_bai_da_xoa_mem(): void
    {
        $cu = $this->bai(['title' => 'Cây chịu bóng', 'slug' => 'cay-chiu-bong']);
        $cu->delete();

        $this->actingAs($this->admin())->post('/admin/cam-nang', [
            'title' => 'Cây chịu bóng',
            'body' => '<p>Bài mới.</p>',
        ])->assertRedirect();

        $this->assertDatabaseHas('blog_posts', ['slug' => 'cay-chiu-bong-2']);
    }

    #[Test]
    public function gan_san_pham_va_bo_tich_thi_go_khoi_bai(): void
    {
        $danhMuc = Category::factory()->create(['kind' => 'plant', 'is_active' => true]);
        $a = Product::factory()->for($danhMuc)->create(['name' => 'Cây A']);
        $b = Product::factory()->for($danhMuc)->create(['name' => 'Cây B']);

        $post = $this->bai(['slug' => 'co-san-pham']);

        $this->actingAs($this->admin())->put('/admin/cam-nang/' . $post->slug, [
            'title' => 'Bài thử',
            'body' => '<p>Nội dung.</p>',
            'products' => [
                ['id' => $a->id, 'note' => 'Chịu bóng tốt'],
                ['id' => $b->id, 'note' => ''],
                ['id' => '', 'note' => 'ghi chú lạc'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $post->fresh()->products()->count());
        $this->assertSame('Chịu bóng tốt', $post->fresh()->products()->find($a->id)->pivot->note);

        $this->actingAs($this->admin())->put('/admin/cam-nang/' . $post->slug, [
            'title' => 'Bài thử',
            'body' => '<p>Nội dung.</p>',
            'products' => [['id' => $a->id, 'note' => 'Chịu bóng tốt']],
        ]);

        $this->assertSame(1, $post->fresh()->products()->count());
    }

    #[Test]
    public function khach_va_nguoi_dung_thuong_khong_vao_duoc_trang_quan_tri(): void
    {
        $this->get('/admin/cam-nang')->assertRedirect('/dang-nhap');

        $this->actingAs(User::factory()->create())
            ->get('/admin/cam-nang')
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post('/admin/cam-nang', ['title' => 'Chen vào', 'body' => '<p>x</p>'])
            ->assertForbidden();

        $this->assertDatabaseCount('blog_posts', 0);
    }

    #[Test]
    public function admin_van_xem_duoc_ban_nhap_o_trang_quan_tri(): void
    {
        $this->bai(['title' => 'Bản nháp của tôi', 'slug' => 'nhap', 'published_at' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/cam-nang')
            ->assertOk()
            ->assertSee('Bản nháp của tôi')
            ->assertSee('Bản nháp');
    }
}
