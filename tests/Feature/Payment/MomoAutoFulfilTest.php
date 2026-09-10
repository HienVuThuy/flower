<?php

namespace Tests\Feature\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Tax\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tiền về là đơn tự chạy tiếp: xác nhận rồi bàn giao GHN.
 * ============================================================
 * VÌ SAO ĐƠN ĐÃ TRẢ TIỀN THÌ TỰ ĐỘNG, CÒN COD THÌ KHÔNG.
 *
 * Tạo vận đơn là CAM KẾT với GHN: họ cử người tới lấy hàng và tính tiền
 * cửa hàng. Với đơn COD, thứ duy nhất đứng sau lời hứa của khách là lời
 * hứa đó — nên cửa hàng phải nhìn đơn trước khi cam kết.
 *
 * Đơn đã trả tiền thì khách đã bỏ tiền ra rồi. Bắt họ đợi một nhân viên
 * bấm nút là kéo dài thời gian giao hàng vì một bước không còn tác dụng.
 *
 * Bốn bất biến được canh ở đây:
 *
 *   1. Trả xong -> đơn tự sang "Đã xác nhận" và có vận đơn.
 *   2. Người thực hiện là "Hệ thống", KHÔNG phải khách đang đăng nhập.
 *   3. GHN hỏng KHÔNG được làm hỏng việc ghi nhận tiền.
 *   4. Gọi lại lần nữa không tạo vận đơn thứ hai.
 */
class MomoAutoFulfilTest extends TestCase
{
    use RefreshDatabase;

    private const PARTNER = 'MOMOBKUN20180529';

    private const ACCESS_KEY = 'klm05TvNBzhg7h7j';

    private const SECRET = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(TaxCalculator::SETTING_KEY, '0.08');

