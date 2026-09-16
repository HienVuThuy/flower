<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Nút tim yêu thích — bấm không tải lại trang. */
class WishlistToggleTest extends TestCase
{
    use RefreshDatabase;

    private function sanPham(): Product
    {
        return Product::factory()->for(Category::factory())->create();
    }

    #[Test]
    public function bam_lan_dau_tra_ve_active_true_lan_hai_tra_ve_false(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->sanPham();

        $this->postJson('/yeu-thich/' . $product->slug)
            ->assertOk()
            ->assertJson(['ok' => true, 'active' => true]);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $this->postJson('/yeu-thich/' . $product->slug)
            ->assertOk()
            ->assertJson(['ok' => true, 'active' => false]);

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function khong_co_javascript_van_chuyen_huong_nhu_cu(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->sanPham();

        $this->from('/san-pham')
            ->post('/yeu-thich/' . $product->slug)
            ->assertRedirect('/san-pham')
            ->assertSessionHas('success');
    }

    #[Test]
    public function khach_chua_dang_nhap_khong_luu_duoc(): void
    {
        $this->postJson('/yeu-thich/' . $this->sanPham()->slug)
            ->assertUnauthorized();

        $this->assertDatabaseCount('wishlists', 0);
    }
}
