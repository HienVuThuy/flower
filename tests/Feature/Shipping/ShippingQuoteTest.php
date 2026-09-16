<?php

namespace Tests\Feature\Shipping;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cước giao hàng: ai là người quyết định con số. */
class ShippingQuoteTest extends TestCase
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

    private function ghnBaoCuoc(int $cuoc): void
    {
        Http::fake([
            '*/shipping-order/fee' => Http::response([
                'code' => 200,
                'data' => ['total' => $cuoc],
            ]),
            '*' => Http::response(['code' => 200, 'data' => []]),
        ]);
    }

    private function sanPham(): Product
    {
        return Product::factory()
            ->for(Category::factory())
            ->price('300000.00')
            ->stock(20)
            ->create(['weight' => 500]);
    }

    private function datHang(array $them = []): Order
    {
        $product = $this->sanPham();

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/thanh-toan', array_merge([
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com',
            'shipping_address' => '12 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'shipping_district' => 'Quận Bắc Từ Liêm',
            'shipping_ward' => 'Phường Phú Diễn',
            'to_district_id' => 1482,
            'to_ward_code' => '11012',
            'payment_method' => 'cod',
            'address_id' => '',
        ], $them));

        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    #[Test]
    public function phi_giao_lay_tu_GHN_chu_khong_lay_tu_bieu_mau(): void
    {
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $order = $this->datHang([
            'shipping_fee' => 0,
            'total_price' => 1000,
            'ghn_total_fee' => 0,
            'grand_total' => 1000,
        ]);

        $this->assertSame(
            '42900.00',
            $order->shipping_fee,
            'Phí phải là con số GHN báo, không phải con số biểu mẫu gửi lên.',
        );

        $this->assertGreaterThan(
            1000,
            (float) $order->grand_total,
            'Tổng tiền phải do máy chủ cộng lại, không nhận từ trình duyệt.',
        );
    }

    #[Test]
    public function don_hang_luu_lai_ma_dia_gioi_de_con_tao_van_don(): void
    {
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $order = $this->datHang();

        $this->assertSame(1482, $order->to_district_id);
        $this->assertSame('11012', $order->to_ward_code);

        $this->assertSame('Quận Bắc Từ Liêm', $order->shipping_district);
    }

    #[Test]
    public function luu_rieng_cuoc_GHN_va_tien_thu_cua_khach(): void
    {
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $order = $this->datHang();

        $this->assertSame(42900, $order->ghn_total_fee);
    }

    #[Test]
    public function GHN_khong_tra_loi_thi_lui_ve_bang_phi_theo_tinh(): void
    {
        $this->actingAs(User::factory()->create());

        Http::fake(['*' => Http::response(['code' => 500], 500)]);

        $order = $this->datHang();

        $this->assertGreaterThan(0, (float) $order->shipping_fee,
            'Không được giao miễn phí chỉ vì GHN im lặng.');

        $this->assertNull($order->ghn_total_fee,
            'Chưa hỏi được GHN thì cước GHN là "chưa biết" (NULL), không phải 0₫.');
    }

    #[Test]
    public function dia_chi_khong_co_ma_GHN_van_dat_hang_duoc(): void
    {
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $product = $this->sanPham();
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com',
            'shipping_address' => '12 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'address_id' => '',
        ])->assertRedirect();

        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        $order = Order::latest('id')->firstOrFail();

        $this->assertNull($order->to_district_id);
        $this->assertGreaterThan(0, (float) $order->shipping_fee);
    }

    #[Test]
    public function khoi_luong_nhan_voi_so_luong(): void
    {
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $product = $this->sanPham();

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 4]);

        $this->post('/dia-gioi/tinh-cuoc', [
            'to_district_id' => 1482,
            'to_ward_code' => '11012',
        ])->assertOk();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'shipping-order/fee')) {
                return false;
            }

            return ($request->data()['weight'] ?? null) === 2000;
        });
    }

    #[Test]
    public function endpoint_bao_gia_khong_nhan_khoi_luong_tu_request(): void
    {
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $product = $this->sanPham();
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 2]);

        $this->post('/dia-gioi/tinh-cuoc', [
            'to_district_id' => 1482,
            'to_ward_code' => '11012',
            'weight' => 1,
        ])->assertOk();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'shipping-order/fee')) {
                return false;
            }

            return ($request->data()['weight'] ?? null) === 1000;
        });
    }

    #[Test]
    public function gio_rong_thi_khong_bao_gia(): void
    {
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $this->post('/dia-gioi/tinh-cuoc', [
            'to_district_id' => 1482,
            'to_ward_code' => '11012',
        ])->assertStatus(422);
    }

    #[Test]
    public function GHN_hong_thi_endpoint_noi_ro_day_la_muc_tam(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake(['*' => Http::response(['code' => 500], 500)]);

        $product = $this->sanPham();
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $res = $this->post('/dia-gioi/tinh-cuoc', [
            'to_district_id' => 1482,
            'to_ward_code' => '11012',
        ])->assertOk();

        $res->assertJsonPath('uoc_tinh', true);
        $this->assertGreaterThan(0, $res->json('data.total'));
    }

    #[Test]
    public function ten_tinh_cua_GHN_duoc_chap_nhan_du_khong_co_trong_danh_sach_tinh(): void
    {
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $product = $this->sanPham();
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '12 Đường Thử',
            'shipping_province' => 'Hòa Bình',
            'shipping_district' => 'Thành phố Hòa Bình',
            'shipping_ward' => 'Phường Phương Lâm',
            'to_district_id' => 1566,
            'to_ward_code' => '210101',
            'payment_method' => 'cod',
            'address_id' => '',
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function nhap_tay_thi_VAN_kiem_theo_danh_sach_tinh(): void
    {
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $product = $this->sanPham();
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '12 Đường Thử',
            'shipping_province' => 'Tỉnh Không Có Thật',
            'payment_method' => 'cod',
            'address_id' => '',
        ])->assertSessionHasErrors('shipping_province');
    }
}