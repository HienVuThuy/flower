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

/**
 * Xoá sản phẩm: nửa hoàn tác được, nửa không, thì không hoàn tác được.
 * ============================================================
 * Product dùng xoá mềm để lịch sử đơn hàng còn đọc được sau nhiều năm.
 * Nhưng trang quản trị lại xoá luôn FILE ẢNH trên đĩa trước khi xoá
 * mềm — một việc không có đường lui.
 *
 * Hậu quả: khôi phục sản phẩm ra một trang hàng đủ tên, đủ giá, và
 * không còn tấm ảnh nào. Không có thông báo lỗi nào, vì xét về mã lệnh
 * thì mọi thứ đã chạy đúng.
 */
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

    /** Một sản phẩm có ảnh chính và một ảnh phụ, file thật trên đĩa giả. */
    private function sanPhamCoAnh(): Product
    {
        Storage::fake('public');

        $product = Product::factory()->for(Category::factory())->create([
            'main_image' => 'products/chinh.jpg',
        ]);

        Storage::disk('public')->put('products/chinh.jpg', 'anh-chinh');
        Storage::disk('public')->put('products/phu.jpg', 'anh-phu');

        // Qua quan hệ để không phụ thuộc product_id có nằm trong
        // $fillable của ProductImage hay không.
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

        // Sản phẩm đã khuất khỏi cửa hàng...
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        // ...nhưng ảnh vẫn còn, nên khôi phục là khôi phục được thật.
        Storage::disk('public')->assertExists('products/chinh.jpg');
        Storage::disk('public')->assertExists('products/phu.jpg');
    }

    #[Test]
    public function khoi_phuc_san_pham_thi_anh_van_con_nguyen(): void
    {
        /*
         * Bài trên kiểm file còn trên đĩa. Bài này kiểm ĐIỀU MÀ NGƯỜI
         * DÙNG THẤY: sản phẩm quay lại với đúng bộ ảnh của nó.
         */
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
        /*
         * Mặt còn lại: không được vì sợ mất ảnh mà giữ rác mãi mãi. Khi
         * sản phẩm bị xoá HẲN thì file cũng phải đi theo — nếu không,
         * thư mục ảnh chỉ có phình ra và không ai biết tấm nào còn dùng.
         */
        $product = $this->sanPhamCoAnh();

        $product->forceDelete();

        Storage::disk('public')->assertMissing('products/chinh.jpg');
        Storage::disk('public')->assertMissing('products/phu.jpg');
    }
}
