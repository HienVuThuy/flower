<?php

namespace Tests\Feature\Payment;

use App\Enums\MomoFlow;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\User;
use App\Services\Payment\MomoGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Thanh toán MoMo. */
class MomoPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const PARTNER = 'MOMOBKUN20180529';

    private const ACCESS_KEY = 'klm05TvNBzhg7h7j';

    private const SECRET = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.gateways.momo.enabled' => true,
            'payment.gateways.momo.partner_code' => self::PARTNER,
            'payment.gateways.momo.access_key' => self::ACCESS_KEY,
            'payment.gateways.momo.secret_key' => self::SECRET,
            'payment.gateways.momo.endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
            'payment.gateways.momo.request_type' => 'payWithCC',
            'payment.gateways.momo.verify_ssl' => false,
            'payment.gateways.momo.redirect_url' => null,
            'payment.gateways.momo.ipn_url' => null,
        ]);
    }

    private function hang(string $gia = '500000.00'): Product
    {
        return Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price($gia)
            ->stock(20)
            ->create(['weight' => 500]);
    }

    private function momoNhan(): void
    {
        Http::fake([
            '*' => Http::response([
                'resultCode' => 0,
                'message' => 'Successful.',
                'payUrl' => 'https://test-payment.momo.vn/v2/gateway/pay?t=abc',
            ]),
        ]);
    }

    private function datHangMomo(string $gia = '500000.00'): Order
    {
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang($gia)->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'momo',
        ]);

        $this->post('/thanh-toan/dat-hang');

        return Order::latest('id')->firstOrFail();
    }

    private function goiTin(PaymentTransaction $tx, array $ghiDe = []): array
    {
        $payload = array_merge([
            'partnerCode' => self::PARTNER,
            'orderId' => (string) $tx->gateway_order_id,
            'requestId' => (string) $tx->gateway_order_id,
            'amount' => (string) (int) round((float) $tx->amount),
            'orderInfo' => 'Thanh toan don hang',
            'orderType' => 'momo_wallet',
            'transId' => '2609260001',
            'resultCode' => '0',
            'message' => 'Successful.',
            'payType' => 'napas',
            'responseTime' => '1758800000000',
            'extraData' => (string) $tx->order->order_number,
        ], $ghiDe);

        $rawHash = 'accessKey=' . self::ACCESS_KEY
            . '&amount=' . $payload['amount']
            . '&extraData=' . $payload['extraData']
            . '&message=' . $payload['message']
            . '&orderId=' . $payload['orderId']
            . '&orderInfo=' . $payload['orderInfo']
            . '&orderType=' . $payload['orderType']
            . '&partnerCode=' . $payload['partnerCode']
            . '&payType=' . $payload['payType']
            . '&requestId=' . $payload['requestId']
            . '&responseTime=' . $payload['responseTime']
            . '&resultCode=' . $payload['resultCode']
            . '&transId=' . $payload['transId'];

        $payload['signature'] = hash_hmac('sha256', $rawHash, self::SECRET);

        return $payload;
    }

    #[Test]
    public function momo_hien_ra_o_buoc_thanh_toan_khi_da_cau_hinh(): void
    {
        $this->assertContains('momo', PaymentMethod::values());
    }

    #[Test]
    public function momo_KHONG_hien_ra_khi_thieu_khoa(): void
    {
        config(['payment.gateways.momo.enabled' => false]);

        $this->assertNotContains('momo', PaymentMethod::values());
    }

    #[Test]
    public function dat_don_momo_thi_chuyen_thang_sang_cong(): void
    {
        $this->momoNhan();

        $order = $this->datHangMomo();

        $this->assertSame(PaymentMethod::Momo, $order->payment_method);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);

        $this->get('/thanh-toan/momo/' . $order->order_number)
            ->assertRedirect('https://test-payment.momo.vn/v2/gateway/pay?t=abc');
    }

    #[Test]
    public function yeu_cau_gui_sang_momo_mang_du_tham_so_va_dung_chu_ky(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        Http::assertSent(function ($request) {
            $d = $request->data();

            $rawHash = 'accessKey=' . self::ACCESS_KEY
                . '&amount=' . $d['amount']
                . '&extraData=' . $d['extraData']
                . '&ipnUrl=' . $d['ipnUrl']
                . '&orderId=' . $d['orderId']
                . '&orderInfo=' . $d['orderInfo']
                . '&partnerCode=' . $d['partnerCode']
                . '&redirectUrl=' . $d['redirectUrl']
                . '&requestId=' . $d['requestId']
                . '&requestType=' . $d['requestType'];

            return $d['partnerCode'] === self::PARTNER
                && $d['requestType'] === config('payment.gateways.momo.request_type')
                && $d['amount'] === '500000'
                && $d['signature'] === hash_hmac('sha256', $rawHash, self::SECRET);
        });
    }

    #[Test]
    public function chu_ky_KHONG_bi_luu_vao_co_so_du_lieu(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();

        $this->assertArrayNotHasKey('signature', $tx->request_payload);
    }

    #[Test]
    public function momo_tu_choi_thi_khach_ve_trang_don_kem_ly_do(): void
    {
        Http::fake(['*' => Http::response(['resultCode' => 99, 'message' => 'Bad format'])]);

        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);
        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'momo',
        ]);
        $this->post('/thanh-toan/dat-hang');

        $order = Order::latest('id')->firstOrFail();

        $this->get('/thanh-toan/momo/' . $order->order_number)
            ->assertRedirect(route('shop.orders.show', $order))
            ->assertSessionHas('error');

        $this->assertSame(
            PaymentTransactionStatus::Failed,
            PaymentTransaction::where('gateway', 'momo')->firstOrFail()->status,
        );
    }

    #[Test]
    public function chon_quet_QR_thi_mo_dung_dich_vu_vi_MoMo(): void
    {
        $this->momoNhan();

        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'momo',
            'momo_flow' => 'vi',
        ]);

        $order = tap($this->post('/thanh-toan/dat-hang'), function ($res) {
            $res->assertRedirect();
        }) && Order::latest('id')->firstOrFail();

        $order = Order::latest('id')->firstOrFail();

        $this->get('/thanh-toan/momo/' . $order->order_number . '?cach=vi');

        Http::assertSent(fn ($r) => $r->data()['requestType'] === 'captureWallet');
    }

    #[Test]
    public function duong_chuyen_huong_sau_khi_dat_hang_mang_theo_lua_chon(): void
    {
        $this->momoNhan();

        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'momo',
            'momo_flow' => 'vi',
        ]);

        $order = null;

        $res = $this->post('/thanh-toan/dat-hang');
        $order = Order::latest('id')->firstOrFail();

        $res->assertRedirect(route('shop.payment.momo.start', [$order, 'cach' => 'vi']));
    }

    #[Test]
    public function chon_the_quoc_te_thi_mo_dung_dich_vu_the(): void
    {
        $this->momoNhan();

        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'momo',
            'momo_flow' => 'the',
        ]);

        $res = $this->post('/thanh-toan/dat-hang');
        $order = Order::latest('id')->firstOrFail();

        $res->assertRedirect(route('shop.payment.momo.start', [$order, 'cach' => 'the']));
    }

    #[Test]
    public function khong_gui_momo_flow_thi_dung_muc_mac_dinh_chu_khong_chan_don(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();

        $this->assertSame(PaymentMethod::Momo, $order->payment_method);

        $this->get('/thanh-toan/momo/' . $order->order_number);

        Http::assertSent(fn ($r) => $r->data()['requestType'] === MomoFlow::macDinh()->requestType());
    }

    #[Test]
    public function tham_so_cach_la_tren_URL_thi_lui_ve_mac_dinh_chu_khong_404(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();

        $this->get('/thanh-toan/momo/' . $order->order_number . '?cach=khong-co-that')
            ->assertRedirect('https://test-payment.momo.vn/v2/gateway/pay?t=abc');

        Http::assertSent(fn ($r) => $r->data()['requestType'] === MomoFlow::macDinh()->requestType());
    }

    #[Test]
    public function buoc_thanh_toan_moi_khach_chon_cach_tra_tien(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->get('/thanh-toan')
            ->assertOk()
            ->assertSee('Quét mã QR bằng ứng dụng MoMo')
            ->assertSee('Thẻ quốc tế (Visa, Mastercard, JCB)')
            ->assertSee('name="momo_flow"', escape: false);
    }

    #[Test]
    public function callback_SAI_CHU_KY_thi_don_khong_doi(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();
        $payload = $this->goiTin($tx, ['resultCode' => '0']);
        $payload['signature'] = 'chu-ky-gia';

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($payload))
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertSame(PaymentTransactionStatus::Initiated, $tx->fresh()->status);
    }

    #[Test]
    public function callback_thieu_chu_ky_cung_bi_vut(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();
        $payload = $this->goiTin($tx);
        unset($payload['signature']);

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($payload));

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    #[Test]
    public function callback_dung_chu_ky_va_thanh_cong_thi_don_duoc_ghi_da_tra(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($this->goiTin($tx)))
            ->assertRedirect(route('shop.orders.show', $order))
            ->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        $tx->refresh();
        $this->assertSame(PaymentTransactionStatus::Paid, $tx->status);
        $this->assertSame('2609260001', $tx->transaction_id);
        $this->assertNotNull($tx->paid_at);
    }

    #[Test]
    public function chu_ky_dung_nhung_resultCode_khac_0_thi_van_chua_tra(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query(
            $this->goiTin($tx, ['resultCode' => '1006', 'message' => 'Giao dich bi tu choi'])
        ))->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertSame(PaymentTransactionStatus::Failed, $tx->fresh()->status);
    }

    #[Test]
    public function so_tien_lech_thi_KHONG_ghi_nhan(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query(
            $this->goiTin($tx, ['amount' => '1000'])
        ))->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertSame(PaymentTransactionStatus::Failed, $tx->fresh()->status);
    }

    #[Test]
    public function duong_ipn_duoc_mien_kiem_csrf(): void
    {
        $mien = (new \ReflectionClass(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class))
            ->getStaticPropertyValue('neverVerify');

        $this->assertContains('thanh-toan/momo/ipn', $mien);

        $this->assertCount(1, $mien);
    }

    #[Test]
    public function ipn_dung_chu_ky_thi_ghi_nhan_thanh_toan(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();

        $this->post('/thanh-toan/momo/ipn', $this->goiTin($tx))
            ->assertOk()
            ->assertJson(['message' => 'Received']);

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    #[Test]
    public function ipn_sai_chu_ky_van_tra_200_nhung_khong_ghi_gi(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();
        $payload = $this->goiTin($tx);
        $payload['signature'] = 'gia';

        $this->post('/thanh-toan/momo/ipn', $payload)->assertOk();

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    #[Test]
    public function callback_va_ipn_cung_ve_thi_chi_ghi_mot_lan(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();
        $payload = $this->goiTin($tx);

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($payload));
        $this->post('/thanh-toan/momo/ipn', $payload)->assertOk();

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame(1, PaymentTransaction::where('status', 'paid')->count());

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($payload))
            ->assertSessionHas('success')
            ->assertSessionMissing('error');
    }

    #[Test]
    public function thanh_toan_lai_them_luot_giao_dich_chu_KHONG_them_don(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();
        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query(
            $this->goiTin($tx, ['resultCode' => '1006', 'message' => 'That bai'])
        ));

        $this->get('/don-hang/' . $order->order_number . '/thanh-toan-momo')
            ->assertRedirect('https://test-payment.momo.vn/v2/gateway/pay?t=abc');

        $this->assertSame(1, Order::count());
        $this->assertSame(2, PaymentTransaction::where('gateway', 'momo')->count());
    }

    #[Test]
    public function moi_luot_mang_ma_gateway_khac_nhau(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();

        $this->get('/thanh-toan/momo/' . $order->order_number);
        $this->get('/don-hang/' . $order->order_number . '/thanh-toan-momo');

        $ma = PaymentTransaction::where('gateway', 'momo')->pluck('gateway_order_id');

        $this->assertCount(2, $ma->unique());
    }

    #[Test]
    public function nut_thanh_toan_lai_hien_o_trang_don_chua_tra(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Quét mã QR bằng ứng dụng MoMo')
            ->assertSee('Thẻ quốc tế (Visa, Mastercard, JCB)');
    }

    #[Test]
    public function don_da_tra_KHONG_con_nut_thanh_toan_lai(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();
        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($this->goiTin($tx)));

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertDontSee('Quét mã QR bằng ứng dụng MoMo');
    }

    #[Test]
    public function khong_tra_tien_ho_don_cua_nguoi_khac_duoc(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();

        $this->flushSession();
        $this->actingAs(User::factory()->create());

        $this->get('/thanh-toan/momo/' . $order->order_number)->assertForbidden();
        $this->get('/don-hang/' . $order->order_number . '/thanh-toan-momo')->assertForbidden();
    }

    #[Test]
    public function don_COD_van_ve_thang_trang_don_va_co_mot_dong_giao_dich(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);
        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
        ]);

        $order = Order::query();

        $this->post('/thanh-toan/dat-hang');

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(PaymentMethod::Cod, $order->payment_method);
        $this->assertSame(1, PaymentTransaction::where('gateway', 'cod')->count());
        $this->assertSame(0, PaymentTransaction::where('gateway', 'momo')->count());
    }

    #[Test]
    public function don_COD_khong_dung_duoc_duong_thanh_toan_momo(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);
        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
        ]);
        $this->post('/thanh-toan/dat-hang');

        $order = Order::latest('id')->firstOrFail();

        $this->get('/thanh-toan/momo/' . $order->order_number)
            ->assertRedirect(route('shop.orders.show', $order))
            ->assertSessionHas('error');

        $this->assertSame(0, PaymentTransaction::where('gateway', 'momo')->count());
    }

    #[Test]
    public function huy_giao_dich_o_momo_thi_trang_don_KHONG_bao_thanh_cong(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query(
            $this->goiTin($tx, ['resultCode' => '1006', 'message' => 'Giao dich bi tu choi boi nguoi dung.'])
        ));

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Đơn hàng chưa thanh toán')
            ->assertDontSee('Đã nhận đơn hàng của bạn');
    }

    #[Test]
    public function tra_xong_thi_tieu_de_quay_ve_bao_da_nhan_don(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();
        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($this->goiTin($tx)));

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Đã nhận đơn hàng của bạn')
            ->assertDontSee('Đơn hàng chưa thanh toán');
    }

    #[Test]
    public function don_COD_chua_tra_van_bao_da_nhan_don(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);
        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
        ]);
        $this->post('/thanh-toan/dat-hang');

        $order = Order::latest('id')->firstOrFail();

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Đã nhận đơn hàng của bạn')
            ->assertDontSee('Đơn hàng chưa thanh toán');
    }

    #[Test]
    public function don_da_huy_khong_con_bao_da_nhan_don(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();

        $this->post('/don-hang/' . $order->order_number . '/huy', ['reason' => 'Đổi ý']);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Đơn hàng đã huỷ')
            ->assertDontSee('Đã nhận đơn hàng của bạn')
            ->assertDontSee('Thanh toán lại với MoMo');
    }

    #[Test]
    public function gui_di_dung_requestType_dang_cau_hinh(): void
    {
        config(['payment.gateways.momo.request_type' => 'payWithCC']);

        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        Http::assertSent(fn ($request) => $request->data()['requestType'] === 'payWithCC');
    }

    #[Test]
    public function admin_doc_duoc_tung_luot_thanh_toan(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();
        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query(
            $this->goiTin($tx, ['resultCode' => '1006', 'message' => 'The bi khoa'])
        ));

        $this->get('/don-hang/' . $order->order_number . '/thanh-toan-momo');

        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        $this->actingAs($admin)
            ->get('/admin/orders/' . $order->order_number)
            ->assertOk()
            ->assertSee('Lượt thanh toán')
            ->assertSee('The bi khoa')
            ->assertSee('Thất bại')
            ->assertSee('Đã chuyển sang cổng');
    }

    #[Test]
    public function gateway_bao_chua_cau_hinh_khi_thieu_khoa(): void
    {
        config(['payment.gateways.momo.enabled' => false]);

        $this->assertFalse(app(MomoGateway::class)->configured());
    }
}
