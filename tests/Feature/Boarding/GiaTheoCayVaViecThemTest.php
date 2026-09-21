<?php

namespace Tests\Feature\Boarding;

use App\Enums\BoardingExtraStatus;
use App\Enums\UserRole;
use App\Models\BoardingBooking;
use App\Models\BoardingExtra;
use App\Models\BoardingRate;
use App\Models\User;
use App\Services\Boarding\BoardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Giá chăm không cố định: bảng giá chỉ tham khảo, cửa hàng chốt giá từng cây; việc làm thêm báo giá riêng, khách đồng ý mới tính. */
class GiaTheoCayVaViecThemTest extends TestCase
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

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function phieu(User $khach): BoardingBooking
    {
        $gia = BoardingRate::create(['name' => 'Đào thế chậu', 'monthly_price' => 300000, 'yearly_price' => 3000000, 'is_active' => true]);

        $this->actingAs($khach)->post(route('shop.boarding.store'), [
            'boarding_rate_id' => $gia->id, 'plant_name' => 'Đào thế cao 1,8m', 'mode' => 'thang', 'months' => 3,
            'drop_off_on' => '2026-09-25', 'handover' => 'tu_mang', 'contact_phone' => '0912345678',
        ]);

        return BoardingBooking::sole();
    }

    #[Test]
    public function cua_hang_chot_gia_rieng_cho_tung_cay_khi_xac_nhan(): void
    {
        $khach = User::factory()->create();
        $p = $this->phieu($khach);

        $this->assertFalse($p->daChotGia(), 'Lúc khách gửi chỉ là giá tham khảo');
        $this->actingAs($khach)->get(route('shop.boarding.show', $p))->assertSee('Giá tham khảo (chờ cửa hàng chốt)');

        $this->actingAs($this->admin())->patch(route('admin.boarding.confirm', $p), ['drop_off_on' => '2026-09-25'])
            ->assertSessionHasErrors('monthly_price');

        $this->actingAs($this->admin())->patch(route('admin.boarding.confirm', $p), [
            'drop_off_on' => '2026-09-25', 'monthly_price' => 450000,
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertTrue($p->daChotGia());
        $this->assertSame('450000.00', (string) $p->monthly_price);
        $this->assertSame('5400000.00', (string) $p->yearly_price, 'Không ghi giá năm thì bằng 12 tháng');
        $this->assertSame('1350000.00', (string) $p->care_amount, '3 tháng × giá chốt');
        $this->assertSame('300000.00', (string) BoardingRate::sole()->monthly_price, 'Bảng giá tham khảo không đổi');
        $this->actingAs($khach)->get(route('shop.boarding.show', $p))->assertSee('Giá chốt cho cây này');
    }

    #[Test]
    public function khach_yeu_cau_them_cua_hang_bao_gia_khach_dong_y_moi_tinh_tien(): void
    {
        $khach = User::factory()->create();
        $admin = $this->admin();
        $p = $this->phieu($khach);
        $dv = app(BoardingService::class);
        $dv->xacNhan($p, $admin, ['drop_off_on' => '2026-09-25', 'monthly_price' => 300000]);
        $truoc = $p->fresh()->tongTien();

        $this->actingAs($khach)->post(route('shop.boarding.extra.store', $p), ['viec' => 'Thay chậu to hơn'])->assertSessionHasNoErrors();
        $x = BoardingExtra::sole();
        $this->assertSame(BoardingExtraStatus::ChoBaoGia, $x->status);
        $this->assertSame($truoc, $p->fresh()->load('extras')->tongTien(), 'Chưa báo giá thì chưa tính');

        $this->actingAs($admin)->patch(route('admin.boarding.extra.quote', [$p, $x]), ['gia' => 150000])->assertSessionHasNoErrors();
        $this->assertSame($truoc, $p->fresh()->load('extras')->tongTien(), 'Báo giá rồi nhưng khách chưa đồng ý thì chưa tính');

        $this->actingAs($khach)->post(route('shop.boarding.extra.answer', [$p, $x]), ['dong_y' => 1])->assertSessionHasNoErrors();
        $this->assertSame(bcadd($truoc, '150000', 2), $p->fresh()->load('extras')->tongTien());

        $this->actingAs($admin)->patch(route('admin.boarding.extra.done', [$p, $x]), ['ghi_chu' => 'Đã thay chậu gốm 40cm'])->assertSessionHasNoErrors();
        $this->assertSame(BoardingExtraStatus::DaLam, $x->fresh()->status);
        $this->assertSame(bcadd($truoc, '150000', 2), $p->fresh()->load('extras')->tongTien(), 'Đã làm vẫn tính đúng một lần');

        $this->actingAs($khach)->post(route('shop.boarding.extra.answer', [$p, $x]), ['dong_y' => 0])->assertSessionHasErrors('viec');
    }

    #[Test]
    public function cua_hang_de_xuat_khach_tu_choi_thi_khong_tinh_va_khach_tai_quay_duoc_ghi_ho(): void
    {
        $khach = User::factory()->create();
        $admin = $this->admin();
        $p = $this->phieu($khach);
        $dv = app(BoardingService::class);
        $dv->xacNhan($p, $admin, ['drop_off_on' => '2026-09-25', 'monthly_price' => 300000]);
        $truoc = $p->fresh()->tongTien();

        $this->actingAs($admin)->post(route('admin.boarding.extra.propose', $p), ['viec' => 'Xử lý rệp sáp', 'gia' => 80000])->assertSessionHasNoErrors();
        $deXuat = BoardingExtra::sole();
        $this->actingAs($khach)->get(route('shop.boarding.show', $p))->assertSee('Đồng ý 80.000');

        $this->actingAs($khach)->post(route('shop.boarding.extra.answer', [$p, $deXuat]), ['dong_y' => 0]);
        $this->assertSame(BoardingExtraStatus::KhachTuChoi, $deXuat->fresh()->status);
        $this->assertSame($truoc, $p->fresh()->load('extras')->tongTien());

        $this->actingAs($admin)->post(route('admin.boarding.extra.propose', $p), ['viec' => 'Bón phân kích hoa', 'gia' => 50000]);
        $x2 = BoardingExtra::latest('id')->first();
        $this->actingAs($admin)->patch(route('admin.boarding.extra.answer', [$p, $x2]), ['dong_y' => 1])->assertSessionHasNoErrors();
        $this->assertSame(BoardingExtraStatus::DaDongY, $x2->fresh()->status, 'Khách đồng ý qua điện thoại, cửa hàng ghi hộ');
    }

    #[Test]
    public function khong_nhan_yeu_cau_khi_phieu_chua_xac_nhan_va_khach_khac_khong_tra_loi_duoc(): void
    {
        $khach = User::factory()->create();
        $p = $this->phieu($khach);

        $this->actingAs($khach)->post(route('shop.boarding.extra.store', $p), ['viec' => 'Tạo dáng'])->assertSessionHasErrors('viec');

        app(BoardingService::class)->xacNhan($p, $this->admin(), ['drop_off_on' => '2026-09-25', 'monthly_price' => 300000]);
        $this->actingAs($khach)->post(route('shop.boarding.extra.store', $p), ['viec' => 'Tạo dáng']);
        $x = BoardingExtra::sole();

        $this->actingAs(User::factory()->create())->post(route('shop.boarding.extra.answer', [$p, $x]), ['dong_y' => 1])->assertNotFound();
    }

    #[Test]
    public function trang_bang_gia_dat_o_them_moi_len_dau(): void
    {
        BoardingRate::create(['name' => 'Bonsai', 'monthly_price' => 400000, 'is_active' => true]);

        $html = $this->actingAs($this->admin())->get(route('admin.boarding-rates.index'))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'data-dong-gia="' . BoardingRate::sole()->id . '"'), strpos($html, 'data-dong-gia="moi"'));
    }
}
