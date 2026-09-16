<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartException;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Quy cách sản phẩm (biến thể). */
class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    private function cart(): CartService
    {
        return app(CartService::class);
    }

    private function coQuyCach(): Product
    {
        $product = Product::factory()
            ->for(Category::factory())
            ->price('180000.00')
            ->stock(20)
            ->create(['name' => 'Lưỡi hổ mini để bàn']);

        $product->variants()->createMany([
            [
                'name' => 'Chậu sứ trắng',
                'price' => '180000.00',
                'is_active' => true,
                'track_inventory' => true,
                'stock_quantity' => 18,
                'sort_order' => 1,
            ],
            [
                'name' => 'Chậu gốm nâu',
                'price' => '195000.00',
                'is_active' => true,
                'track_inventory' => true,
                'stock_quantity' => 12,
                'sort_order' => 2,
            ],
        ]);

        return $product->load('variants');
    }

    #[Test]
    public function khong_them_duoc_vao_gio_neu_chua_chon_quy_cach(): void
    {
        $product = $this->coQuyCach();

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('cart_items', 0);
    }

    #[Test]
    public function mua_ngay_cung_bi_chan_y_het(): void
    {
        $product = $this->coQuyCach();

        $this->post('/mua-ngay', ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    #[Test]
    public function chon_quy_cach_thi_vao_gio_dung_gia_cua_quy_cach_do(): void
    {
        $product = $this->coQuyCach();
        $gomNau = $product->variants->firstWhere('name', 'Chậu gốm nâu');

        $this->post('/gio-hang', [
            'product_id' => $product->id,
            'variant_id' => $gomNau->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'product_variant_id' => $gomNau->id,
        ]);

        $dong = $this->cart()->current()->items->first();

        $this->assertSame(
            '195000.00',
            $dong->unitPrice(),
            'Phải tính theo giá quy cách đã chọn, không phải base_price.',
        );
    }

    #[Test]
    public function san_pham_khong_co_quy_cach_van_mua_binh_thuong(): void
    {
        $product = Product::factory()
            ->for(Category::factory())
            ->price('300000.00')
            ->stock(10)
            ->create();

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect()
            ->assertSessionMissing('error');

        $this->assertDatabaseCount('cart_items', 1);
    }

    #[Test]
    public function quy_cach_cua_san_pham_khac_bi_tu_choi(): void
    {
        $a = $this->coQuyCach();
        $b = $this->coQuyCach();

        $this->expectException(CartException::class);

        $this->cart()->assertPurchasable($a, $b->variants->first());
    }

    #[Test]
    public function dong_gio_cu_khong_quy_cach_khong_thanh_don_duoc(): void
    {
        $this->actingAs(User::factory()->create());

        $product = Product::factory()
            ->for(Category::factory())
            ->price('180000.00')
            ->stock(20)
            ->create();

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect();

        $this->assertDatabaseCount('cart_items', 1);

        $product->variants()->create([
            'name' => 'Chậu sứ trắng',
            'price' => '180000.00',
            'is_active' => true,
            'track_inventory' => true,
            'stock_quantity' => 5,
            'sort_order' => 1,
        ]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com',
            'shipping_address' => '12 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'address_id' => '',
        ]);

        $this->post('/thanh-toan/dat-hang')->assertRedirect('/gio-hang');

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function het_sach_quy_cach_thi_coi_nhu_het_hang(): void
    {
        $product = $this->coQuyCach();

        $product->variants()->update(['stock_quantity' => 0]);
        $product->refresh()->load('variants');

        $this->assertTrue($product->inStock(), 'Kho của chính sản phẩm vẫn còn...');
        $this->assertFalse($product->isPurchasable(), '...nhưng không quy cách nào bán được.');
    }

    #[Test]
    public function con_mot_quy_cach_con_hang_la_van_ban_duoc(): void
    {
        $product = $this->coQuyCach();

        $product->variants->firstWhere('name', 'Chậu sứ trắng')
            ->update(['stock_quantity' => 0]);

        $this->assertTrue($product->refresh()->load('variants')->isPurchasable());
    }

    #[Test]
    public function the_san_pham_co_quy_cach_dua_khach_qua_buoc_chon(): void
    {
        $product = $this->coQuyCach();

        $html = $this->get('/san-pham?q='.urlencode('Lưỡi hổ'))->assertOk()->getContent();

        $this->assertStringContainsString('data-variant-choice', $html);
        $this->assertStringContainsString('Chậu gốm nâu', $html);

        $this->assertStringContainsString(
            route('shop.products.show', $product).'#chon-quy-cach',
            $html,
        );
    }

    #[Test]
    public function chu_tren_nut_KHONG_doi_thanh_xem_chi_tiet(): void
    {
        $this->coQuyCach();

        $this->get('/san-pham?q='.urlencode('Lưỡi hổ'))
            ->assertOk()
            ->assertSee('Thêm vào giỏ')
            ->assertSee('Mua ngay');
    }
}
