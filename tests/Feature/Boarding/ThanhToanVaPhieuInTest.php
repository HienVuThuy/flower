<?php

namespace Tests\Feature\Boarding;

use App\Enums\BoardingPaymentMethod;
use App\Enums\BoardingSource;
use App\Enums\BoardingStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\UserRole;
use App\Models\BoardingBooking;
use App\Models\BoardingPayment;
use App\Models\BoardingRate;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Analytics\CashFlowReport;
use App\Services\Boarding\BoardingService;
use Database\Seeders\BoardingSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Chăm cây hộ: phiếu tại quầy, phiếu in (đầy đủ và trắng), thu trực tiếp, trả online MoMo, sổ thu chi. */
class ThanhToanVaPhieuInTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'khoa-bi-mat-thu';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-21 09:00:00');

        config([
            'payment.gateways.momo.enabled' => true,
            'payment.gateways.momo.partner_code' => 'MOMO_THU',
            'payment.gateways.momo.access_key' => 'khoa-truy-cap',
            'payment.gateways.momo.secret_key' => self::SECRET,
            'payment.gateways.momo.endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
            'payment.gateways.momo.request_type' => 'payWithMethod',
            'payment.gateways.momo.verify_ssl' => false,
            'payment.gateways.momo.redirect_url' => null,
            'payment.gateways.momo.ipn_url' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function gia(): BoardingRate
    {
        return BoardingRate::create(['name' => 'Cây chậu vừa', 'monthly_price' => 120000, 'yearly_price' => 1200000, 'is_active' => true]);
    }

    private function phieuDaXacNhan(User $khach): BoardingBooking
    {
        $this->actingAs($khach)->post(route('shop.boarding.store'), [
            'boarding_rate_id' => $this->gia()->id, 'plant_name' => 'Monstera', 'mode' => 'thang', 'months' => 2,
            'drop_off_on' => '2026-09-25', 'handover' => 'tu_mang', 'contact_phone' => '0912345678',
        ]);

        $p = BoardingBooking::sole();
        app(BoardingService::class)->xacNhan($p, $this->admin(), ['drop_off_on' => '2026-09-25']);

        return $p->fresh();
    }

    private function kyMomo(array $du): array
    {
        $cot = ['accessKey', 'amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId'];
        $tho = collect($cot)->map(fn ($k) => $k . '=' . ($k === 'accessKey' ? 'khoa-truy-cap' : ($du[$k] ?? '')))->implode('&');

        return $du + ['signature' => hash_hmac('sha256', $tho, self::SECRET)];
    }

    #[Test]
    public function lap_phieu_tai_quay_cho_khach_khong_co_tai_khoan_va_nhan_cay_ngay(): void
    {
        $gia = $this->gia();

        $this->actingAs($this->admin())->post(route('admin.boarding.store'), [
            'customer_name' => 'Chị Hương', 'boarding_rate_id' => $gia->id, 'mode' => 'thang', 'months' => 1,
            'drop_off_on' => '2026-09-21', 'nhan_cay_ngay' => 1, 'plant_name' => 'Sen đá',
            'handover' => 'tu_mang', 'contact_phone' => '0912345678',
        ])->assertSessionHasNoErrors();

        $p = BoardingBooking::sole();
        $this->assertNull($p->user_id);
        $this->assertSame('Chị Hương', $p->tenKhach());
        $this->assertSame(BoardingSource::TaiQuay, $p->source);
        $this->assertSame(BoardingStatus::DangCham, $p->status, 'Lập tại quầy + mang cây đến ngay');
        $this->assertSame('120000.00', (string) $p->care_amount);
    }

    #[Test]
    public function email_trung_tai_khoan_thi_phieu_tai_quay_gan_vao_tai_khoan(): void
    {
        $khach = User::factory()->create(['email' => 'khach@vidu.test']);

        $this->actingAs($this->admin())->post(route('admin.boarding.store'), [
            'customer_name' => 'Anh Nam', 'customer_email' => 'khach@vidu.test', 'boarding_rate_id' => $this->gia()->id,
            'mode' => 'thang', 'months' => 1, 'drop_off_on' => '2026-09-22', 'plant_name' => 'Kim tiền',
            'handover' => 'tu_mang', 'contact_phone' => '0912345678',
        ])->assertSessionHasNoErrors();

        $p = BoardingBooking::sole();
        $this->assertSame($khach->id, $p->user_id);
        $this->actingAs($khach)->get(route('shop.boarding.show', $p))->assertOk();
    }

    #[Test]
    public function phieu_in_va_phieu_trang_cung_mot_mau(): void
    {
        $khach = User::factory()->create(['name' => 'Lê Mai Anh']);
        $p = $this->phieuDaXacNhan($khach);

        $day = $this->actingAs($khach)->get(route('shop.boarding.print', $p))->assertOk();
        $day->assertSee('PHIẾU GỬI CÂY CHĂM HỘ')->assertSee($p->code)->assertSee('Lê Mai Anh')->assertSee('Monstera');

        $trang = $this->get(route('shop.boarding.blank'))->assertOk();
        $trang->assertSee('PHIẾU GỬI CÂY CHĂM HỘ')->assertSee('data-phieu-in="trang"', false)
            ->assertDontSee('Lê Mai Anh')->assertSee('Cây chậu vừa', false);

        foreach (['1. Khách hàng', '2. Cây gửi chăm', '3. Thời gian gửi', '4. Giao nhận cây', '5. Chi phí'] as $muc) {
            $day->assertSee($muc);
            $trang->assertSee($muc);
        }

        $this->actingAs($this->admin())->get(route('admin.boarding.print', $p))->assertOk()->assertSee($p->code);
        $this->actingAs(User::factory()->create())->get(route('shop.boarding.print', $p))->assertNotFound();
    }

    #[Test]
    public function thu_truc_tiep_ghi_tung_dong_va_so_da_thu_bang_tong(): void
    {
        $p = $this->phieuDaXacNhan(User::factory()->create());
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.boarding.payment', $p), ['amount' => 100000, 'method' => 'tien_mat'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.boarding.payment', $p), ['amount' => 140000, 'method' => 'chuyen_khoan', 'note' => 'VCB'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.boarding.payment', $p), ['amount' => 1, 'method' => 'momo'])->assertSessionHasErrors('method');

        $p->refresh();
        $this->assertSame('240000.00', (string) $p->paid_amount);
        $this->assertSame(2, $p->payments()->count());
        $this->assertSame('0.00', $p->conLai());
        $this->assertFalse($p->traOnlineDuoc(), 'Trả đủ rồi thì không hiện nút trả online');
    }

    #[Test]
    public function khach_tra_online_qua_momo_va_ipn_ghi_vao_phieu(): void
    {
        Http::fake(['*' => Http::response(['resultCode' => 0, 'message' => 'Successful.', 'payUrl' => 'https://test-payment.momo.vn/pay?t=x'])]);

        $khach = User::factory()->create();
        $p = $this->phieuDaXacNhan($khach);

        $this->actingAs($khach)->get(route('shop.boarding.show', $p))->assertSee('Trả online qua MoMo');
        $this->actingAs($khach)->post(route('shop.boarding.momo', $p))->assertRedirect('https://test-payment.momo.vn/pay?t=x');

        $gd = PaymentTransaction::sole();
        $this->assertSame($p->id, $gd->boarding_booking_id);
        $this->assertNull($gd->order_id);
        $this->assertSame('240000.00', (string) $gd->amount);

        $this->postJson(route('shop.payment.momo.ipn'), $this->kyMomo([
            'partnerCode' => 'MOMO_THU', 'orderId' => $gd->gateway_order_id, 'requestId' => 'r1', 'amount' => '240000',
            'orderInfo' => 'x', 'orderType' => 'momo_wallet', 'transId' => '999', 'resultCode' => '0', 'message' => 'OK',
            'payType' => 'qr', 'responseTime' => '1', 'extraData' => 'chamho:' . $p->code,
        ]))->assertOk();

        $p->refresh();
        $this->assertSame(PaymentTransactionStatus::Paid, $gd->fresh()->status);
        $this->assertSame('240000.00', (string) $p->paid_amount);
        $this->assertSame(BoardingPaymentMethod::Momo, BoardingPayment::sole()->method);

        $this->postJson(route('shop.payment.momo.ipn'), $this->kyMomo([
            'partnerCode' => 'MOMO_THU', 'orderId' => $gd->gateway_order_id, 'requestId' => 'r1', 'amount' => '240000',
            'orderInfo' => 'x', 'orderType' => 'momo_wallet', 'transId' => '999', 'resultCode' => '0', 'message' => 'OK',
            'payType' => 'qr', 'responseTime' => '1', 'extraData' => 'chamho:' . $p->code,
        ]))->assertOk();
        $this->assertSame(1, BoardingPayment::count(), 'IPN gửi lại không ghi hai lần');
    }

    #[Test]
    public function chua_xac_nhan_hoac_momo_chua_cau_hinh_thi_khong_co_nut_tra_online(): void
    {
        $khach = User::factory()->create();
        $this->actingAs($khach)->post(route('shop.boarding.store'), [
            'boarding_rate_id' => $this->gia()->id, 'plant_name' => 'Cây', 'mode' => 'thang', 'months' => 1,
            'drop_off_on' => '2026-09-25', 'handover' => 'tu_mang', 'contact_phone' => '0912345678',
        ]);
        $p = BoardingBooking::sole();

        $this->actingAs($khach)->get(route('shop.boarding.show', $p))->assertDontSee('Trả online qua MoMo');
        $this->actingAs($khach)->post(route('shop.boarding.momo', $p))->assertSessionHas('error');

        app(BoardingService::class)->xacNhan($p, $this->admin(), ['drop_off_on' => '2026-09-25']);
        config(['payment.gateways.momo.enabled' => false]);
        $this->actingAs($khach)->get(route('shop.boarding.show', $p))->assertDontSee('Trả online qua MoMo')->assertSee('Thanh toán trực tiếp');
    }

    #[Test]
    public function tien_cham_ho_vao_so_thu_chi_theo_ngay_thu(): void
    {
        $p = $this->phieuDaXacNhan(User::factory()->create());
        $dv = app(BoardingService::class);
        $admin = $this->admin();

        $dv->ghiThu($p, $admin, '240000.00', BoardingPaymentMethod::TienMat);
        Carbon::setTestNow('2026-10-05 09:00:00');
        $dv->ghiThu($p, $admin, '-40000.00', BoardingPaymentMethod::TienMat, 'Nhận sớm, trả lại');

        $this->assertSame('240000.00', CashFlowReport::chamHo(CashFlowReport::khoangThang('2026-09')));
        $this->assertSame('-40000.00', CashFlowReport::chamHo(CashFlowReport::khoangThang('2026-10')));

        $bao = app(CashFlowReport::class)->thang('2026-09');
        $this->assertSame('240000.00', $bao['dong_tien']['cham_ho']);
        $this->assertSame('240000.00', $bao['dong_tien']['tien_vao']);
    }

    #[Test]
    public function du_lieu_mau_tao_du_cac_trang_thai_va_chay_lai_khong_trung(): void
    {
        $this->admin();
        User::factory()->create();

        $this->seed(BoardingSampleSeeder::class);

        $this->assertSame(5, BoardingRate::count());
        $this->assertSame(5, BoardingBooking::count());
        $trangThai = BoardingBooking::pluck('status')->map->value->all();
        foreach (['dang_cham', 'da_tra', 'cho_tra', 'cho_khach_duyet'] as $tt) {
            $this->assertContains($tt, $trangThai);
        }
        $this->assertSame(1, BoardingBooking::where('source', 'tai_quay')->count());
        $this->assertSame('0.00', BoardingBooking::where('status', 'da_tra')->sole()->conLai(), 'Phiếu đã trả cây đã thanh toán đủ');

        $this->seed(BoardingSampleSeeder::class);
        $this->assertSame(5, BoardingBooking::count());
    }
}
