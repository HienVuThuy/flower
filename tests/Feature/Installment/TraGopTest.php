<?php

namespace Tests\Feature\Installment;

use App\Enums\InstallmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Credit\CreditScore;
use App\Services\Installment\InstallmentPlanner;
use App\Services\Installment\InstallmentService;
use App\Services\Order\OrderException;
use App\Services\Order\OrderService;
use App\Services\Refund\RefundException;
use App\Services\Refund\RefundService;
use App\Services\Shipping\GHNOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Trả góp trước khi giao + điểm tín dụng. */
class TraGopTest extends TestCase
{
    use RefreshDatabase;

    private const PARTNER = 'MOMOBKUN20180529';

    private const ACCESS_KEY = 'klm05TvNBzhg7h7j';

    private const SECRET = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';

    private Product $sp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 03:00:00', 'UTC'));
        config(['payment.gateways.momo.enabled' => false]);
        Http::preventStrayRequests();
    }

    private function batMomo(): void
    {
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
        ]);

        Http::fake(['*' => Http::response(['resultCode' => 0, 'message' => 'Successful.', 'payUrl' => 'https://momo.test/pay?t=1'])]);
    }

    private function goiTin(PaymentTransaction $tx, array $ghiDe = []): array
    {
        $p = array_merge([
            'partnerCode' => self::PARTNER,
            'orderId' => (string) $tx->gateway_order_id,
            'requestId' => (string) $tx->gateway_order_id,
            'amount' => (string) (int) round((float) $tx->amount),
            'orderInfo' => 'Thanh toan',
            'orderType' => 'momo_wallet',
            'transId' => (string) random_int(1000000, 9999999),
            'resultCode' => '0',
            'message' => 'Successful.',
            'payType' => 'napas',
            'responseTime' => '1758800000000',
            'extraData' => (string) $tx->order->order_number,
        ], $ghiDe);

        $raw = 'accessKey=' . self::ACCESS_KEY . '&amount=' . $p['amount'] . '&extraData=' . $p['extraData']
            . '&message=' . $p['message'] . '&orderId=' . $p['orderId'] . '&orderInfo=' . $p['orderInfo']
            . '&orderType=' . $p['orderType'] . '&partnerCode=' . $p['partnerCode'] . '&payType=' . $p['payType']
            . '&requestId=' . $p['requestId'] . '&responseTime=' . $p['responseTime'] . '&resultCode=' . $p['resultCode']
            . '&transId=' . $p['transId'];

        $p['signature'] = hash_hmac('sha256', $raw, self::SECRET);

        return $p;
    }

    private function nguoi(UserRole $vaiTro = UserRole::Customer): User
    {
        $u = User::factory()->create();
        $u->role = $vaiTro;
        $u->save();

        return $u;
    }

    private function vaoThanhToan(string $gia = '2000000.00', array $them = []): void
    {
        $this->sp = Product::factory()->for(Category::factory())->price($gia)->stock(10)->create();

        $this->post('/gio-hang', ['product_id' => $this->sp->id, 'quantity' => 1])->assertRedirect();
        $this->post('/thanh-toan', $them + [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử', 'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com', 'shipping_address' => '12 Đường Thử Nghiệm',
            'shipping_ward' => 'Phường 1', 'shipping_district' => 'Quận 3',
            'shipping_province' => 'Thành phố Hồ Chí Minh', 'payment_method' => 'tra_gop', 'so_ky' => 2,
            'address_id' => '', 'coupon_code' => '',
        ]);
    }

    private function datTraGop(?User $u = null, int $soKy = 2): Order
    {
        $this->actingAs($u ?? $this->nguoi());
        $this->vaoThanhToan(them: ['so_ky' => $soKy]);
        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    private function donDaGiao(User $u, int $so, array $ghiDe = []): void
    {
        for ($i = 0; $i < $so; $i++) {
            $o = Order::create($ghiDe + [
                'order_number' => 'TG-' . $u->id . '-' . $i . '-' . random_int(1000, 9999), 'user_id' => $u->id,
                'recipient_name' => 'K', 'recipient_phone' => '0912345678', 'shipping_address' => '1',
                'shipping_province' => 'Thành phố Hà Nội', 'payment_method' => 'cod',
                'subtotal' => '100000', 'discount_total' => '0', 'shipping_fee' => '0', 'coupon_discount' => '0', 'grand_total' => '100000',
            ]);
            $o->status = OrderStatus::Completed;
            $o->payment_status = PaymentStatus::Paid;
            $o->save();
        }
    }

    private function thu(Order $don, int $sequence): \Illuminate\Testing\TestResponse
    {
        $ky = InstallmentPayment::whereHas('plan', fn ($q) => $q->where('order_id', $don->id))->where('sequence', $sequence)->firstOrFail();

        return $this->post(route('admin.orders.installments.record', $don), ['ky_id' => $ky->id]);
    }

    #[Test]
    public function chia_lich_tong_dung_bang_don_tra_truoc_lam_tron_len(): void
    {
        $lich = InstallmentPlanner::lich('1000001.00', 3, 30, '2026-10-01', 14);

        $this->assertSame(['300001.00', '233333.00', '233333.00', '233334.00'], array_column($lich, 'amount'));
        $this->assertSame(['2026-10-01', '2026-10-15', '2026-10-29', '2026-11-12'], array_column($lich, 'due_on'));
        $this->assertSame('1000001.00', array_reduce(array_column($lich, 'amount'), fn ($t, $a) => bcadd($t, $a, 2), '0.00'));
    }

    #[Test]
    public function dat_tra_gop_giu_hang_tao_lich_theo_muc_thuong(): void
    {
        $don = $this->datTraGop();

        $this->assertSame(PaymentMethod::TraGop, $don->payment_method);
        $this->assertSame(PaymentStatus::Unpaid, $don->payment_status);
        $this->assertSame(9, $this->sp->fresh()->stock_quantity, 'Hàng bị giữ ngay khi đặt');

        $kh = $don->installmentPlan()->with('payments')->firstOrFail();
        $tong = (int) $don->grand_total;

        $this->assertSame(InstallmentStatus::DangTra, $kh->status);
        $this->assertSame([2, 50, 50, 14, 3], [$kh->period_count, $kh->down_payment_percent, $kh->credit_score, $kh->period_days, $kh->grace_days]);
        $this->assertSame([0, 1, 2], $kh->payments->pluck('sequence')->all());
        $this->assertSame(bcadd((string) intdiv($tong * 50 + 99, 100), '0', 2), (string) $kh->payments[0]->amount);
        $this->assertSame((string) $don->grand_total, $kh->payments->reduce(fn ($t, $k) => bcadd($t, (string) $k->amount, 2), '0.00'));
        $this->assertSame(['2026-10-01', '2026-10-15', '2026-10-29'], $kh->payments->map(fn ($k) => $k->due_on->toDateString())->all());
    }

    #[Test]
    public function xac_nhan_hien_lich_truoc_khi_dat(): void
    {
        $this->actingAs($this->nguoi());
        $this->vaoThanhToan();

        $this->get(route('shop.checkout.confirm'))->assertOk()
            ->assertSee('data-lich-tra-gop', false)
            ->assertSee('Kỳ 2');
    }

    #[Test]
    public function vuot_so_ky_thi_khong_co_don_va_kho_nguyen(): void
    {
        $this->actingAs($this->nguoi());
        $this->vaoThanhToan(them: ['so_ky' => 3]);

        $this->post('/thanh-toan/dat-hang')
            ->assertRedirect(route('shop.checkout.details'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'từ 1 đến 2 kỳ'));

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->sp->fresh()->stock_quantity);
    }

    #[Test]
    public function khach_vang_lai_khong_duoc_xet_tra_gop(): void
    {
        $xet = app(\App\Services\Installment\InstallmentPolicy::class)->xet(null, '5000000.00');
        $this->assertFalse($xet['duoc']);
        $this->assertStringContainsString('Đăng nhập để trả góp', (string) $xet['ly_do']);

        $this->expectException(\App\Services\Installment\InstallmentException::class);
        app(InstallmentService::class)->taoKeHoach(new Order(['grand_total' => '5000000.00']), null, 1);
    }

    #[Test]
    public function don_nho_thay_ly_do_va_may_chu_van_chan(): void
    {
        $this->actingAs($this->nguoi());
        $this->vaoThanhToan('500000.00');

        $this->get(route('shop.checkout.details'))
            ->assertSee('data-tra-gop="khong"', false)
            ->assertSee('Trả góp áp dụng cho đơn từ');

        $this->post('/thanh-toan/dat-hang')->assertRedirect(route('shop.checkout.details'));
        $this->assertSame(0, Order::count(), 'Gửi thẳng tra_gop cho đơn nhỏ vẫn không có đơn');
        $this->assertSame(10, $this->sp->fresh()->stock_quantity);
    }

    #[Test]
    public function mot_ke_hoach_dang_tra_moi_lan(): void
    {
        $u = $this->nguoi();
        $this->datTraGop($u);

        $this->vaoThanhToan();
        $this->post('/thanh-toan/dat-hang')->assertSessionHas('error', fn ($m) => str_contains($m, 'chưa trả xong'));

        $this->assertSame(1, Order::count());
    }

    #[Test]
    public function diem_tin_dung_tu_lich_su_va_doi_muc_tra_gop(): void
    {
        $u = $this->nguoi();
        $this->assertSame(50, app(CreditScore::class)->cua($u)['diem']);

        $this->donDaGiao($u, 12);
        $this->assertSame(70, app(CreditScore::class)->cua($u)['diem']);

        $this->actingAs($u);
        $this->vaoThanhToan();
        $this->get(route('shop.checkout.details'))
            ->assertSee('data-tra-gop="duoc"', false)
            ->assertSee('class="tra-gop-chi-tiet d-block"', false)
            ->assertSee('trả trước 30%')
            ->assertSee('tối đa 4 kỳ');

        $o = Order::create([
            'order_number' => 'TG-TU-CHOI', 'user_id' => $u->id, 'recipient_name' => 'K', 'recipient_phone' => '0912345678',
            'shipping_address' => '1', 'shipping_province' => 'Thành phố Hà Nội', 'payment_method' => 'cod',
            'subtotal' => '1', 'discount_total' => '0', 'shipping_fee' => '0', 'coupon_discount' => '0', 'grand_total' => '1',
        ]);
        $dv = app(OrderService::class);
        foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Shipping] as $b) {
            $dv->changeStatus($o->fresh(), $b);
        }
        $dv->changeStatus($o->fresh(), OrderStatus::Cancelled, 'khách từ chối nhận', hoanHang: true);

        $tinDung = app(CreditScore::class)->cua($u);
        $this->assertSame(60, $tinDung['diem']);
        $this->assertSame(1, collect($tinDung['yeu_to'])->firstWhere('ma', 'tu_choi_nhan')['so_lan']);
    }

    #[Test]
    public function diem_duoi_toi_thieu_thi_khong_tra_gop(): void
    {
        Setting::set('tra_gop.diem_toi_thieu', '60');
        $this->actingAs($this->nguoi());
        $this->vaoThanhToan();

        $this->get(route('shop.checkout.details'))->assertSee('Điểm tín dụng của bạn là 50, cần từ 60');
        $this->post('/thanh-toan/dat-hang')->assertRedirect(route('shop.checkout.details'));
        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function chua_tra_du_khong_chuan_bi_khong_van_don_khong_dat_tay_da_tra(): void
    {
        $don = $this->datTraGop();
        $dv = app(OrderService::class);

        $dv->changeStatus($don->fresh(), OrderStatus::Confirmed);

        try {
            $dv->changeStatus($don->fresh(), OrderStatus::Preparing);
            $this->fail('Đơn trả góp chưa trả đủ đã sang chuẩn bị');
        } catch (OrderException $e) {
            $this->assertStringContainsString('chưa trả đủ', $e->getMessage());
        }

        $this->assertSame(-1, app(GHNOrderService::class)->create($don->fresh())['code']);
        $this->assertStringContainsString('trả góp', app(GHNOrderService::class)->create($don->fresh())['message']);

        $this->expectException(OrderException::class);
        $dv->setPaymentStatus($don->fresh(), PaymentStatus::Paid);
    }

    #[Test]
    public function thu_tai_cua_hang_theo_thu_tu_ky_cuoi_thi_da_tra_va_xac_nhan(): void
    {
        $don = $this->datTraGop();
        $admin = $this->nguoi(UserRole::Admin);

        $this->actingAs($this->nguoi(UserRole::Staff));
        $this->thu($don, 0)->assertForbidden();

        $this->actingAs($admin);
        $this->thu($don, 1)->assertSessionHas('error', fn ($m) => str_contains($m, 'trả trước chưa trả'));

        $this->thu($don, 0)->assertSessionHas('success');
        $this->thu($don, 0)->assertSessionHas('error', fn ($m) => str_contains($m, 'đã được ghi nhận'));
        $this->thu($don, 1)->assertSessionHas('success');
        $this->assertSame(PaymentStatus::Unpaid, $don->fresh()->payment_status);

        $this->thu($don, 2)->assertSessionHas('success', fn ($m) => str_contains($m, 'trả đủ'));

        $don->refresh();
        $this->assertSame(PaymentStatus::Paid, $don->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $don->status);
        $this->assertSame(InstallmentStatus::HoanTat, $don->installmentPlan->status);
        $this->assertSame(3, PaymentTransaction::where('gateway', InstallmentService::TAI_CUA_HANG)->where('status', 'paid')->count());

        app(OrderService::class)->changeStatus($don->fresh(), OrderStatus::Preparing);
        $this->assertSame(OrderStatus::Preparing, $don->fresh()->status);

        $this->assertSame(59, app(CreditScore::class)->cua($don->user)['diem']);
    }

    #[Test]
    public function momo_tra_tung_ky_dung_so_tien_ky_cuoi_hoan_tat(): void
    {
        $this->batMomo();
        $don = $this->datTraGop();
        $kyDau = $don->installmentPlan->payments[0];

        $this->get(route('shop.orders.tra-gop.momo', $don))->assertRedirect('https://momo.test/pay?t=1');

        $tx = PaymentTransaction::where('gateway', 'momo')->latest('id')->firstOrFail();
        $this->assertSame($kyDau->id, $tx->installment_payment_id);
        $this->assertSame((string) $kyDau->amount, (string) $tx->amount);

        $this->get(route('shop.payment.momo.callback', $this->goiTin($tx, ['amount' => (string) (int) $don->grand_total])));
        $this->assertNull($kyDau->fresh()->paid_at);

        $this->get(route('shop.orders.tra-gop.momo', $don));
        $tx = PaymentTransaction::where('gateway', 'momo')->latest('id')->firstOrFail();
        $this->get(route('shop.payment.momo.callback', $this->goiTin($tx)))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'kỳ trả góp'));

        $this->assertNotNull($kyDau->fresh()->paid_at);
        $this->assertSame(PaymentStatus::Unpaid, $don->fresh()->payment_status);
        $this->assertSame(OrderStatus::Pending, $don->fresh()->status);

        foreach ([1, 2] as $_) {
            $this->get(route('shop.orders.tra-gop.momo', $don));
            $tx = PaymentTransaction::where('gateway', 'momo')->latest('id')->firstOrFail();
            $this->get(route('shop.payment.momo.callback', $this->goiTin($tx)));
        }

        $this->assertSame(PaymentStatus::Paid, $don->fresh()->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $don->fresh()->status);

        $this->get(route('shop.orders.tra-gop.momo', $don))->assertSessionHas('error', fn ($m) => str_contains($m, 'không còn kỳ'));

        $this->assertFalse(app(RefundService::class)->hoanQuaMomoDuoc($don->fresh()));
    }

    #[Test]
    public function qua_han_vuot_an_han_thi_vo_huy_don_hoan_kho_no_dung_so_da_thu(): void
    {
        $don = $this->datTraGop();
        $khach = $don->user;
        $this->actingAs($this->nguoi(UserRole::Admin));
        $this->thu($don, 0)->assertSessionHas('success');
        $daThu = (string) $don->installmentPlan->payments[0]->amount;

        $this->travelTo(Carbon::parse('2026-10-18 16:00:00', 'UTC'));
        $this->artisan('tra-gop:qua-han')->assertSuccessful();
        $this->assertSame(InstallmentStatus::DangTra, $don->fresh()->installmentPlan->status);

        $this->travelTo(Carbon::parse('2026-10-18 17:30:00', 'UTC'));
        $this->artisan('tra-gop:qua-han')->assertSuccessful();

        $don->refresh();
        $this->assertSame(InstallmentStatus::VoNo, $don->installmentPlan->status);
        $this->assertSame(OrderStatus::Cancelled, $don->status);
        $this->assertStringContainsString('Quá hạn kỳ 1', (string) $don->cancel_reason);
        $this->assertSame(10, $this->sp->fresh()->stock_quantity, 'Hàng về kho');

        $this->assertSame($daThu, $don->daThu());
        $this->assertSame($daThu, $don->refundableAmount());
        $this->assertTrue(app(OrderService::class)->owesRefund($don));

        $tinDung = app(CreditScore::class)->cua($khach);
        $this->assertSame(50 + 3 - 30, $tinDung['diem']);

        $hoan = app(RefundService::class);
        $this->assertNull($hoan->lyDoKhongHoanDuoc($don));

        try {
            $hoan->hoan($don->fresh(), ['amount' => (int) $daThu + 1000, 'reason' => 'don_huy', 'method' => 'chuyen_khoan', 'reference' => 'FT1']);
            $this->fail('Hoàn quá số đã thu');
        } catch (RefundException) {
        }

        $hoan->hoan($don->fresh(), ['amount' => (int) $daThu, 'reason' => 'don_huy', 'method' => 'chuyen_khoan', 'reference' => 'FT2']);
        $this->assertSame(PaymentStatus::Refunded, $don->fresh()->payment_status);
        $this->assertFalse(app(OrderService::class)->owesRefund($don->fresh()));
    }

    #[Test]
    public function tra_tre_tinh_theo_ngay_viet_nam_va_tru_diem(): void
    {
        $don = $this->datTraGop();
        $khach = $don->user;
        $this->actingAs($this->nguoi(UserRole::Admin));

        $this->travelTo(Carbon::parse('2026-10-01 17:30:00', 'UTC'));
        $this->thu($don, 0)->assertSessionHas('success');

        $tinDung = app(CreditScore::class)->cua($khach);
        $this->assertSame(1, collect($tinDung['yeu_to'])->firstWhere('ma', 'ky_tre')['so_lan']);
        $this->assertSame(45, $tinDung['diem']);

        $this->assertStringContainsString('(trễ)', $this->get(route('admin.orders.show', $don))->getContent());
    }

    #[Test]
    public function khach_huy_don_thi_ke_hoach_da_huy_khong_tru_diem(): void
    {
        $don = $this->datTraGop();

        $this->post(route('shop.orders.cancel', $don), ['reason' => 'Đổi ý'])->assertRedirect();

        $this->assertSame(InstallmentStatus::DaHuy, $don->fresh()->installmentPlan->status);
        $this->assertSame(10, $this->sp->fresh()->stock_quantity);
        $this->assertSame(50, app(CreditScore::class)->cua($don->user)['diem']);
        $this->assertFalse(app(OrderService::class)->owesRefund($don->fresh()), 'Chưa trả kỳ nào thì không nợ');
    }

    #[Test]
    public function admin_sua_cau_hinh_co_rang_buoc_va_tat_duoc(): void
    {
        $hopLe = [
            'bat' => '1', 'don_toi_thieu' => 1500000, 'so_ngay_moi_ky' => 7, 'ngay_an_han' => 2,
            'diem_toi_thieu' => 40, 'diem_tot' => 80, 'ky_toi_da_thuong' => 3, 'ky_toi_da_tot' => 6,
            'tra_truoc_thuong' => 40, 'tra_truoc_tot' => 20,
        ];

        $this->actingAs($this->nguoi(UserRole::Staff))->put(route('admin.installments.settings'), $hopLe)->assertForbidden();

        $this->actingAs($this->nguoi(UserRole::Admin));
        $this->put(route('admin.installments.settings'), ['diem_tot' => 30, 'ky_toi_da_tot' => 2, 'tra_truoc_tot' => 50] + $hopLe)
            ->assertSessionHasErrors(['diem_tot', 'ky_toi_da_tot', 'tra_truoc_tot']);

        $this->put(route('admin.installments.settings'), $hopLe)->assertRedirect(route('admin.installments.index'));
        $this->assertSame('7', Setting::get('tra_gop.so_ngay_moi_ky'));

        $this->get(route('admin.installments.index'))->assertOk()->assertSee('data-cau-hinh-tra-gop', false);

        $this->put(route('admin.installments.settings'), ['bat' => '0'] + $hopLe);
        $this->assertNotContains('tra_gop', PaymentMethod::values());
    }

    #[Test]
    public function trang_don_khach_admin_va_ho_so_hien_tra_gop(): void
    {
        $don = $this->datTraGop();

        $this->get(route('shop.orders.show', $don))->assertOk()
            ->assertSee('data-tra-gop="dang_tra"', false)
            ->assertSee('data-ky="2"', false)
            ->assertDontSee('@endif', false);

        $this->get(route('shop.profile.edit', ['muc' => 'tra-gop']))->assertOk()
            ->assertSee('data-diem-tin-dung="50"', false)
            ->assertSee('chưa trả xong');

        $this->actingAs($this->nguoi(UserRole::Admin));
        $html = $this->get(route('admin.orders.show', $don))->assertOk()->getContent();
        $this->assertStringContainsString('data-admin-tra-gop="dang_tra"', $html);
        $this->assertSame(1, substr_count($html, 'data-ghi-ky'), 'Chỉ kỳ chưa trả sớm nhất có nút ghi');
        $this->assertStringNotContainsString(route('admin.orders.payment', $don), $html, 'Không bày nút đặt tay đã thanh toán');
    }
}
