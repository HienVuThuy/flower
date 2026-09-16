<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Nhật ký thao tác quản trị. */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $ten = 'Người quản trị'): User
    {
        $user = User::factory()->create(['name' => $ten]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function sanPham(): Product
    {
        return Product::factory()->for(Category::factory())->create();
    }

    #[Test]
    public function xoa_san_pham_duoc_ghi_lai_kem_ten_nguoi_lam(): void
    {
        $product = $this->sanPham();

        $this->actingAs($this->admin('Nguyễn Văn Quản'))
            ->delete("/admin/products/{$product->id}")
            ->assertRedirect();

        $log = ActivityLog::latest('id')->first();

        $this->assertSame('product.deleted', $log->action);
        $this->assertStringContainsString($product->name, $log->description);
        $this->assertSame('Nguyễn Văn Quản', $log->actorLabel());
    }

    #[Test]
    public function nhat_ky_giu_dung_khoa_chinh_cua_thu_vua_bi_xoa(): void
    {
        $product = $this->sanPham();
        $id = $product->id;

        $this->actingAs($this->admin())->delete("/admin/products/{$id}");

        $log = ActivityLog::latest('id')->first();

        $this->assertSame($id, (int) $log->subject_id);
        $this->assertSame(Product::class, $log->subject_type);
    }

    #[Test]
    public function doi_gia_san_pham_thi_ghi_ca_gia_cu(): void
    {
        $product = $this->sanPham();
        $product->base_price = '500000.00';
        $product->save();

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'product_code' => $product->product_code,
            'product_type' => $product->product_type->value,
            'selling_form' => $product->selling_form->value,
            'base_price' => '300000',
            'track_inventory' => '1',
            'stock_quantity' => '10',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $log = ActivityLog::where('action', 'product.updated')->latest('id')->first();

        $this->assertNotNull($log, 'Sửa sản phẩm phải được ghi nhật ký.');
        $this->assertSame('500000.00', $log->properties['gia_truoc']);
        $this->assertSame('300000.00', $log->properties['gia_sau']);
    }

    #[Test]
    public function doi_trang_thai_don_duoc_ghi_kem_trang_thai_truoc_va_sau(): void
    {
        $admin = $this->admin('Trần Thị Vận');
        $order = Order::create([
            'order_number' => 'FP-NK-'.strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '25000.00',
            'coupon_discount' => '0.00',
            'grand_total' => '325000.00',
        ]);

        $this->actingAs($admin);
        app(OrderService::class)->changeStatus($order, OrderStatus::Confirmed);

        $log = ActivityLog::where('action', 'order.status_changed')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('Chờ xác nhận', $log->properties['truoc']);
        $this->assertSame('Đã xác nhận', $log->properties['sau']);
        $this->assertSame('Trần Thị Vận', $log->actorLabel());
    }

    #[Test]
    public function nhat_ky_song_sot_khi_nguoi_lam_bi_xoa_tai_khoan(): void
    {
        $admin = $this->admin('Người sắp nghỉ việc');
        $product = $this->sanPham();

        $this->actingAs($admin)->delete("/admin/products/{$product->id}");

        $admin->delete();

        $log = ActivityLog::latest('id')->first();

        $this->assertNotNull($log, 'Dòng nhật ký phải ở lại.');
        $this->assertNull($log->user_id);
        $this->assertStringContainsString('Người sắp nghỉ việc', $log->actorLabel());
        $this->assertStringContainsString('đã xoá', $log->actorLabel());
    }

    #[Test]
    public function trang_nhat_ky_loc_duoc_theo_nhom_viec(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->delete("/admin/products/{$this->sanPham()->id}");

        $danhMuc = Category::factory()->create(['name' => 'Danh mục sẽ bị xoá']);
        $this->delete("/admin/categories/{$danhMuc->id}");

        $this->get('/admin/nhat-ky?nhom=category')
            ->assertOk()
            ->assertSee('Danh mục sẽ bị xoá')
            ->assertDontSee('Xoá sản phẩm');
    }

    #[Test]
    public function khong_co_duong_nao_xoa_duoc_nhat_ky(): void
    {
        $this->actingAs($this->admin())->delete("/admin/products/{$this->sanPham()->id}");

        $log = ActivityLog::latest('id')->first();

        $this->actingAs($this->admin())
            ->delete("/admin/nhat-ky/{$log->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('activity_logs', ['id' => $log->id]);
    }

    #[Test]
    public function khach_thuong_khong_xem_duoc_nhat_ky(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/nhat-ky')
            ->assertForbidden();
    }
}
