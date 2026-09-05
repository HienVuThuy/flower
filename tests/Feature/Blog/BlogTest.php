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

/**
 * Cẩm nang — blog.
 * ============================================================
 * Trọng tâm là những chỗ SAI THÌ MẤT TIỀN hoặc MẤT AN TOÀN:
 *
 *   - bản nháp và bài hẹn giờ KHÔNG được lộ ra ngoài;
 *   - slug KHÔNG đổi theo tiêu đề (đổi là chết mọi link đã chia sẻ và
 *     mất thứ hạng Google — thứ cả khu vực này sinh ra để xây);
 *   - nội dung PHẢI đi qua HtmlSanitizer lúc lưu, vì đây là chỗ duy nhất
 *     trong dự án in HTML thô.
 */
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

    /* ================= HIỂN THỊ ================= */

    #[Test]
    public function ban_nhap_khong_lo_ra_ngoai_du_biet_dung_slug(): void
    {
        /*
         * Route-model binding tìm theo slug và KHÔNG biết gì về trạng
         * thái. Thiếu phép kiểm ở controller thì ai đoán trúng slug là
         * đọc được bản nháp.
         */
        $this->bai(['slug' => 'ban-nhap', 'published_at' => null]);

        $this->get('/cam-nang/ban-nhap')->assertNotFound();
        $this->get('/cam-nang')->assertOk()->assertDontSee('Bài thử');
    }

    #[Test]
    public function bai_hen_gio_chua_toi_ngay_thi_chua_hien(): void
    {
        /*
         * `scopePublished` phải kiểm CẢ `<= now()`, không chỉ
         * `whereNotNull`. Bỏ vế so sánh thì cả tính năng hẹn giờ thành vô
         * nghĩa mà không có gì báo.
         */
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
        // Lọc bằng đường dẫn để Google đọc được từng chuyên mục như một
        // trang riêng — lý do quan trọng nhất với khu vực sinh ra để SEO.
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
        // URL cũ trong một bài chia sẻ vẫn nên dẫn tới danh sách đầy đủ,
        // hơn là dẫn vào trang lỗi.
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

        // Không có cả hai thì cắt từ nội dung — vẫn hơn để trống, vì
        // không có mô tả thì Google tự bịa một đoạn và bịa tệ hơn.
        $khac = $this->bai(['slug' => 'khong-tom-tat', 'body' => '<p>Chỉ có nội dung.</p>']);
        $this->assertSame('Chỉ có nội dung.', $khac->metaDescription());
    }

    /* ================= QUẢN TRỊ ================= */

    #[Test]
    public function noi_dung_bai_di_qua_HtmlSanitizer_LUC_LUU(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT CỦA TỆP NÀY.
         *
         * Bài viết là chỗ duy nhất trong dự án in HTML thô ra trang. Nếu
         * bước làm sạch bị bỏ ở controller thì không có lớp nào phía sau
         * đỡ — mọi chỗ hiển thị đều `{!! !!}`.
         *
         * Kiểm ở TRONG CƠ SỞ DỮ LIỆU, không kiểm ở trang hiển thị: làm
         * sạch lúc lưu nghĩa là thứ nằm trong bảng đã sạch, và không có
         * đường nào lấy ra bản chưa sạch.
         */
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
        /*
         * Đổi slug là làm chết mọi link đã chia sẻ và mọi thứ hạng Google
         * đã có — đúng thứ cả khu vực này sinh ra để xây.
         */
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
        // Bài xoá mềm vẫn giữ slug, nên tạo bài mới trùng tên sẽ đụng
        // ràng buộc UNIQUE nếu không tính tới nó.
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
                // Hàng chưa chọn -> bỏ qua, KHÔNG báo lỗi (QĐ-128).
                ['id' => '', 'note' => 'ghi chú lạc'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $post->fresh()->products()->count());
        $this->assertSame('Chịu bóng tốt', $post->fresh()->products()->find($a->id)->pivot->note);

        // Lưu lại chỉ với một sản phẩm -> sản phẩm kia phải bị gỡ.
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
        $this->get('/admin/cam-nang')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/admin/cam-nang')
            ->assertForbidden();

        // Và không ghi được — đây mới là chỗ nguy hiểm, vì nội dung bài
        // được in ra dưới dạng HTML thô.
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
