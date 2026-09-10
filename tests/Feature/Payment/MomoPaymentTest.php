<?php

namespace Tests\Feature\Payment;

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

/**
 * Thanh toán MoMo.
 * ============================================================
 * ĐIỀU QUAN TRỌNG NHẤT: đơn CHỈ được ghi "đã thanh toán" từ một gói tin
 * ĐÚNG CHỮ KÝ. Đường quay về của trình duyệt là một URL ai cũng gõ được;
 * nếu chỉ cần khách có mặt ở đó là đủ thì bất kỳ ai cũng tự đánh dấu đơn
 * của mình đã trả tiền.
 *
 * Các bất biến được canh ở đây, mỗi cái là một cách hỏng:
 *
 *   1. Sai chữ ký  -> vứt, đơn không đổi.
 *   2. resultCode khác 0 -> lượt giao dịch hỏng, đơn vẫn chưa trả.
 *   3. Số tiền lệch -> không ghi nhận.
 *   4. Callback và IPN cùng về -> chỉ ghi một lần.
 *   5. Trả lại lần hai -> thêm lượt giao dịch, KHÔNG thêm đơn.
 *   6. Đơn của người khác -> 403.
 */
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
            'payment.gateways.momo.request_type' => 'payWithATM',
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

    /** MoMo nhận yêu cầu và trả về một đường dẫn thanh toán. */
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

    /**
     * Gói tin MoMo gửi về, ký đúng như MoMo ký.
     *
     * Ký ngay trong bài kiểm thử chứ không gọi lớp cần kiểm: dùng chính
     * hàm của lớp đó để dựng dữ liệu thì bài luôn xanh, kể cả khi công
     * thức ký sai — nó chỉ chứng minh lớp đó khớp với chính nó.
     *
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

    /* ================= ĐẶT HÀNG VÀ CHUYỂN SANG MOMO ================= */

    #[Test]
    public function momo_hien_ra_o_buoc_thanh_toan_khi_da_cau_hinh(): void
    {
        $this->assertContains('momo', PaymentMethod::values());
    }

    #[Test]
    public function momo_KHONG_hien_ra_khi_thieu_khoa(): void
    {
        /*
         * Một cổng thiếu khoá mà vẫn hiện ra thì khách điền hết địa chỉ
         * rồi mới gặp lỗi. Thà không hiện còn hơn hiện rồi hỏng.
         */
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
                && $d['requestType'] === 'payWithATM'
                // MoMo chỉ nhận số nguyên VND: "500000", không phải "500000.00".
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

    /* ================= CHỮ KÝ LÀ RANH GIỚI TIN CẬY ================= */

    #[Test]
    public function callback_SAI_CHU_KY_thi_don_khong_doi(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT CẢ TỆP.
         *
         * Đường dẫn này ai cũng gõ được. Nếu chỉ cần có mặt ở đó là đủ
         * thì bất kỳ khách nào cũng tự đánh dấu đơn của mình đã trả tiền
         * mà không mất một đồng.
         */
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
        /*
         * MoMo cũng ký cho những lượt HỎNG — khách bấm huỷ, thẻ khoá,
         * không đủ tiền. "Gói tin này có thật không" và "nó nói gì" là
         * hai câu hỏi khác nhau.
         */
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
        /*
         * MoMo báo thu 1.000₫ cho một đơn 500.000₫: hoặc gói tin bị sửa,
         * hoặc có nhầm lẫn ở đâu đó. Ghi "đã thanh toán" lúc này là để
         * phần mềm tự xác nhận một số tiền nó không kiểm được.
         */
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

    /* ================= IPN ================= */

    #[Test]
    public function duong_ipn_duoc_mien_kiem_csrf(): void
    {
        /*
         * ĐO TRÊN CẤU HÌNH, không đo bằng một request thử.
         *
         * Laravel TỰ TẮT kiểm CSRF khi đang chạy kiểm thử, nên một bài
         * POST vào /ipn vẫn xanh kể cả khi quên khai miễn trừ — đã kiểm
         * bằng cách xoá dòng khai đi. Trên máy thật thì mọi IPN bị 419
         * và đơn không bao giờ được ghi nhận qua đường đáng tin nhất.
         *
         * Chỉ có phép đo trên chính danh sách miễn trừ mới bắt được.
         */
        $mien = (new \ReflectionClass(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class))
            ->getStaticPropertyValue('neverVerify');

        $this->assertContains('thanh-toan/momo/ipn', $mien);

        // Và chỉ MỘT đường được miễn: mỗi dòng ở đây là một cánh cửa mở.
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
        // Trả mã lỗi chỉ khiến MoMo gọi lại nhiều lần, mà gói tin sai
        // chữ ký thì gọi bao nhiêu lần cũng vẫn sai.
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
        /*
         * Hai đường về cho cùng một lượt. Không chống trùng thì lần thứ
         * hai gọi setPaymentStatus() trên một đơn đã "đã thanh toán" và
         * ném lỗi ra giữa mặt khách.
         */
        $this->momoNhan();
        $order = $this->datHangMomo();
        $this->get('/thanh-toan/momo/' . $order->order_number);

        $tx = PaymentTransaction::where('gateway', 'momo')->firstOrFail();
        $payload = $this->goiTin($tx);

        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($payload));
        $this->post('/thanh-toan/momo/ipn', $payload)->assertOk();

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame(1, PaymentTransaction::where('status', 'paid')->count());

        /*
         * ĐO Ở CHỖ KHÁCH NHÌN THẤY.
         *
         * Hai khẳng định trên vẫn xanh kể cả khi bỏ hẳn chốt chống ghi
         * trùng — đã kiểm bằng cách bỏ. Lần thứ hai khi đó vẫn chạy tiếp,
         * gọi setPaymentStatus() trên một đơn đã trả rồi, ăn lỗi "đơn này
         * đã ở trạng thái đó rồi", và khách quay lại trang đơn thấy một
         * dòng đỏ báo đơn đã huỷ — trong khi họ vừa trả tiền xong.
         */
        $this->get('/thanh-toan/momo/ket-qua?' . http_build_query($payload))
            ->assertSessionHas('success')
            ->assertSessionMissing('error');
    }

    /* ================= THANH TOÁN LẠI ================= */

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
        /*
         * Cột (gateway, gateway_order_id) là UNIQUE, và MoMo cũng từ
         * chối nhận lại một orderId đã dùng. Trùng mã là lượt trả lại
         * thứ hai không bao giờ tạo được.
         */
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
            ->assertSee('Thanh toán lại với MoMo');
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
            ->assertDontSee('Thanh toán lại với MoMo');
    }

    /* ================= PHÂN QUYỀN ================= */

    #[Test]
    public function khong_tra_tien_ho_don_cua_nguoi_khac_duoc(): void
    {
        $this->momoNhan();
        $order = $this->datHangMomo();

        /*
         * PHIÊN PHẢI SẠCH.
         *
         * Mã đơn được ghi vào phiên lúc đặt để khách vãng lai còn xem
         * lại được đơn của mình. Không xoá thì người thứ hai vẫn mang
         * cái phiên đó, và bài này đo nhầm chính cơ chế ấy chứ không đo
         * phân quyền.
         */
        $this->flushSession();
        $this->actingAs(User::factory()->create());

        $this->get('/thanh-toan/momo/' . $order->order_number)->assertForbidden();
        $this->get('/don-hang/' . $order->order_number . '/thanh-toan-momo')->assertForbidden();
    }

    /* ================= COD KHÔNG BỊ ĐỘNG TỚI ================= */

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

    /* ================= NHẬT KÝ CHO NGƯỜI TRỰC ================= */

    #[Test]
    public function admin_doc_duoc_tung_luot_thanh_toan(): void
    {
        /*
         * Đây là chỗ trả lời câu "khách bảo đã bị trừ tiền mà đơn chưa
         * ghi nhận". Cột `payment_status` của đơn chỉ có một chữ, không
         * nói được đã thử mấy lần và hỏng ở đâu.
         */
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
