<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** "Hàng mới về" phải thật sự là hàng mới. */
class NewArrivalTest extends TestCase
{
    use RefreshDatabase;

    private function sanPham(string $ten, int $soNgayTruoc): Product
    {
        $p = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->create(['name' => $ten, 'status' => 'active']);

        $p->forceFill(['created_at' => now()->subDays($soNgayTruoc)])->saveQuietly();

        return $p->refresh();
    }

    #[Test]
    public function hang_nhap_trong_khoang_ngay_thi_duoc_tinh_la_moi(): void
    {
        config()->set('catalog.new_arrival_days', 60);

        $this->sanPham('Cây mới hôm qua', 1);
        $this->sanPham('Cây nhập tháng trước', 40);

        $this->assertSame(2, Product::mainCatalog()->newArrivals()->count());
    }

    #[Test]
    public function hang_qua_nguong_ngay_thi_khong_con_la_moi(): void
    {
        config()->set('catalog.new_arrival_days', 60);

        $this->sanPham('Cây mới', 5);
        $this->sanPham('Cây nhập từ quý trước', 90);

        $ten = Product::mainCatalog()->newArrivals()->pluck('name')->all();

        $this->assertSame(['Cây mới'], $ten);
    }

    #[Test]
    public function nguong_ngay_doc_tu_cau_hinh_chu_khong_viet_cung(): void
    {
        $this->sanPham('Cây nhập 30 ngày trước', 30);

        config()->set('catalog.new_arrival_days', 60);
        $this->assertSame(1, Product::mainCatalog()->newArrivals()->count());

        config()->set('catalog.new_arrival_days', 14);
        $this->assertSame(0, Product::mainCatalog()->newArrivals()->count());
    }

    #[Test]
    public function khong_co_hang_moi_thi_trang_chu_khong_hien_khoi_do(): void
    {
        config()->set('catalog.new_arrival_days', 60);

        $this->sanPham('Cây nhập từ năm ngoái', 400);

        $res = $this->get('/');

        $res->assertOk();
        $res->assertDontSee('Hàng mới về');
    }

    #[Test]
    public function co_hang_moi_thi_khoi_do_hien_ra(): void
    {
        config()->set('catalog.new_arrival_days', 60);

        $this->sanPham('Cây vừa nhập tuần này', 3);

        $res = $this->get('/');

        $res->assertOk();
        $res->assertSee('Hàng mới về');
        $res->assertSee('Cây vừa nhập tuần này');
    }
}
