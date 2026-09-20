<?php

namespace Tests\Feature\Catalog;

use App\Mail\StockAlertMail;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** "Báo tôi khi có hàng": đăng ký lúc hết hàng, báo đúng một lần khi hàng về. */
class BaoHangVeTest extends TestCase
{
    use RefreshDatabase;

    private function sp(int $ton = 0): Product
    {
        return Product::factory()->for(Category::factory())->price('180000.00')->stock($ton)->create(['status' => 'active']);
    }

    #[Test]
    public function het_hang_thi_dang_ky_duoc_va_hang_ve_thi_bao_mot_lan(): void
    {
        Mail::fake();
        $sp = $this->sp(0);
        $khach = User::factory()->create();

        $this->actingAs($khach)
            ->post(route('shop.stock-alerts.store', $sp))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('stock_alerts', ['user_id' => $khach->id, 'product_id' => $sp->id, 'notified_at' => null]);

        $sp->update(['stock_quantity' => 4]);

        $this->assertNotNull(StockAlert::sole()->notified_at);
        $this->assertSame(1, UserNotification::where('user_id', $khach->id)->where('type', 'hang_ve')->count());
        Mail::assertSent(StockAlertMail::class, 1);
        $this->assertSame(
            route('shop.products.show', $sp),
            UserNotification::where('type', 'hang_ve')->sole()->duongDan(),
        );

        $sp->update(['stock_quantity' => 0]);
        $sp->update(['stock_quantity' => 9]);

        Mail::assertSent(StockAlertMail::class, 1);
        $this->assertSame(1, UserNotification::where('type', 'hang_ve')->count(), 'Một lượt đăng ký chỉ báo một lần');
    }

    #[Test]
    public function con_hang_thi_khong_dang_ky_va_huy_duoc_luc_dang_cho(): void
    {
        $conHang = $this->sp(5);
        $khach = User::factory()->create();

        $this->actingAs($khach)
            ->post(route('shop.stock-alerts.store', $conHang))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('stock_alerts', 0);

        $het = $this->sp(0);
        $this->actingAs($khach)->post(route('shop.stock-alerts.store', $het));
        $this->assertDatabaseCount('stock_alerts', 1);

        $this->actingAs($khach)->delete(route('shop.stock-alerts.destroy', $het))->assertSessionHas('success');
        $this->assertDatabaseCount('stock_alerts', 0);
    }

    #[Test]
    public function quy_cach_het_hang_bao_rieng_theo_quy_cach(): void
    {
        Mail::fake();
        $sp = $this->sp(0);
        $qc = $sp->variants()->create(['name' => 'Chậu 18cm', 'is_active' => true, 'track_inventory' => true, 'stock_quantity' => 0]);
        $khach = User::factory()->create();

        $this->actingAs($khach)
            ->post(route('shop.stock-alerts.store', $sp), ['product_variant_id' => $qc->id])
            ->assertSessionHas('success');

        $qc->update(['stock_quantity' => 3]);

        $this->assertNotNull(StockAlert::sole()->notified_at);
        Mail::assertSent(StockAlertMail::class, 1);
    }

    #[Test]
    public function trang_san_pham_moi_dang_ky_dung_luc(): void
    {
        $het = $this->sp(0);
        $con = $this->sp(7);

        $this->get(route('shop.products.show', $het))->assertOk()->assertSee('Đăng nhập');
        $this->get(route('shop.products.show', $con))->assertOk()->assertDontSee('Báo tôi khi có hàng');

        $this->actingAs(User::factory()->create())
            ->get(route('shop.products.show', $het))
            ->assertOk()
            ->assertSee('Báo tôi khi có hàng');
    }
}
