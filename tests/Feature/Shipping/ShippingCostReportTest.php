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

/**
 * Cước ship: ai trả GHN, và cửa hàng bù bao nhiêu.
 * ============================================================
 * HAI NHÓM KIỂM THỬ, và nhóm đầu quan trọng hơn:
 *
 *   1. VẬN ĐƠN GỬI ĐI ĐÚNG. Bản đầu gửi GHN `payment_type_id = 2` —
 *      người nhận trả cước — trong khi khách đã trả phí ship cho cửa
 *      hàng. Shipper thu thêm của người nhận là thu hai lần.
 *
 *   2. BÁO CÁO KHÔNG BỊA. Chỉ tính vận đơn cửa hàng thật sự trả cước;
 *      "GHN không báo cước" là NULL chứ không phải 0₫.
 *
 * Dùng Http::fake(), không gọi GHN thật.
 */
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

    /**
     * Một đơn đã có vận đơn, với phí thu, cước GHN và người trả cho trước.
     *
     * forceFill vì `status`, `created_at` và `ghn_fee_payer` cố ý nằm ngoài
     * $fillable — đưa vào create() thì bị bỏ qua im lặng.
     */
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

    /* ================= 1. VẬN ĐƠN GỬI ĐI ĐÚNG ================= */

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
        /*
         * `grand_total` đã gồm phí ship khách trả cho cửa hàng, và shipper
         * thu hộ đúng `grand_total`. Để GHN thu cước của người nhận nữa
         * (payment_type_id = 2) là khách trả phí ship HAI LẦN.
         */
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
        $order->forceFill(['ghn_total_fee' => 64900])->save(); // báo giá lúc đặt

        app(GHNOrderService::class)->create($order);

        /*
         * NULL, và ĐÈ LÊN báo giá lúc đặt. Báo giá cũ không phải cước của
         * vận đơn này; giữ lại là để một con số đoán trông như số liệu
         * thật trong báo cáo.
         */
        $this->assertNull($order->refresh()->ghn_total_fee);
    }

    /* ================= 2. BÁO CÁO KHÔNG BỊA ================= */

    #[Test]
    public function cong_dung_tung_dong_thu_tra_va_chenh(): void
    {
        // Đơn miễn phí giao: thu 0, trả 42.900 → bù 42.900.
        $this->vanDon('0.00', 42900);
        // Tỉnh xa: thu 50.000, trả 64.900 → bù 14.900.
        $this->vanDon('50000.00', 64900);
        // Thu dư: thu 35.000, trả 30.000 → -5.000.
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
        /*
         * Với vận đơn người nhận trả, cửa hàng không trả GHN đồng nào. Tính
         * nó vào là bịa ra một khoản chi 64.900₫ không có thật.
         */
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
        /*
         * Coi NULL là 0 thì đơn này thành "thu 25.000, trả 0" — cửa hàng
         * lãi 25.000₫ tiền ship, và khoản bù của cả tháng bị kéo xuống.
         */
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
        /*
         * Tổng và bảng tháng dùng CHUNG một điều kiện lọc. Bài này bắt
         * trường hợp một bên quên loại vận đơn huỷ hay người nhận trả:
         * hai con số vẫn hiện bình thường, chỉ là không cộng lại khớp.
         */
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

    /* ================= 3. GIAO DIỆN NÓI ĐÚNG ================= */

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
        /*
         * "Thu 0₫ / trả 0₫ / bù 0₫" nói "cửa hàng không bù đồng nào" — một
         * câu khác hẳn với "không có số liệu để biết". Đây đúng là tình
         * trạng dữ liệu thật lúc sửa: 3 vận đơn, cả 3 người nhận trả.
         */
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
        /*
         * Tệp xuất ra được mở ở chỗ không có giao diện giải thích. Không
         * ghi phần bị loại vào tệp thì người đọc bảng tính tưởng tổng là
         * của cả kỳ.
         */
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