        config([
            'payment.gateways.momo.enabled' => true,
            'payment.gateways.momo.partner_code' => self::PARTNER,
            'payment.gateways.momo.access_key' => self::ACCESS_KEY,
            'payment.gateways.momo.secret_key' => self::SECRET,
            'payment.gateways.momo.endpoint' => 'https://momo.test/create',
            'payment.gateways.momo.request_type' => 'payWithCC',
            'payment.gateways.momo.verify_ssl' => false,
            'payment.gateways.momo.redirect_url' => null,
            'payment.gateways.momo.ipn_url' => null,

            'services.ghn.base_url' => 'https://ghn.test/api',
            'services.ghn.token' => 'token-thu',
            'services.ghn.shop_id' => 1234,
            'services.ghn.from_district_id' => 1482,
        ]);
    }

    /**
     * Cả hai dịch vụ ngoài đều giả lập, TÁCH THEO ĐỊA CHỈ.
     *
     * Một stub `'*'` duy nhất sẽ trả câu của MoMo cho cả lời gọi GHN, và
     * bài kiểm thử xanh trong khi luồng thật gãy.
     */
    private function ngoaiGiaLap(bool $ghnOk = true): void
    {
        Http::fake([
            'momo.test/*' => Http::response([
                'resultCode' => 0,
                'message' => 'Successful.',
                'payUrl' => 'https://momo.test/pay?t=abc',
            ]),

            'ghn.test/*' => $ghnOk
                ? Http::response([
                    'code' => 200,
                    'message' => 'Success',
                    'data' => ['order_code' => 'GHN123456', 'total_fee' => 25000],
                ])
                : Http::response(['code' => 500, 'message' => 'GHN dang bao tri'], 500),
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

    private function datHangMomo(): Order
    {
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'to_district_id' => 1482,
            'to_ward_code' => '1A0201',
            'payment_method' => 'momo',
        ]);

        $this->post('/thanh-toan/dat-hang');

        $order = Order::latest('id')->firstOrFail();

        $this->get('/thanh-toan/momo/' . $order->order_number);

        return $order;
    }

    /**
     * @return array<string, string>
     */
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

    private function luot(): PaymentTransaction
    {
        return PaymentTransaction::where('gateway', 'momo')->latest('id')->firstOrFail();
    }

    /* ================= 1. TIỀN VỀ THÌ ĐƠN CHẠY TIẾP ================= */

    #[Test]
    public function tra_xong_thi_don_tu_sang_da_xac_nhan(): void
    {
        $this->ngoaiGiaLap();
        $order = $this->datHangMomo();

        $this->assertSame(OrderStatus::Pending, $order->status);

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($this->goiTin($this->luot())));

        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNotNull($order->confirmed_at);
    }

    #[Test]
    public function tra_xong_thi_van_don_GHN_duoc_tao_luon(): void
    {
        $this->ngoaiGiaLap();
        $order = $this->datHangMomo();

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($this->goiTin($this->luot())));

        $order->refresh();

        $this->assertSame('GHN123456', $order->ghn_order_code);
        $this->assertSame('ready_to_pick', $order->shipping_status);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/shipping-order/create'));
    }

    #[Test]
    public function GHN_khong_thu_ho_dong_nao_vi_khach_da_tra_roi(): void
    {
        /*
         * `cod_amount` phải là 0. Để nguyên số tiền đơn thì shipper thu
         * thêm một lần nữa của khách đã trả qua MoMo — và cửa hàng chỉ
         * biết khi khách gọi điện.
         */
        $this->ngoaiGiaLap();
        $order = $this->datHangMomo();

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($this->goiTin($this->luot())));

        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), '/shipping-order/create')) {
                return true;
            }

            return (int) $r->data()['cod_amount'] === 0;
        });
    }

    /* ================= 2. AI LÀ NGƯỜI THỰC HIỆN ================= */

    #[Test]
    public function moc_xac_nhan_ghi_la_He_thong_chu_khong_phai_khach(): void
    {
        /*
         * Đường callback chạy TRONG PHIÊN CỦA KHÁCH, nên Auth::id() là
         * khách. Không có cờ `tuDong` thì dòng thời gian ở trang quản
         * trị ghi "Khách thử đã xác nhận đơn" — trong khi khách không hề
         * bấm gì, và cửa hàng thì tưởng có người đã duyệt đơn này.
         */
        $this->ngoaiGiaLap();
        $order = $this->datHangMomo();

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($this->goiTin($this->luot())));

        $moc = $order->statusEvents()
            ->where('status', OrderStatus::Confirmed->value)
            ->firstOrFail();

        $this->assertNull($moc->changed_by);
        $this->assertSame('Hệ thống', $moc->actorLabel());
        $this->assertSame('Đã nhận thanh toán qua MoMo.', $moc->note);
    }

    /* ================= 3. GHN HỎNG KHÔNG LÀM HỎNG VIỆC CHÍNH ================= */

    #[Test]
    public function GHN_hong_thi_tien_van_duoc_ghi_nhan_va_don_van_xac_nhan(): void
    {
        /*
         * Tiền đã về thật. Một dịch vụ vận chuyển bảo trì không được
         * phép biến thành trang lỗi trước mặt khách vừa trả tiền, và
         * càng không được làm mất bản ghi thanh toán.
         */
        $this->ngoaiGiaLap(ghnOk: false);
        $order = $this->datHangMomo();

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($this->goiTin($this->luot())))
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNull($order->ghn_order_code, 'GHN hỏng mà vẫn ghi mã vận đơn.');
    }

    #[Test]
    public function GHN_hong_o_callback_thi_IPN_ve_sau_con_mot_co_hoi_nua(): void
    {
        /*
         * Đây là lý do bước hoàn tất chạy cả với kết quả 'already', chứ
         * không chỉ 'paid'. Không có nó thì một lần lỗi mạng là đơn nằm
         * lại chờ người thật, dù IPN đã về ngay sau đó.
         */
        /*
         * MỘT stub duy nhất, có TRẠNG THÁI.
         *
         * Gọi Http::fake() lần thứ hai chỉ THÊM stub chứ không thay cái
         * cũ, nên bản "GHN hỏng" vẫn đứng đầu hàng và trả lời mãi.
         */
        $ghnSong = false;

        Http::fake(function ($request) use (&$ghnSong) {
            if (str_contains($request->url(), 'momo.test')) {
                return Http::response([
                    'resultCode' => 0,
                    'message' => 'Successful.',
                    'payUrl' => 'https://momo.test/pay?t=abc',
                ]);
            }

            return $ghnSong
                ? Http::response([
                    'code' => 200,
                    'message' => 'Success',
                    'data' => ['order_code' => 'GHN123456', 'total_fee' => 25000],
                ])
                : Http::response(['code' => 500, 'message' => 'GHN dang bao tri'], 500);
        });

        $order = $this->datHangMomo();
        $goiTin = $this->goiTin($this->luot());

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($goiTin));
        $this->assertNull($order->refresh()->ghn_order_code);

        // GHN sống lại, IPN về.
        $ghnSong = true;
        $this->post('/thanh-toan/momo/ipn', $goiTin)->assertOk();

        $this->assertSame('GHN123456', $order->refresh()->ghn_order_code);
    }

    /* ================= 4. GỌI LẠI KHÔNG TẠO VẬN ĐƠN THỨ HAI ================= */

    #[Test]
    public function callback_va_IPN_cung_ve_thi_chi_mot_van_don(): void
    {
        /*
         * Hai vận đơn cho một đơn nghĩa là GHN cử hai chuyến xe và tính
         * tiền cả hai, còn một kiện sẽ tới nơi mà không ai chờ.
         */
        $this->ngoaiGiaLap();
        $order = $this->datHangMomo();
        $goiTin = $this->goiTin($this->luot());

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($goiTin));
        $this->post('/thanh-toan/momo/ipn', $goiTin)->assertOk();

        $soLanTao = 0;

        Http::assertSent(function ($r) use (&$soLanTao) {
            if (str_contains($r->url(), '/shipping-order/create')) {
                $soLanTao++;
            }

            return true;
        });

        $this->assertSame(1, $soLanTao, 'Đã gọi GHN tạo vận đơn nhiều hơn một lần.');
        $this->assertSame(1, $order->statusEvents()->where('status', 'confirmed')->count());
    }

    /* ================= 5. NHỮNG TRƯỜNG HỢP KHÔNG ĐƯỢC TỰ ĐỘNG ================= */

    #[Test]
    public function tra_hong_thi_KHONG_xac_nhan_va_KHONG_tao_van_don(): void
    {
        $this->ngoaiGiaLap();
        $order = $this->datHangMomo();

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query(
            $this->goiTin($this->luot(), ['resultCode' => '1006', 'message' => 'That bai'])
        ));

        $order->refresh();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->ghn_order_code);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/shipping-order/create'));
    }

    #[Test]
    public function don_da_huy_thi_KHONG_tu_xac_nhan_du_tien_ve(): void
    {
        /*
         * Khách huỷ đơn rồi mới trả tiền ở tab MoMo còn mở. Tiền về
         * thật, nhưng đơn đã huỷ thì không được sống lại — việc cần làm
         * là HOÀN TIỀN, và đó là việc của người thật.
         */
        $this->ngoaiGiaLap();
        $order = $this->datHangMomo();
        $goiTin = $this->goiTin($this->luot());

        $this->post('/don-hang/' . $order->order_number . '/huy', ['reason' => 'Đổi ý']);
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($goiTin));

        $order->refresh();

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNull($order->ghn_order_code);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/shipping-order/create'));
    }

    #[Test]
    public function huy_don_sau_khi_da_tra_thi_IPN_ve_sau_KHONG_tao_van_don(): void
    {
        /*
         * TRÌNH TỰ HIẾM NHƯNG CÓ THẬT, và là chỗ DUY NHẤT chốt chặn "đơn
         * đã huỷ" trong hoanTatSauThanhToan() thật sự có tác dụng:
         *
         *   1. khách trả tiền xong  -> đơn sang "Đã xác nhận"
         *   2. GHN đang lỗi         -> chưa có vận đơn
         *   3. khách huỷ đơn
         *   4. IPN của MoMo về muộn -> kết quả 'already'
         *
         * Bước 4 vẫn chạy tiếp phần hoàn tất (có chủ ý — xem bài "GHN
         * hỏng ở callback thì IPN về sau còn một cơ hội nữa"). Không có
         * chốt chặn thì nó bàn giao GHN một đơn vừa bị huỷ: xe tới lấy
         * hàng, cửa hàng trả cước, và hàng đi tới người không còn chờ nó.
         *
         * Bài "đơn đã huỷ thì không tự xác nhận dù tiền về" ở trên KHÔNG
         * đo được điều này: ở đó đơn huỷ TRƯỚC khi tiền về nên
         * setPaymentStatus() đã chặn từ sớm, và phần hoàn tất không bao
         * giờ chạy. Đã kiểm bằng cách bỏ chốt chặn đi — bài đó vẫn xanh.
         */
        $ghnSong = false;

        Http::fake(function ($request) use (&$ghnSong) {
            if (str_contains($request->url(), 'momo.test')) {
                return Http::response([
                    'resultCode' => 0,
                    'message' => 'Successful.',
                    'payUrl' => 'https://momo.test/pay?t=abc',
                ]);
            }

            return $ghnSong
                ? Http::response([
                    'code' => 200,
                    'message' => 'Success',
                    'data' => ['order_code' => 'GHN123456', 'total_fee' => 25000],
                ])
                : Http::response(['code' => 500, 'message' => 'GHN dang bao tri'], 500);
        });

        $order = $this->datHangMomo();
        $goiTin = $this->goiTin($this->luot());

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($goiTin));

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNull($order->ghn_order_code);

        $this->post('/don-hang/' . $order->order_number . '/huy', ['reason' => 'Đổi ý']);
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);

        // GHN sống lại, IPN về muộn.
        $ghnSong = true;
        $this->post('/thanh-toan/momo/ipn', $goiTin)->assertOk();

        $this->assertNull(
            $order->refresh()->ghn_order_code,
            'Đã bàn giao GHN một đơn vừa bị huỷ.',
        );
    }

    #[Test]
    public function don_COD_van_do_cua_hang_xac_nhan_bang_tay(): void
    {
        /*
         * KHÔNG ĐƯỢC LAN SANG COD. Tạo vận đơn cho một đơn chưa trả tiền
         * là cam kết một chuyến xe chỉ dựa trên lời hứa của khách — kể
         * cả đơn đặt nhầm, hết hàng, hay huỷ sau ba phút.
         */
        $this->ngoaiGiaLap();

        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);
        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'to_district_id' => 1482,
            'to_ward_code' => '1A0201',
            'payment_method' => 'cod',
        ]);
        $this->post('/thanh-toan/dat-hang');

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->ghn_order_code);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/shipping-order/create'));
    }
}
