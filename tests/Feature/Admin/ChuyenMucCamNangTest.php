<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Quản lý chuyên mục Cẩm nang.
 * ============================================================
 * Trước đây ba chuyên mục đến từ dữ liệu mẫu và không có chỗ nào thêm
 * hay sửa.
 */
class ChuyenMucCamNangTest extends TestCase
{
    use RefreshDatabase;

    private function nguoi(UserRole $vaiTro = UserRole::Admin): User
    {
        $u = User::factory()->create();
        $u->role = $vaiTro;
        $u->save();

        return $u;
    }

    private function cm(string $ten, string $slug): BlogCategory
    {
        return BlogCategory::create(['name' => $ten, 'slug' => $slug]);
    }

    #[Test]
    public function them_chuyen_muc_de_trong_dia_chi_thi_tu_tao_tu_ten(): void
    {
        $this->actingAs($this->nguoi())
            ->post(route('admin.blog-categories.store'), ['name' => 'Hoa cưới & sự kiện'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.blog-categories.index'));

        $this->assertSame('hoa-cuoi-su-kien', BlogCategory::where('name', 'Hoa cưới & sự kiện')->value('slug'));
    }

    #[Test]
    public function dia_chi_trung_bi_tu_choi(): void
    {
        $this->cm('Chăm cây', 'cham-cay');

        $this->actingAs($this->nguoi())
            ->post(route('admin.blog-categories.store'), ['name' => 'Chăm cây'])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, BlogCategory::count());
    }

    #[Test]
    public function sua_chuyen_muc_giu_duoc_dia_chi_cua_chinh_no(): void
    {
        // Luật "không trùng" phải bỏ qua chính bản ghi đang sửa.
        $cm = $this->cm('Chăm cây', 'cham-cay');

        $this->actingAs($this->nguoi())
            ->put(route('admin.blog-categories.update', $cm), [
                'name' => 'Chăm cây trong nhà',
                'slug' => 'cham-cay',
                'sort_order' => 3,
            ])
            ->assertSessionHasNoErrors();

        $cm->refresh();
        $this->assertSame('Chăm cây trong nhà', $cm->name);
        $this->assertSame(3, $cm->sort_order);
    }

    #[Test]
    public function KHONG_xoa_chuyen_muc_con_bai_ke_ca_bai_da_xoa_mem(): void
    {
        /*
         * Khôi phục bài đã xoá mềm về sau là nó trỏ vào một chuyên mục
         * không còn.
         */
        $cm = $this->cm('Chăm cây', 'cham-cay');
        $bai = BlogPost::create([
            'title' => 'Tưới cây mùa hè',
            'slug' => 'tuoi-cay-mua-he',
            'body' => '<p>Nội dung.</p>',
            'blog_category_id' => $cm->id,
        ]);
        $bai->delete();

        $this->actingAs($this->nguoi())
            ->delete(route('admin.blog-categories.destroy', $cm))
            ->assertSessionHas('error');

        $this->assertNotNull(BlogCategory::find($cm->id));
    }

    #[Test]
    public function xoa_duoc_chuyen_muc_rong(): void
    {
        $cm = $this->cm('Tạm', 'tam');

        $this->actingAs($this->nguoi())
            ->delete(route('admin.blog-categories.destroy', $cm))
            ->assertRedirect(route('admin.blog-categories.index'));

        $this->assertNull(BlogCategory::find($cm->id));
    }

    #[Test]
    public function chuyen_muc_moi_hien_o_trang_cam_nang_cua_khach(): void
    {
        $this->actingAs($this->nguoi())
            ->post(route('admin.blog-categories.store'), ['name' => 'Hoa khai trương']);

        $this->get(route('shop.blog.index'))
            ->assertOk()
            ->assertSee('Hoa khai trương');
    }

    #[Test]
    public function nhan_vien_khong_co_quyen_san_pham_bi_chan(): void
    {
        $nv = $this->nguoi(UserRole::Staff);

        $this->actingAs($nv)->get(route('admin.blog-categories.index'))->assertForbidden();
        $this->actingAs($nv)->post(route('admin.blog-categories.store'), ['name' => 'X'])->assertForbidden();
    }

    #[Test]
    public function trang_quan_ly_liet_ke_va_nut_xoa_chi_hien_khi_xoa_duoc(): void
    {
        $rong = $this->cm('Rỗng', 'rong');
        $coBai = $this->cm('Có bài', 'co-bai');
        BlogPost::create([
            'title' => 'Bài', 'slug' => 'bai', 'body' => '<p>x</p>', 'blog_category_id' => $coBai->id,
        ]);

        /*
         * TÌM ĐÚNG FORM XOÁ, không chỉ địa chỉ.
         *
         * Form SỬA của cùng chuyên mục trỏ tới đúng địa chỉ đó (PUT thay vì
         * DELETE) — tìm mỗi `action="..."` thì chuỗi luôn có mặt, và bài
         * này đỏ dù nút xoá đã ẩn đúng.
         */
        $xoa = fn ($cm) => 'action="' . route('admin.blog-categories.destroy', $cm) . '" class="d-inline"';

        $this->actingAs($this->nguoi())
            ->get(route('admin.blog-categories.index'))
            ->assertOk()
            ->assertSee('Rỗng')
            ->assertSee('Có bài')
            ->assertSee($xoa($rong), false)
            ->assertDontSee($xoa($coBai), false);
    }
}
