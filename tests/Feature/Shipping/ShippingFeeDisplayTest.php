<?php

namespace Tests\Feature\Shipping;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Con số phí giao hiện ra khi CHƯA biết giao tới đâu. */
class ShippingFeeDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ghn.base_url', 'https://ghn.test/api');
        config()->set('services.ghn.token', 'token-thu');
        config()->set('services.ghn.shop_id', 1234);
        config()->set('services.ghn.from_district_id', 1482);

        Cache::flush();
    }

    private function themVaoGio(): Product
    {
        $product = Product::factory()
            ->for(Category::factory())
            ->price('300000.00')
            ->stock(20)
            ->create(['weight' => 500]);

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        return $product;
    }

    #[Test]
    public function chua_nhap_dia_chi_thi_phi_giao_la_khong_dong(): void
    {
        $this->actingAs(User::factory()->create());
        $this->themVaoGio();

        $gio = app(\App\Services\Checkout\CheckoutSource::class)->basket();

        $this->assertFalse($gio->hasDestination());
        $this->assertSame('0.00', $gio->baseShippingFee());
        $this->assertSame('0.00', $gio->shippingFee());
    }

    #[Test]
    public function tong_tien_khi_chua_co_dia_chi_dung_bang_tien_hang(): void
    {
        $this->actingAs(User::factory()->create());
        $this->themVaoGio();

        $gio = app(\App\Services\Checkout\CheckoutSource::class)->basket();

        $this->assertSame($gio->payableItemsTotal(), $gio->grandTotal());
    }

    #[Test]
    public function KHONG_hien_dong_mien_phi_giao_khi_chua_co_dia_chi(): void
    {
        $this->actingAs(User::factory()->create());

        $product = Product::factory()
            ->for(Category::factory())
            ->price('900000.00')
            ->stock(20)
            ->create(['weight' => 500]);

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $gio = app(\App\Services\Checkout\CheckoutSource::class)->basket();

        $this->assertSame('0.00', $gio->shippingDiscount());
    }

    #[Test]
    public function trang_gio_hang_noi_ro_phai_nhap_dia_chi(): void
    {
        $this->actingAs(User::factory()->create());
        $this->themVaoGio();

        $this->get('/gio-hang')
            ->assertOk()
            ->assertSee('nhập địa chỉ để tính phí giao');
    }

    #[Test]
    public function co_dia_chi_roi_thi_phi_hien_dung_cuoc_GHN(): void
    {
        Http::fake([
            '*/shipping-order/fee' => Http::response(['code' => 200, 'data' => ['total' => 42900]]),
            '*' => Http::response(['code' => 200, 'data' => []]),
        ]);

        $this->actingAs(User::factory()->create());
        $this->themVaoGio();

        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '12 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'shipping_district' => 'Quận Bắc Từ Liêm',
            'shipping_ward' => 'Phường Phú Diễn',
            'to_district_id' => 1482,
            'to_ward_code' => '11012',
            'payment_method' => 'cod',
            'address_id' => '',
        ])->assertRedirect();

        $gio = app(\App\Services\Checkout\CheckoutSource::class)->basket();

        $this->assertTrue($gio->hasDestination());
        $this->assertSame('42900.00', $gio->shippingFee());
    }
}
