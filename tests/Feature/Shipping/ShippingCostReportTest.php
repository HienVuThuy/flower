<?php

namespace Tests\Feature\Shipping;

use App\Enums\GhnFeePayer;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Services\Shipping\GHNOrderService;
use App\Services\Shop\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cước ship: ai trả GHN, và cửa hàng bù bao nhiêu. */
class ShippingCostReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ghn.base_url', 'https://ghn.test/api');
        config()->set('services.ghn.token', 'token-thu');
        config()->set('services.ghn.shop_id', 1234);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function vanDon(
        string $thu,
        ?int $tra,
        GhnFeePayer $nguoiTra = GhnFeePayer::Shop,
        string $trangThaiGhn = 'delivered',
        int $ngayTruoc = 2,
        string $tinh = 'Thành phố Hà Nội',
    ): Order {
        $order = Order::create([
            'order_number' => 'FP-SHIP-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => $tinh,
            'payment_method' => 'cod',
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => $thu,
            'coupon_discount' => '0.00',
            'grand_total' => bcadd('300000.00', $thu, 2),
            'ghn_order_code' => 'GHN' . strtoupper(bin2hex(random_bytes(3))),
            'ghn_total_fee' => $tra,
            'shipping_status' => $trangThaiGhn,
        ]);

        $order->forceFill([
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'ghn_fee_payer' => $nguoiTra,
            'created_at' => now()->subDays($ngayTruoc),
        ])->save();

        return $order;
    }

    private function bao(string $ky = '30'): AnalyticsService
    {
        return app(AnalyticsService::class)->forPeriod($ky);
    }

    private function donChoTaoVanDon(): Order
    {
        $order = Order::create([
            'order_number' => 'FP-SHIP-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '25000.00',
            'coupon_discount' => '0.00',
            'grand_total' => '325000.00',
            'to_district_id' => 1482,
            'to_ward_code' => '11012',
        ]);

        $order->forceFill(['status' => OrderStatus::Confirmed])->save();

        return $order;
    }

    #[Test]
    public function van_don_gui_GHN_voi_CUA_HANG_tra_cuoc(): void
    {
        Http::fake([
            '*/v2/shipping-order/create' => Http::response([
                'code' => 200,
                'data' => ['order_code' => 'GHNABC', 'total_fee' => 38500],
            ]),
        ]);

        $order = $this->donChoTaoVanDon();

        app(GHNOrderService::class)->create($order);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/shipping-order/create')
            && (int) $r->data()['payment_type_id'] === 1);

        $order->refresh();

        $this->assertSame(GhnFeePayer::Shop, $order->ghn_fee_payer,
            'Phải chụp lại người trả cước đúng như đã gửi GHN.');
        $this->assertSame(38500, $order->ghn_total_fee);
    }

    #[Test]
    public function GHN_khong_bao_cuoc_thi_luu_NULL_khong_phai_0(): void
    {
        Http::fake([
            '*/v2/shipping-order/create' => Http::response([
                'code' => 200,
                'data' => ['order_code' => 'GHNABC'],
            ]),
        ]);

        $order = $this->donChoTaoVanDon();
        $order->forceFill(['ghn_total_fee' => 64900])->save();

        app(GHNOrderService::class)->create($order);

        $this->assertNull($order->refresh()->ghn_total_fee);
    }

    #[Test]
    public function cong_dung_tung_dong_thu_tra_va_chenh(): void
    {
        $this->vanDon('0.00', 42900);
        $this->vanDon('50000.00', 64900);
        $this->vanDon('35000.00', 30000);

        $r = $this->bao()->shippingCost();

        $this->assertSame(3, $r['tinh_duoc']);
        $this->assertSame('85000.00', $r['thu']);
        $this->assertSame('137800.00', $r['tra']);
        $this->assertSame('52800.00', $r['chenh']);
        $this->assertSame(1, $r['mien_phi']);
    }

    #[Test]
    public function van_don_NGUOI_NHAN_tra_cuoc_khong_tinh_vao_khoan_bu(): void
    {
        $this->vanDon('50000.00', 64900, GhnFeePayer::Buyer);

        $r = $this->bao()->shippingCost();

        $this->assertSame(1, $r['van_don']);
        $this->assertSame(0, $r['tinh_duoc']);
        $this->assertSame('0.00', $r['tra']);
        $this->assertSame(1, $r['loai']['nguoi_nhan_tra']);
    }

    #[Test]
    public function van_don_da_huy_khong_tinh_cuoc(): void
    {
        $this->vanDon('25000.00', 42900, trangThaiGhn: 'cancel');

        $r = $this->bao()->shippingCost();

        $this->assertSame(0, $r['tinh_duoc']);
        $this->assertSame(1, $r['loai']['da_huy']);
    }

    #[Test]
    public function thieu_so_lieu_cuoc_KHONG_bi_coi_la_0d(): void
    {
        $this->vanDon('50000.00', 64900);
        $this->vanDon('25000.00', null);

        $r = $this->bao()->shippingCost();

        $this->assertSame(1, $r['tinh_duoc']);
        $this->assertSame('14900.00', $r['chenh']);
        $this->assertSame(1, $r['loai']['thieu_cuoc']);
    }

    #[Test]
    public function chi_tinh_don_trong_ky(): void
    {
        $this->vanDon('0.00', 42900, ngayTruoc: 3);
        $this->vanDon('0.00', 99000, ngayTruoc: 20);

        $this->assertSame('42900.00', $this->bao('7')->shippingCost()['tra']);
        $this->assertSame('141900.00', $this->bao('30')->shippingCost()['tra']);
    }

    #[Test]
    public function bang_thang_cong_lai_dung_bang_tong(): void
    {
        $this->vanDon('0.00', 42900, ngayTruoc: 2);
        $this->vanDon('50000.00', 64900, ngayTruoc: 40);
        $this->vanDon('25000.00', 30000, GhnFeePayer::Buyer, ngayTruoc: 5);
        $this->vanDon('25000.00', 30000, trangThaiGhn: 'cancel', ngayTruoc: 6);

        $bao = $this->bao('all');
        $tong = $bao->shippingCost();
        $thang = $bao->shippingCostByMonth();

        $cong = '0.00';
        foreach ($thang as $m) {
            $cong = bcadd($cong, $m['chenh'], 2);
        }

        $this->assertSame($tong['chenh'], $cong);
        $this->assertSame($tong['tinh_duoc'], $thang->sum('don'));
    }

    #[Test]
    public function danh_sach_bu_ship_xep_giam_dan_va_bo_don_thu_du(): void
    {
        $it = $this->vanDon('50000.00', 64900);
        $nhieu = $this->vanDon('0.00', 42900);
        $thuDu = $this->vanDon('35000.00', 30000);

        $ds = $this->bao()->shippingSubsidies();

        $this->assertSame(
            [$nhieu->order_number, $it->order_number],
            $ds->map(fn ($d) => $d['order']->order_number)->all(),
        );
        $this->assertSame('42900.00', $ds->first()['chenh']);
    }

    #[Test]
    public function trang_phan_tich_hien_khoan_bu_bang_chu(): void
    {
        $this->vanDon('0.00', 42900);

        $this->actingAs($this->admin())
            ->get('/admin/phan-tich?ky=30')
            ->assertOk()
            ->assertSee('Cửa hàng bù ship')
            ->assertSee(Money::format('42900'));
    }

    #[Test]
    public function chi_co_van_don_nguoi_nhan_tra_thi_KHONG_in_bu_0d(): void
    {
        $this->vanDon('50000.00', 64900, GhnFeePayer::Buyer);

        $this->actingAs($this->admin())
            ->get('/admin/phan-tich?ky=30')
            ->assertOk()
            ->assertSee('chưa vận đơn nào tính được khoản bù')
            ->assertSee('người nhận trả cước')
            ->assertDontSee('Cửa hàng bù ship');
    }

    #[Test]
    public function noi_ro_khi_dang_dung_cong_thu_cua_GHN(): void
    {
        $this->vanDon('0.00', 42900);

        config()->set('services.ghn.base_url', 'https://dev-online-gateway.ghn.vn/shiip/public-api');

        $this->actingAs($this->admin())
            ->get('/admin/phan-tich?ky=30')
            ->assertSee('cổng thử');
    }

    #[Test]
    public function trang_don_canh_bao_van_don_nguoi_nhan_tra_cuoc(): void
    {
        $order = $this->vanDon('50000.00', 64900, GhnFeePayer::Buyer);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('người nhận trả cước cho GHN')
            ->assertDontSee('cửa hàng bù');
    }

    #[Test]
    public function trang_don_noi_chua_co_so_lieu_cuoc_thay_vi_0d(): void
    {
        $order = $this->vanDon('25000.00', null);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Cước GHN: chưa có số liệu');
    }

    #[Test]
    public function tep_xuat_ghi_ca_so_van_don_bi_loai(): void
    {
        $this->vanDon('0.00', 42900);
        $this->vanDon('25000.00', 30000, GhnFeePayer::Buyer);

        $csv = $this->actingAs($this->admin())
            ->get('/admin/phan-tich/xuat/tai-ve?' . http_build_query(['ky' => '30', 'dinh_dang' => 'csv', 'phan' => ['van-chuyen']]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Không tính: người nhận trả cước', $csv);
        $this->assertStringContainsString('42900.00', $csv);
    }
}
