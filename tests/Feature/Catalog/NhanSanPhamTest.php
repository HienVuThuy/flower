<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\GiftItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Nhãn góc trên trái của thẻ sản phẩm: giảm giá, nổi bật, có quà. */
class NhanSanPhamTest extends TestCase
{
    use RefreshDatabase;

    private function sp(array $ghiDe = []): Product
    {
        return Product::factory()->for(Category::factory())->price('200000.00')->stock(10)->create($ghiDe);
    }

    private function the(Product $sp): string
    {
        return view('components.product.card', ['product' => $sp->fresh(['category', 'promotions'])])->render();
    }

    #[Test]
    public function the_hien_nhan_giam_noi_bat_va_co_qua(): void
    {
        $thuong = $this->sp();
        $this->assertStringNotContainsString('media-tags', $this->the($thuong));

        $giam = $this->sp(['is_featured' => true]);
        $km = Promotion::create(['name' => 'Giảm', 'slug' => 'giam', 'type' => 'percent', 'discount_value' => 20, 'status' => 'active', 'priority' => 0]);
        $km->products()->attach($giam->id);

        $html = $this->the($giam);
        $this->assertStringContainsString('Giảm 20%', $html);
        $this->assertStringContainsString('media-tag--noi-bat', $html);
        $this->assertStringNotContainsString('media-tag--qua', $html);

        $coQua = $this->sp();
        $vat = GiftItem::create(['name' => 'Chậu gốm', 'kind' => 'do_vat', 'stock_quantity' => 5, 'is_active' => true]);
        $qua = Promotion::create(['name' => 'Tặng chậu', 'slug' => 'tang-chau', 'type' => 'tang_qua', 'discount_value' => 0,
            'gift_item_id' => $vat->id, 'gift_quantity' => 1, 'status' => 'active', 'priority' => 0]);
        $qua->products()->attach($coQua->id);

        request()->attributes->remove('san_pham_co_qua');
        $this->assertStringContainsString('Có quà', $this->the($coQua));

        $this->get(route('shop.products.show', $coQua))
            ->assertOk()
            ->assertSee('data-qua-chuong-trinh', false)
            ->assertSee('1 × Chậu gốm');
    }

    #[Test]
    public function quan_tri_danh_dau_noi_bat_va_khach_sap_noi_bat_truoc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = $this->sp(['name' => 'Cây A thường']);
        $b = $this->sp(['name' => 'Cây B nổi bật']);
        $a->forceFill(['created_at' => now()])->save();
        $b->forceFill(['created_at' => now()->subDay()])->save();

        $this->actingAs($admin)
            ->post(route('admin.products.bulk'), ['viec' => 'noi-bat', 'ids' => [$b->id]])
            ->assertSessionHas('success');
        $this->assertTrue($b->fresh()->is_featured);

        $html = $this->get(route('shop.products.index', ['sort' => 'noi_bat']))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'Cây A thường'), strpos($html, 'Cây B nổi bật'));

        $this->get('/')->assertOk()->assertSee('data-noi-bat', false)->assertSee('Sản phẩm nổi bật');

        $this->actingAs($admin)->post(route('admin.products.bulk'), ['viec' => 'bo-noi-bat', 'ids' => [$b->id]]);
        $this->assertFalse($b->fresh()->is_featured);
    }
}
