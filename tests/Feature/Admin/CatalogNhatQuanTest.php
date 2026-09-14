<?php

namespace Tests\Feature\Admin;

use App\Enums\CategoryKind;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\ImageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Catalog nhất quán: nhóm danh mục, thùng rác sản phẩm, dọn ảnh, wishlist.
 */
class CatalogNhatQuanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    /* ================= NHÓM DANH MỤC ================= */

    #[Test]
    public function tao_duoc_danh_muc_phu_kien_tu_trang_quan_tri(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), ['name' => 'Chậu nhựa', 'kind' => 'supply', 'is_active' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame(CategoryKind::Supply, Category::where('name', 'Chậu nhựa')->firstOrFail()->kind);
    }

    #[Test]
    public function khong_chon_nhom_thi_la_hoa_va_cay(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), ['name' => 'Sen đá', 'is_active' => 1]);

        $this->assertSame(CategoryKind::Plant, Category::where('name', 'Sen đá')->firstOrFail()->kind);
    }

    #[Test]
    public function KHONG_doi_sang_phu_kien_khi_con_cay_that_trong_danh_muc(): void
    {
        $dm = Category::factory()->create(['name' => 'Chậu cảnh', 'kind' => 'plant']);
        Product::factory()->for($dm)->create(['product_type' => 'plant']);

        $this->actingAs($this->admin())
            ->put(route('admin.categories.update', $dm), ['name' => 'Chậu cảnh', 'kind' => 'supply', 'is_active' => 1])
            ->assertSessionHasErrors('kind');

        $this->assertSame(CategoryKind::Plant, $dm->fresh()->kind);
    }

    #[Test]
    public function bieu_mau_danh_muc_co_o_chon_nhom(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.categories.create'))
            ->assertOk()
            ->assertSee('name="kind"', false)
            ->assertSee('Phụ kiện &amp; vật tư', false);
    }

    /* ================= THÙNG RÁC ================= */

    #[Test]
    public function san_pham_da_xoa_hien_trong_thung_rac_va_khoi_phuc_ve_NHAP(): void
    {
        $sp = Product::factory()->for(Category::factory())->create(['name' => 'Cây thử', 'status' => 'active']);
        $sp->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.products.index', ['thung_rac' => 1]))
            ->assertOk()
            ->assertSee('Cây thử')
            ->assertSee(route('admin.products.restore', $sp->id), false);

        $this->patch(route('admin.products.restore', $sp->id))->assertRedirect();

        $sp = Product::findOrFail($sp->id);
        $this->assertFalse($sp->trashed());
        $this->assertSame('draft', $sp->status instanceof \BackedEnum ? $sp->status->value : $sp->status);
    }

    #[Test]
    public function xoa_vinh_vien_phai_go_dung_ten(): void
    {
        $sp = Product::factory()->for(Category::factory())->create(['name' => 'Cây thử']);
        $sp->delete();

        $this->actingAs($this->admin())
            ->delete(route('admin.products.force-destroy', $sp->id), ['xac_nhan' => 'cay thu'])
            ->assertSessionHas('error');
        $this->assertNotNull(Product::withTrashed()->find($sp->id));

        $this->delete(route('admin.products.force-destroy', $sp->id), ['xac_nhan' => 'Cây thử'])->assertRedirect();
        $this->assertNull(Product::withTrashed()->find($sp->id));
    }

    #[Test]
    public function KHONG_xoa_vinh_vien_san_pham_con_dang_ban(): void
    {
        // Chỉ thứ đã nằm trong thùng rác mới xoá hẳn được.
        $sp = Product::factory()->for(Category::factory())->create(['name' => 'Cây thử']);

        $this->actingAs($this->admin())
            ->delete(route('admin.products.force-destroy', $sp->id), ['xac_nhan' => 'Cây thử'])
            ->assertNotFound();
    }

    /* ================= DỌN ẢNH ================= */

    #[Test]
    public function xoa_vinh_vien_don_anh_qua_ImageStore_ke_ca_anh_chinh(): void
    {
        $sp = Product::factory()->for(Category::factory())->create(['main_image' => 'products/anh-chinh.jpg']);
        $sp->delete();

        $daXoa = [];
        $this->mock(ImageStore::class, function ($m) use (&$daXoa) {
            $m->shouldReceive('xoa')->andReturnUsing(function ($duong) use (&$daXoa) {
                $daXoa[] = $duong;
            });
        });

        $sp->forceDelete();

        $this->assertContains('products/anh-chinh.jpg', $daXoa, 'Ảnh chính phải được dọn qua ImageStore (kèm bản WebP)');

        $this->assertNull(Product::withTrashed()->find($sp->id));
    }

    #[Test]
    public function xoa_danh_muc_don_anh_qua_ImageStore(): void
    {
        $dm = Category::factory()->create(['image' => 'categories/dm.jpg']);

        $this->mock(ImageStore::class, fn ($m) => $m->shouldReceive('xoa')->with('categories/dm.jpg')->once());

        $this->actingAs($this->admin())->delete(route('admin.categories.destroy', $dm))->assertRedirect();
    }

    /* ================= WISHLIST ================= */

    #[Test]
    public function tai_khoan_chua_xac_thuc_van_mo_duoc_trang_yeu_thich(): void
    {
        // Bấm tim được thì phải xem được danh sách mình vừa bấm.
        $this->actingAs(User::factory()->create(['email_verified_at' => null]))
            ->get(route('shop.wishlist.index'))
            ->assertOk();
    }
}
