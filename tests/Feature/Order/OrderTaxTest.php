<?php

namespace Tests\Feature\Order;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Tax\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Thuế GTGT: TÁCH ra từ tổng, không cộng thêm vào. */
class OrderTaxTest extends TestCase
{
    use RefreshDatabase;

    private function datHang(string $gia = '100000.00'): Order
    {
        $product = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price($gia)
            ->stock(20)
            ->create(['weight' => 500]);

        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
        ]);

        $this->post('/thanh-toan/dat-hang');

        return Order::latest('id')->firstOrFail();
    }

    #[Test]
    public function thue_duoc_tach_ra_tu_tong_chu_khong_cong_them_vao(): void
    {
        Setting::set(TaxCalculator::SETTING_KEY, '0.08');

        $order = $this->datHang('100000.00');

        $tong = (float) $order->grand_total;
        $thue = (float) $order->tax_amount;

        $this->assertEqualsWithDelta($tong, ($tong - $thue) * 1.08, 0.02);

        $this->assertLessThan($tong * 0.08, $thue);
    }

    #[Test]
    public function bat_thue_len_khong_lam_khach_phai_tra_them_dong_nao(): void
    {
        Setting::set(TaxCalculator::SETTING_KEY, '0');
        $khongThue = $this->datHang('250000.00');

        Setting::set(TaxCalculator::SETTING_KEY, '0.10');
        $coThue = $this->datHang('250000.00');

        $this->assertSame(
            $khongThue->grand_total,
            $coThue->grand_total,
            'Bật thuế lên đã làm đổi số tiền khách phải trả.',
        );

        $this->assertSame('0.00', $khongThue->tax_amount);
        $this->assertGreaterThan(0, (float) $coThue->tax_amount);
    }

    #[Test]
    public function thue_suat_duoc_chup_vao_don_de_don_cu_khong_doi_theo(): void
    {
        Setting::set(TaxCalculator::SETTING_KEY, '0.08');
        $order = $this->datHang();

        $thueLucDat = $order->tax_amount;

        Setting::set(TaxCalculator::SETTING_KEY, '0.10');

        $order->refresh();

        $this->assertSame($thueLucDat, $order->tax_amount);
        $this->assertSame('0.08000', $order->tax_rate);
    }

    #[Test]
    public function o_thue_suat_bo_trong_khong_bien_thanh_mien_thue(): void
    {
        Setting::set(TaxCalculator::SETTING_KEY, null);

        $this->assertSame(
            (string) (float) config('tax.default_rate'),
            app(TaxCalculator::class)->rate(),
        );
    }

    #[Test]
    public function khach_nhin_thay_phan_thue_nam_trong_tong(): void
    {
        Setting::set(TaxCalculator::SETTING_KEY, '0.08');
        $order = $this->datHang();

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Trong đó thuế GTGT')
            ->assertSee('8%');
    }

    #[Test]
    public function admin_nhin_thay_thue_kem_muc_da_ap(): void
    {
        Setting::set(TaxCalculator::SETTING_KEY, '0.08');
        $order = $this->datHang();

        $admin = User::factory()->create();
        $admin->role = \App\Enums\UserRole::Admin;
        $admin->save();

        $this->actingAs($admin)
            ->get('/admin/orders/' . $order->order_number)
            ->assertOk()
            ->assertSee('Trong đó thuế VAT')
            ->assertSee('8%');
    }

    #[Test]
    public function don_cu_khong_co_so_lieu_thue_thi_khong_hien_gi(): void
    {
        Setting::set(TaxCalculator::SETTING_KEY, '0.08');
        $order = $this->datHang();

        $order->forceFill(['tax_rate' => null, 'tax_amount' => null])->saveQuietly();

        $admin = User::factory()->create();
        $admin->role = \App\Enums\UserRole::Admin;
        $admin->save();

        $this->actingAs($admin)
            ->get('/admin/orders/' . $order->order_number)
            ->assertOk()
            ->assertDontSee('Trong đó thuế VAT');
    }
}
