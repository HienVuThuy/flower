<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Xoá sản phẩm: nửa hoàn tác được, nửa không, thì không hoàn tác được. */
class ProductDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function sanPhamCoAnh(): Product
    {
        Storage::fake('public');

        $product = Product::factory()->for(Category::factory())->create([
            'main_image' => 'products/chinh.jpg',
        ]);

        Storage::disk('public')->put('products/chinh.jpg', 'anh-chinh');
        Storage::disk('public')->put('products/phu.jpg', 'anh-phu');

        $product->images()->create([
            'path' => 'products/phu.jpg',
            'sort_order' => 1,
        ]);

        return $product;
    }

    #[Test]
    public function xoa_san_pham_khong_dung_toi_file_anh(): void
    {
        $product = $this->sanPhamCoAnh();

        $this->actingAs($this->admin())
            ->delete("/admin/products/{$product->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('products', ['id' => $product->id]);

        Storage::disk('public')->assertExists('products/chinh.jpg');
        Storage::disk('public')->assertExists('products/phu.jpg');
    }

    #[Test]
    public function khoi_phuc_san_pham_thi_anh_van_con_nguyen(): void
    {
        $product = $this->sanPhamCoAnh();

        $this->actingAs($this->admin())->delete("/admin/products/{$product->id}");

        $khoiPhuc = Product::withTrashed()->findOrFail($product->id);
        $khoiPhuc->restore();

        $this->assertSame('products/chinh.jpg', $khoiPhuc->fresh()->main_image);
        $this->assertSame(1, $khoiPhuc->images()->count());
        Storage::disk('public')->assertExists('products/phu.jpg');
    }

    #[Test]
    public function xoa_vinh_vien_moi_don_file_anh(): void
    {
        $product = $this->sanPhamCoAnh();

        $product->forceDelete();

        Storage::disk('public')->assertMissing('products/chinh.jpg');
        Storage::disk('public')->assertMissing('products/phu.jpg');
    }
}
