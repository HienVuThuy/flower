<?php

namespace Tests\Feature\Cart;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Sửa giỏ hàng KHÔNG TẢI LẠI TRANG — và vẫn chạy khi không có JavaScript. */
class CartLiveTest extends TestCase
{
    use RefreshDatabase;

    private function sanPham(string $gia = '100000.00', int $ton = 20): Product
    {
        return Product::factory()
            ->for(Category::factory())
            ->price($gia)
            ->stock($ton)
            ->create();
    }

    private function gioCoMot(Product $product, int $soLuong = 2): \App\Models\CartItem
    {
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => $soLuong]);

        return \App\Models\CartItem::latest('id')->firstOrFail();
    }

    #[Test]
    public function doi_so_luong_bang_fetch_tra_ve_html_da_tinh_lai(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->sanPham('100000.00');
        $item = $this->gioCoMot($product, 2);

        $res = $this->patchJson('/gio-hang/' . $item->id, ['quantity' => 5]);

        $res->assertOk()
            ->assertJson(['ok' => true, 'cartCount' => 5])
            ->assertJsonStructure(['ok', 'message', 'cartCount', 'html']);

        $this->assertStringContainsString('500.000', $res->json('html'));
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 5]);
    }

    #[Test]
    public function doi_so_luong_khong_co_javascript_van_chuyen_huong_nhu_cu(): void
    {
        $this->actingAs(User::factory()->create());
        $item = $this->gioCoMot($this->sanPham(), 2);

        $this->from('/gio-hang')
            ->patch('/gio-hang/' . $item->id, ['quantity' => 3])
            ->assertRedirect('/gio-hang')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 3]);
    }

    #[Test]
    public function xoa_mon_cuoi_cung_tra_ve_man_hinh_gio_trong(): void
    {
        $this->actingAs(User::factory()->create());
        $item = $this->gioCoMot($this->sanPham());

        $res = $this->deleteJson('/gio-hang/' . $item->id);

        $res->assertOk()->assertJson(['ok' => true, 'cartCount' => 0]);

        $this->assertStringContainsString('Giỏ hàng đang trống', $res->json('html'));
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    #[Test]
    public function xoa_khong_co_javascript_van_chuyen_huong_nhu_cu(): void
    {
        $this->actingAs(User::factory()->create());
        $item = $this->gioCoMot($this->sanPham());

        $this->from('/gio-hang')
            ->delete('/gio-hang/' . $item->id)
            ->assertRedirect('/gio-hang')
            ->assertSessionHas('success');
    }

    #[Test]
    public function bo_tich_mot_mon_lam_tam_tinh_ve_khong(): void
    {
        $this->actingAs(User::factory()->create());
        $item = $this->gioCoMot($this->sanPham('100000.00'), 2);

        $res = $this->postJson('/gio-hang/chon', ['selected' => []]);

        $res->assertOk()->assertJson(['ok' => true]);
        $this->assertStringContainsString('Đang chọn <strong>0</strong>', $res->json('html'));
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'is_selected' => false]);
    }

    #[Test]
    public function chon_mon_khong_co_javascript_van_chuyen_huong_nhu_cu(): void
    {
        $this->actingAs(User::factory()->create());
        $item = $this->gioCoMot($this->sanPham());

        $this->from('/gio-hang')
            ->post('/gio-hang/chon', ['selected' => [$item->id]])
            ->assertRedirect('/gio-hang');
    }

    #[Test]
    public function ve_lai_khoi_gio_khong_sua_gi_ca(): void
    {
        $this->actingAs(User::factory()->create());
        $item = $this->gioCoMot($this->sanPham(), 3);

        $res = $this->getJson('/gio-hang/khoi');

        $res->assertOk()->assertJson(['ok' => true, 'cartCount' => 3]);
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 3, 'is_selected' => true]);
    }

    #[Test]
    public function khong_sua_duoc_dong_trong_gio_cua_nguoi_khac_qua_duong_json(): void
    {
        $nguoiKhac = User::factory()->create();
        $this->actingAs($nguoiKhac);
        $item = $this->gioCoMot($this->sanPham());

        $this->actingAs(User::factory()->create());

        $this->patchJson('/gio-hang/' . $item->id, ['quantity' => 9])->assertForbidden();
        $this->deleteJson('/gio-hang/' . $item->id)->assertForbidden();

        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 2]);
    }
}
