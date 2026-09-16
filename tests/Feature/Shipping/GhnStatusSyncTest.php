<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\Shipping\GhnStatusSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Tự động ghi nhận tiền COD từ trạng thái vận đơn GHN. */
class GhnStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ghn.base_url', 'https://ghn.test/api');
        config()->set('services.ghn.token', 'token-thu');
        config()->set('services.ghn.shop_id', 1234);
    }

    private function donDangGiao(
        OrderStatus $status = OrderStatus::Shipping,
        PaymentMethod $method = PaymentMethod::Cod,
        string $shippingStatus = 'picked',
    ): Order {
        $order = Order::create([
            'order_number' => 'FP-TEST-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => $method->value,
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '25000.00',
            'coupon_discount' => '0.00',
            'grand_total' => '325000.00',
        ]);

        $order->status = $status;
        $order->payment_status = PaymentStatus::Unpaid;
        $order->ghn_order_code = 'GHN123456';
        $order->shipping_status = $shippingStatus;
        $order->save();

        return $order;
    }

    private function ghnTraVe(string $status): void
    {
        Http::fake([
            '*/v2/shipping-order/detail' => Http::response([
                'code' => 200,
                'data' => ['status' => $status],
            ]),
        ]);
    }

    #[Test]
    public function dong_bo_luu_lai_khoang_du_kien_giao(): void
    {
        $order = $this->donDangGiao();

        Http::fake([
            '*/v2/shipping-order/detail' => Http::response([
                'code' => 200,
                'data' => [
                    'status' => 'delivering',
                    'leadtime_order' => [
                        'from_estimate_date' => '2026-09-12T16:59:59Z',
                        'to_estimate_date' => '2026-09-13T16:59:59Z',
                    ],
                ],
            ]),
        ]);

        app(GhnStatusSync::class)->syncOne($order);

        $order->refresh();

        $this->assertNotNull($order->ghn_expected_from, 'Không lưu lại khoảng dự kiến giao.');
        $this->assertSame('2026-09-12', $order->ghn_expected_from->format('Y-m-d'));
        $this->assertSame('2026-09-13', $order->ghn_expected_to->format('Y-m-d'));
    }

    #[Test]
    public function du_kien_giao_van_duoc_cap_nhat_du_trang_thai_KHONG_doi(): void
    {
        $order = $this->donDangGiao(shippingStatus: 'delivering');

        Http::fake([
            '*/v2/shipping-order/detail' => Http::response([
                'code' => 200,
                'data' => [
                    'status' => 'delivering',
                    'leadtime_order' => [
                        'from_estimate_date' => '2026-09-20T16:59:59Z',
                        'to_estimate_date' => '2026-09-21T16:59:59Z',
                    ],
                ],
            ]),
        ]);

        $doiGi = app(GhnStatusSync::class)->syncOne($order);

        $this->assertFalse($doiGi);

        $this->assertSame('2026-09-20', $order->refresh()->ghn_expected_from->format('Y-m-d'));
    }

    #[Test]
    public function GHN_khong_bao_ngay_thi_de_trong_chu_khong_bia(): void
    {
        $order = $this->donDangGiao();
        $this->ghnTraVe('delivering');

        app(GhnStatusSync::class)->syncOne($order);

        $this->assertNull($order->refresh()->ghn_expected_from);
    }

    #[Test]
    public function ngay_hong_cua_GHN_khong_lam_gay_lenh_dong_bo(): void
    {
        $order = $this->donDangGiao();

        Http::fake([
            '*/v2/shipping-order/detail' => Http::response([
                'code' => 200,
                'data' => [
                    'status' => 'delivering',
                    'leadtime_order' => ['from_estimate_date' => 'khong-phai-ngay'],
                ],
            ]),
        ]);

        app(GhnStatusSync::class)->syncOne($order);

        $this->assertNull($order->refresh()->ghn_expected_from);
        $this->assertSame('delivering', $order->shipping_status);
    }

    #[Test]
    public function ghn_bao_da_giao_thi_don_cod_tu_dong_thanh_da_thanh_toan(): void
    {
        $order = $this->donDangGiao();
        $this->ghnTraVe('delivered');

        app(GhnStatusSync::class)->syncOne($order);

        $order->refresh();

        $this->assertSame('delivered', $order->shipping_status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Completed, $order->status);
    }

    #[Test]
    public function dang_tren_duong_giao_thi_tuyet_doi_khong_duoc_ghi_la_da_thu_tien(): void
    {
        $order = $this->donDangGiao(status: OrderStatus::Preparing);
        $this->ghnTraVe('delivering');

        app(GhnStatusSync::class)->syncOne($order);

        $order->refresh();

        $this->assertSame('delivering', $order->shipping_status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status, 'Chưa giao xong mà đã ghi là thu được tiền.');

        $this->assertSame(OrderStatus::Shipping, $order->status);
    }

    #[Test]
    public function ghn_bao_da_giao_khi_don_moi_chi_dang_xac_nhan_thi_di_qua_tung_buoc(): void
    {
        $order = $this->donDangGiao(status: OrderStatus::Confirmed);
        $this->ghnTraVe('delivered');

        app(GhnStatusSync::class)->syncOne($order);

        $order->refresh();

        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
    }

    #[Test]
    public function ghn_bao_huy_hoac_hoan_thi_chi_ghi_lai_chu_khong_tu_huy_don(): void
    {
        $order = $this->donDangGiao();
        $this->ghnTraVe('returned');

        app(GhnStatusSync::class)->syncOne($order);

        $order->refresh();

        $this->assertSame('returned', $order->shipping_status);
        $this->assertSame(OrderStatus::Shipping, $order->status, 'Không được tự huỷ đơn.');
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
    }

    #[Test]
    public function trang_thai_khong_doi_thi_khong_ghi_gi_va_khong_lam_ban_nhat_ky(): void
    {
        $order = $this->donDangGiao(shippingStatus: 'picked');
        $this->ghnTraVe('picked');

        $this->assertFalse(app(GhnStatusSync::class)->syncOne($order));

        $this->assertSame(
            0,
            ActivityLog::where('action', 'don-hang.dong-bo-van-don')->count(),
            'Ghi lại y nguyên giá trị cũ chỉ làm nhật ký đầy dòng vô nghĩa.',
        );
    }

    #[Test]
    public function moi_lan_doi_trang_thai_deu_ghi_nhat_ky(): void
    {
        $order = $this->donDangGiao();
        $this->ghnTraVe('delivered');

        app(GhnStatusSync::class)->syncOne($order);

        $this->assertSame(1, ActivityLog::where('action', 'don-hang.dong-bo-van-don')->count());
        $this->assertSame(1, ActivityLog::where('action', 'don-hang.tu-dong-thanh-toan')->count());
    }

    #[Test]
    public function chua_cau_hinh_ghn_thi_khong_goi_di_dau_ca(): void
    {
        config()->set('services.ghn.token', null);
        Http::fake();

        $ketQua = app(GhnStatusSync::class)->syncAll();

        $this->assertSame(['da_hoi' => 0, 'da_doi' => 0, 'loi' => 0], $ketQua);
        Http::assertNothingSent();
    }

    #[Test]
    public function khong_hoi_lai_nhung_van_don_da_di_het_duong(): void
    {
        $this->donDangGiao(shippingStatus: 'delivered');
        $this->donDangGiao(shippingStatus: 'cancel');
        $this->donDangGiao(shippingStatus: 'picked');

        $this->ghnTraVe('picked');

        $ketQua = app(GhnStatusSync::class)->syncAll();

        $this->assertSame(1, $ketQua['da_hoi'], 'Chỉ được hỏi vận đơn còn đang chạy.');
    }

    #[Test]
    public function mot_don_hong_khong_lam_dung_ca_luot(): void
    {
        $this->donDangGiao();
        $this->donDangGiao();

        Http::fake([
            '*/v2/shipping-order/detail' => Http::response(['message' => 'toang'], 500),
        ]);

        $ketQua = app(GhnStatusSync::class)->syncAll();

        $this->assertSame(2, $ketQua['da_hoi']);
        $this->assertSame(2, $ketQua['loi']);
        $this->assertSame(0, $ketQua['da_doi']);
    }
}
