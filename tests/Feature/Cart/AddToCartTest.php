<?php

namespace Tests\Feature\Cart;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Thêm vào giỏ — hai dạng trả lời, MỘT luồng xử lý. */
class AddToCartTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $state = []): Product
    {
        return Product::factory()->for(Category::factory())->create($state);
    }

    private function ajax(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }

    #[Test]
    public function gui_bieu_mau_binh_thuong_thi_van_chuyen_huong_nhu_cu(): void
    {
        $product = $this->product();

        $this->from('/san-pham')
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect('/san-pham')
            ->assertSessionHas('success');
    }

    #[Test]
    public function gui_bieu_mau_binh_thuong_gap_loi_thi_bao_bang_flash(): void
    {
        $this->from('/san-pham')
            ->post('/gio-hang', ['product_id' => 999999, 'quantity' => 1])
            ->assertRedirect('/san-pham')
            ->assertSessionHasErrors('product_id');
    }

    #[Test]
    public function goi_bang_fetch_thi_nhan_json_kem_so_mon_trong_gio(): void
    {
        $product = $this->product();

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJson(['ok' => true, 'cartCount' => 2])
            ->assertJsonStructure(['ok', 'message', 'cartCount']);
    }

    #[Test]
    public function so_mon_trong_gio_do_may_chu_dem_chu_khong_phai_trinh_duyet_tu_cong(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertJson(['cartCount' => 1]);

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertJson(['cartCount' => 2]);

        $this->assertSame(1, \App\Models\CartItem::count(), 'Phải gộp dòng, không thêm dòng mới.');
    }

    #[Test]
    public function hang_khong_ban_truc_tiep_bi_tu_choi_o_ca_hai_duong(): void
    {
        $baoGia = $this->product(['base_price' => null]);

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $baoGia->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->flushHeaders();

        $this->from('/san-pham')
            ->post('/gio-hang', ['product_id' => $baoGia->id, 'quantity' => 1])
            ->assertRedirect('/san-pham')
            ->assertSessionHas('error');

        $this->assertSame(0, \App\Models\CartItem::count());
    }

    #[Test]
    public function du_lieu_sai_tra_ve_422_chu_khong_phai_200(): void
    {
        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => 999999, 'quantity' => 1])
            ->assertStatus(422);

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $this->product()->id, 'quantity' => 0])
            ->assertStatus(422);
    }

    #[Test]
    public function khach_vang_lai_cung_them_duoc_vao_gio(): void
    {
        $product = $this->product();

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    #[Test]
    public function khach_da_dang_nhap_them_vao_dung_gio_cua_minh(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->product();

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 3])
            ->assertJson(['cartCount' => 3]);

        $this->assertSame($user->id, \App\Models\Cart::first()->user_id);
    }

    #[Test]
    public function mua_ngay_van_chuyen_sang_trang_thanh_toan(): void
    {
        $product = $this->product();

        $this->post('/mua-ngay', ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect('/thanh-toan');

        $this->assertSame(0, \App\Models\CartItem::count(), 'Mua ngay không được đụng vào giỏ.');
    }

    #[Test]
    public function trang_chu_co_du_thuoc_tinh_cho_javascript_bam_vao(): void
    {
        $product = $this->product();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/class="product-buy"/',
            $html,
            'Biểu mẫu mua hàng phải mang đúng lớp product-buy.',
        );

        $this->assertMatchesRegularExpression(
            '/data-add-to-cart="'.$product->id.'"/',
            $html,
            'Nút "Thêm vào giỏ" phải mang đúng thuộc tính data-add-to-cart.',
        );

        $this->assertMatchesRegularExpression(
            '/data-cart-badge[\s>]/',
            $html,
            'Huy hiệu số món phải có chỗ cho JavaScript bám vào.',
        );

        $this->assertMatchesRegularExpression(
            '/data-cart-link[\s>]/',
            $html,
            'Liên kết giỏ hàng phải có chỗ cho JavaScript bám vào.',
        );
    }
}
