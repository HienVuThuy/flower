<?php

namespace Tests\Feature\Cart;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sửa giỏ hàng KHÔNG TẢI LẠI TRANG — và vẫn chạy khi không có JavaScript.
 * ============================================================
 * Ba route sửa giỏ (đổi số lượng, xoá, chọn món) giờ trả lời theo hai
 * dạng: chuyển hướng cho biểu mẫu thường, JSON kèm HTML cho fetch.
 *
 * ĐIỀU DỄ HỎNG NHẤT KHÔNG PHẢI NHÁNH JSON — mà là nhánh cũ. Thêm một
 * nhánh vào giữa hàm là cơ hội để nhánh còn lại lặng lẽ đổi hành vi, và
 * không ai phát hiện vì trình duyệt của người viết code luôn có
 * JavaScript. Vì vậy mỗi thao tác ở đây được kiểm CẢ HAI đường.
 *
 * Điều thứ hai phải giữ: HTML trả về là do MÁY CHỦ vẽ, nên mọi con số
 * trong đó phải là con số sau khi sửa. Trả về HTML cũ thì trình duyệt
 * hiển thị một trạng thái đã lỗi thời mà trông y như thật.
 */
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

    /** Một giỏ có sẵn một dòng, trả về dòng đó. */
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

        /*
         * Con số phải nằm TRONG HTML trả về, không chỉ trong database.
         * Đây đúng là chỗ dễ sai: vẽ lại từ dữ liệu đã nạp trước khi sửa
         * thì database đúng mà màn hình vẫn hiện số cũ.
         */
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

        // Khối trả về phải là màn hình trống, không phải một danh sách
        // rỗng — xem chú thích ở shop/cart/index.blade.php.
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

        // Danh sách rỗng = bỏ tích tất cả.
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
        /*
         * Route /gio-hang/khoi tồn tại để vẽ lại sau khi khách thêm phụ
         * kiện từ khối "Có thể bạn cần thêm". Nó là GET và phải ĐỌC
         * THÔI: một route "làm mới" mà lặng lẽ đổi dữ liệu là loại lỗi
         * chỉ lộ ra khi trình duyệt tự tải trước liên kết.
         */
        $this->actingAs(User::factory()->create());
        $item = $this->gioCoMot($this->sanPham(), 3);

        $res = $this->getJson('/gio-hang/khoi');

        $res->assertOk()->assertJson(['ok' => true, 'cartCount' => 3]);
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 3, 'is_selected' => true]);
    }

    #[Test]
    public function khong_sua_duoc_dong_trong_gio_cua_nguoi_khac_qua_duong_json(): void
    {
        /*
         * Nhánh JSON KHÔNG được lỏng hơn nhánh cũ. Thêm một đường vào
         * hàm là thêm một đường phải kiểm quyền — và đường mới là đường
         * dễ quên.
         */
        $nguoiKhac = User::factory()->create();
        $this->actingAs($nguoiKhac);
        $item = $this->gioCoMot($this->sanPham());

        $this->actingAs(User::factory()->create());

        $this->patchJson('/gio-hang/' . $item->id, ['quantity' => 9])->assertForbidden();
        $this->deleteJson('/gio-hang/' . $item->id)->assertForbidden();

        // Vẫn đúng 2 như lúc chủ giỏ thêm vào — không dòng nào bị chạm.
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 2]);
    }
}
