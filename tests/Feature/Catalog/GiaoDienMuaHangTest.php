<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Khối mua hàng dính và trạng thái "đang lọc" của danh sách. */
class GiaoDienMuaHangTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function trang_san_pham_co_thanh_mua_nhanh_khi_mua_duoc(): void
    {
        $banDuoc = Product::factory()->for(Category::factory())->price('300000.00')->stock(5)->create(['status' => 'active']);

        $this->get(route('shop.products.show', $banDuoc))
            ->assertOk()
            ->assertSee('data-mua-chinh', false)
            ->assertSee('data-mua-nhanh', false);
    }

    #[Test]
    public function het_hang_thi_khong_hien_thanh_mua_nhanh(): void
    {
        $het = Product::factory()->for(Category::factory())->price('300000.00')->stock(0)->create(['status' => 'out_of_stock']);

        $this->get(route('shop.products.show', $het))
            ->assertOk()
            ->assertDontSee('data-mua-nhanh', false);
    }

    #[Test]
    public function danh_sach_co_moc_de_bao_dang_loc(): void
    {
        Product::factory()->for(Category::factory())->price('120000.00')->stock(3)->create(['status' => 'active']);

        $this->get(route('shop.products.index'))
            ->assertOk()
            ->assertSee('data-luoi-san-pham', false)
            ->assertSee('data-form-loc', false);
    }
}
