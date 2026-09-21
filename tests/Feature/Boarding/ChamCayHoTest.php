<?php

namespace Tests\Feature\Boarding;

use App\Enums\BoardingStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\BoardingBooking;
use App\Models\BoardingRate;
use App\Models\BoardingWindow;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Boarding\BoardingPricing;
use App\Services\Boarding\BoardingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Chăm cây hộ: giá tính ở máy chủ, vòng đời phiếu, nhận sớm, lặp lại theo dịp, quyền. */
class ChamCayHoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-21 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function gia(array $ghiDe = []): BoardingRate
    {
        return BoardingRate::create(array_merge([
            'name' => 'Đào thế chậu', 'monthly_price' => 300000, 'yearly_price' => 3000000, 'is_active' => true,
        ], $ghiDe));
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function guiYeuCau(User $khach, BoardingRate $gia, array $ghiDe = [])
    {
        return $this->actingAs($khach)->post(route('shop.boarding.store'), array_merge([
            'boarding_rate_id' => $gia->id,
            'plant_name' => 'Đào thế nhà tôi',
            'mode' => 'thang',
            'months' => 3,
            'drop_off_on' => '2026-09-25',
            'handover' => 'tu_mang',
            'contact_phone' => '0912345678',
        ], $ghiDe));
    }

    #[Test]
    public function so_thang_tinh_tien_co_an_han_va_ap_gia_nam(): void
    {
        $g = new BoardingPricing();

        $this->assertSame(1, $g->soThang(CarbonImmutable::parse('2026-01-10'), CarbonImmutable::parse('2026-01-20')));
        $this->assertSame(1, $g->soThang(CarbonImmutable::parse('2026-01-10'), CarbonImmutable::parse('2026-02-12')), 'Lố 2 ngày: trong ân hạn');
        $this->assertSame(2, $g->soThang(CarbonImmutable::parse('2026-01-10'), CarbonImmutable::parse('2026-02-15')));
        $this->assertSame(12, $g->soThang(CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2027-02-01')));

        $this->assertSame('3000000.00', $g->tienCham('300000', '3000000', 12), 'Đủ 12 tháng thì áp giá năm');
        $this->assertSame('3600000.00', $g->tienCham('300000', '3000000', 14));
    }

    #[Test]
    public function chua_co_bang_gia_thi_an_loi_vao_va_bao_chua_nhan(): void
    {
        $this->get(route('shop.boarding.index'))->assertOk()->assertSee('Cửa hàng chưa mở nhận chăm cây hộ');
        $this->get('/')->assertDontSee(route('shop.boarding.index'));

        $this->gia();

        $this->get('/')->assertSee(route('shop.boarding.index'));
        $this->get(route('shop.boarding.index'))->assertSee('Đào thế chậu')->assertSee('300.000');
    }

    #[Test]
    public function khach_gui_yeu_cau_gia_do_may_chu_tinh_khong_nhan_gia_tu_trinh_duyet(): void
    {
        $khach = User::factory()->create();
        $gia = $this->gia();

        $this->guiYeuCau($khach, $gia, ['care_amount' => 1, 'monthly_price' => 1])->assertRedirect();

        $p = BoardingBooking::sole();
        $this->assertSame(BoardingStatus::ChoDuyet, $p->status);
        $this->assertSame('900000.00', (string) $p->care_amount);
        $this->assertSame('300000.00', (string) $p->monthly_price);
        $this->assertSame('2026-12-25', $p->return_on->toDateString());
    }

    #[Test]
    public function bao_gia_tuc_thoi_va_loi_ngay_tra_truoc_ngay_gui(): void
    {
        $gia = $this->gia();

        $this->postJson(route('shop.boarding.quote'), [
            'boarding_rate_id' => $gia->id, 'mode' => 'nam', 'years' => 1, 'drop_off_on' => '2026-10-01',
        ])->assertOk()->assertJson(['months' => 12, 'return_on' => '01/10/2027', 'tam_tinh' => false]);

        $this->postJson(route('shop.boarding.quote'), [
            'boarding_rate_id' => $gia->id, 'mode' => 'den_ngay', 'drop_off_on' => '2026-10-01', 'return_on' => '2026-09-30',
        ])->assertStatus(422)->assertJsonValidationErrors('return_on');
    }

    #[Test]
    public function khach_khac_khong_xem_duoc_phieu(): void
    {
        $this->guiYeuCau(User::factory()->create(), $this->gia());

        $this->actingAs(User::factory()->create())
            ->get(route('shop.boarding.show', BoardingBooking::sole()))
            ->assertNotFound();
    }

    #[Test]
    public function vong_doi_day_du_nhan_som_co_phi_gap_va_tinh_lai_theo_thuc_te(): void
    {
        config(['kinh_doanh.cham_ho.phi_gap' => 50000, 'kinh_doanh.cham_ho.bao_gap_ngay' => 2]);

        $khach = User::factory()->create();
        $admin = $this->admin();
        $this->guiYeuCau($khach, $this->gia(), ['handover' => 'cua_hang_lay', 'address' => 'Số 1 Phú Diễn']);
        $p = BoardingBooking::sole();

        $this->actingAs($admin)->patch(route('admin.boarding.confirm', $p), [
            'drop_off_on' => '2026-09-25', 'monthly_price' => 300000, 'handover_fee' => 80000, 'adjustment' => 50000,
        ])->assertSessionHasErrors('adjustment_reason');

        $this->actingAs($admin)->patch(route('admin.boarding.confirm', $p), [
            'drop_off_on' => '2026-09-25', 'monthly_price' => 300000, 'yearly_price' => 3000000, 'handover_fee' => 80000, 'adjustment' => 50000, 'adjustment_reason' => 'Cây cao 1,8m',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch(route('admin.boarding.receive', $p), ['ngay' => '2026-09-25'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.boarding.update', $p), ['note' => 'Đã bón phân, cây ra lộc'])->assertSessionHasNoErrors();

        $this->assertTrue(UserNotification::where('user_id', $khach->id)->where('type', NotificationType::ChamHo)->where('note', 'like', '%bón phân%')->exists());

        Carbon::setTestNow('2026-10-20 09:00:00');
        $this->actingAs($khach)->post(route('shop.boarding.early', $p), ['ngay' => '2026-10-21'])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(BoardingStatus::ChoTra, $p->status);
        $this->assertSame('50000.00', (string) $p->rush_fee, 'Báo trước 1 ngày < 2 ngày: tính gấp');

        $this->actingAs($admin)->patch(route('admin.boarding.return', $p), ['ngay' => '2026-10-21'])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(BoardingStatus::DaTra, $p->status);
        $this->assertSame('300000.00', (string) $p->care_amount, 'Gửi thực 26 ngày: tính 1 tháng, không phải 3');
        $this->assertSame('480000.00', $p->tongTien(), '300k + 80k giao nhận + 50k gấp + 50k điều chỉnh');

        $this->actingAs($admin)->post(route('admin.boarding.payment', $p), ['amount' => 900000, 'method' => 'chuyen_khoan', 'note' => 'Khách trả trước'])->assertSessionHasNoErrors();
        $this->assertSame('-420000.00', $p->fresh()->conLai(), 'Âm: cửa hàng phải trả lại khách');
    }

    #[Test]
    public function khach_chi_huy_duoc_khi_cay_chua_ve_cua_hang(): void
    {
        $khach = User::factory()->create();
        $this->guiYeuCau($khach, $this->gia());
        $p = BoardingBooking::sole();

        app(BoardingService::class)->xacNhan($p, $this->admin(), ['drop_off_on' => '2026-09-25']);
        app(BoardingService::class)->nhanCay($p->fresh(), $this->admin(), '2026-09-25');

        $this->actingAs($khach)->post(route('shop.boarding.cancel', $p))->assertSessionHasErrors('status');
        $this->assertSame(BoardingStatus::DangCham, $p->fresh()->status);
    }

    #[Test]
    public function lap_lai_theo_dip_mo_ky_sau_va_cho_lich_khi_chua_co_nam_sau(): void
    {
        $khach = User::factory()->create();
        $admin = $this->admin();
        $tet27 = BoardingWindow::create(['group_key' => 'tet', 'name' => 'Tết 2027', 'return_on' => '2027-02-01', 'take_back_on' => '2027-02-20', 'is_active' => true]);

        $this->guiYeuCau($khach, $this->gia(), [
            'mode' => 'theo_dip', 'months' => null, 'boarding_window_id' => $tet27->id, 'repeat_yearly' => 1, 'drop_off_on' => '2026-09-25',
        ])->assertSessionHasNoErrors();

        $p = BoardingBooking::sole();
        $this->assertSame('2027-02-01', $p->return_on->toDateString());

        $dv = app(BoardingService::class);
        $dv->xacNhan($p, $admin, ['drop_off_on' => '2026-09-25']);
        $dv->nhanCay($p->fresh(), $admin, '2026-09-25');

        $this->assertNull($dv->traCay($p->fresh(), $admin, '2027-02-01'));
        $this->assertTrue($p->fresh()->waiting_next_window, 'Chưa có lịch Tết 2028 thì chờ');

        $this->actingAs($admin)->post(route('admin.boarding-windows.store'), [
            'name' => 'Tết 2028', 'group_key' => 'tet', 'return_on' => '2028-01-20', 'take_back_on' => '2028-02-10',
        ])->assertSessionHasNoErrors();

        $kySau = BoardingBooking::where('parent_id', $p->id)->sole();
        $this->assertSame(BoardingStatus::ChoDuyet, $kySau->status);
        $this->assertSame('2027-02-20', $kySau->drop_off_on->toDateString(), 'Nhận cây lại sau Tết');
        $this->assertSame('2028-01-20', $kySau->return_on->toDateString());
        $this->assertTrue($kySau->repeat_yearly);
        $this->assertFalse($p->fresh()->waiting_next_window);
    }

    #[Test]
    public function lenh_hang_ngay_chuyen_phieu_sap_den_han_sang_sap_tra(): void
    {
        $khach = User::factory()->create();
        $this->guiYeuCau($khach, $this->gia(), ['months' => 1]);
        $p = BoardingBooking::sole();

        $dv = app(BoardingService::class);
        $dv->xacNhan($p, $this->admin(), ['drop_off_on' => '2026-09-25']);
        $dv->nhanCay($p->fresh(), $this->admin(), '2026-09-25');

        $this->artisan('cham-ho:nhac')->assertSuccessful();
        $this->assertSame(BoardingStatus::DangCham, $p->fresh()->status, 'Còn hơn 7 ngày');

        Carbon::setTestNow('2026-10-20 08:00:00');
        $this->artisan('cham-ho:nhac')->assertSuccessful();
        $this->assertSame(BoardingStatus::ChoTra, $p->fresh()->status);
    }

    #[Test]
    public function khu_quan_tri_can_quyen_va_gia_da_dung_thi_chi_tat(): void
    {
        $gia = $this->gia();
        $this->guiYeuCau(User::factory()->create(), $gia);

        $this->actingAs(User::factory()->create())->get(route('admin.boarding.index'))->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.boarding.index'))->assertOk()->assertSee(BoardingBooking::sole()->code);
        $this->actingAs($admin)->get(route('admin.boarding.show', BoardingBooking::sole()))->assertOk()->assertSee('Xác nhận và báo khách');
        $this->actingAs($admin)->get(route('admin.boarding-rates.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.boarding-windows.index'))->assertOk();

        $this->actingAs($admin)->delete(route('admin.boarding-rates.destroy', $gia));
        $this->assertFalse($gia->fresh()->is_active);
    }
}
