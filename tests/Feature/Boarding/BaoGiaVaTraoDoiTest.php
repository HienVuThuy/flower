<?php

namespace Tests\Feature\Boarding;

use App\Enums\BoardingExtraStatus;
use App\Enums\BoardingQuoteStatus;
use App\Enums\BoardingStatus;
use App\Enums\UserRole;
use App\Models\BoardingBooking;
use App\Models\BoardingExtra;
use App\Models\BoardingQuote;
use App\Models\BoardingRate;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Báo giá phiếu chăm hộ: khách gửi kèm danh sách yêu cầu → cửa hàng báo giá chi tiết + tổng →
 * khách xác nhận (rồi mới trả tiền) / yêu cầu sửa / huỷ. Trao đổi qua tin nhắn gắn mã phiếu.
 */
class BaoGiaVaTraoDoiTest extends TestCase
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

    private function khach(): User
    {
        return User::factory()->create();
    }

    private function gui(User $khach, array $ghiDe = [], bool $dacThu = false): BoardingBooking
    {
        $gia = BoardingRate::create(['name' => 'Bonsai', 'monthly_price' => 400000, 'yearly_price' => 4000000, 'is_active' => true, 'needs_quote' => $dacThu]);

        $this->actingAs($khach)->post(route('shop.boarding.store'), array_merge([
            'boarding_rate_id' => $gia->id, 'plant_name' => 'Tùng la hán 30 năm', 'mode' => 'thang', 'months' => 2,
            'drop_off_on' => '2026-09-25', 'handover' => 'cua_hang_lay', 'address' => 'Số 1', 'contact_phone' => '0912345678',
        ], $ghiDe))->assertSessionHasNoErrors();

        return BoardingBooking::latest('id')->first();
    }

    private function baoGia(BoardingBooking $p, User $admin, array $ghiDe = [])
    {
        return $this->actingAs($admin)->patch(route('admin.boarding.confirm', $p), array_merge([
            'cach' => 'bao_gia', 'drop_off_on' => '2026-09-25', 'return_on' => '2026-11-25',
            'monthly_price' => 500000, 'handover_fee' => 100000,
        ], $ghiDe));
    }

    #[Test]
    public function khach_gui_kem_danh_sach_yeu_cau_rieng_va_gia_tri_cay(): void
    {
        $p = $this->gui($this->khach(), [
            'yeu_cau_rieng' => "- Thay chậu to hơn\nTỉa tạo dáng\n\nThay chậu to hơn",
            'declared_value' => 20000000,
        ]);

        $this->assertSame(['Thay chậu to hơn', 'Tỉa tạo dáng'], $p->extras()->pluck('title')->all(), 'Mỗi dòng một việc, bỏ trùng, bỏ gạch đầu dòng');
        $this->assertSame(BoardingExtraStatus::ChoBaoGia, $p->extras()->first()->status);
        $this->assertSame('20000000.00', (string) $p->declared_value);
    }

    #[Test]
    public function cay_dac_thu_gia_tri_cao_hoac_co_yeu_cau_rieng_thi_khong_xac_nhan_thang_duoc(): void
    {
        config(['kinh_doanh.cham_ho.gia_tri_cao_tu' => 5000000]);
        $admin = $this->admin();

        $thuong = $this->gui($this->khach());
        $this->assertSame([], $thuong->load(['rate', 'extras'])->lyDoPhaiBaoGia());

        $dacThu = $this->gui($this->khach(), [], true);
        $giaTriCao = $this->gui($this->khach(), ['declared_value' => 8000000]);
        $coYeuCau = $this->gui($this->khach(), ['yeu_cau_rieng' => 'Kích hoa']);

        foreach ([$dacThu, $giaTriCao, $coYeuCau] as $p) {
            $this->actingAs($admin)->patch(route('admin.boarding.confirm', $p), [
                'cach' => 'xac_nhan', 'drop_off_on' => '2026-09-25', 'monthly_price' => 400000,
            ])->assertSessionHasErrors('cach');
            $this->assertSame(BoardingStatus::ChoDuyet, $p->fresh()->status);
        }

        $this->actingAs($admin)->patch(route('admin.boarding.confirm', $thuong), [
            'cach' => 'xac_nhan', 'drop_off_on' => '2026-09-25', 'monthly_price' => 400000,
        ])->assertSessionHasNoErrors();
        $this->assertSame(BoardingStatus::DaXacNhan, $thuong->fresh()->status, 'Cây thường vẫn xác nhận thẳng được');
    }

    #[Test]
    public function bao_gia_phai_co_gia_tung_yeu_cau_va_co_dong_chi_tiet_khop_tong(): void
    {
        $khach = $this->khach();
        $admin = $this->admin();
        $p = $this->gui($khach, ['yeu_cau_rieng' => "Thay chậu\nTạo dáng"]);
        [$thayChau, $taoDang] = $p->extras()->orderBy('id')->get()->all();

        $this->baoGia($p, $admin, ['extras' => [$thayChau->id => ['gia' => 150000]]])
            ->assertSessionHasErrors("extras.{$taoDang->id}.gia");

        $this->baoGia($p, $admin, [
            'extras' => [
                $thayChau->id => ['gia' => 150000],
                $taoDang->id => ['khong_nhan' => 1, 'ly_do' => 'Cây đang yếu, chưa nên uốn'],
            ],
            'them' => [['viec' => 'Xử lý rệp sáp', 'gia' => 80000]],
            'note' => 'Cây to nên giá tháng cao hơn bảng.',
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $bg = BoardingQuote::sole();
        $this->assertSame(BoardingStatus::ChoKhachDuyet, $p->status);
        $this->assertSame(1, $bg->version);
        $this->assertSame('2026-09-24', $bg->valid_until->toDateString(), 'Mặc định hiệu lực 3 ngày');

        // 2 tháng × 500k + thay chậu 150k + rệp 80k + phí lấy/trả 100k
        $this->assertSame('1330000.00', (string) $bg->total);
        $this->assertCount(4, $bg->lines);
        $this->assertSame(BoardingExtraStatus::CuaHangTuChoi, $taoDang->fresh()->status);
        $this->assertFalse($p->daChotGia(), 'Chưa chốt cho tới khi khách xác nhận');

        $this->actingAs($khach)->get(route('shop.boarding.show', $p))
            ->assertSee('Cửa hàng đã gửi báo giá')->assertSee('Yêu cầu của bạn: Thay chậu')
            ->assertSee('Cửa hàng đề xuất: Xử lý rệp sáp')->assertSee('Xác nhận báo giá 1.330.000');
        $this->assertFalse($p->fresh()->traOnlineDuoc(), 'Chưa xác nhận báo giá thì chưa trả tiền');
    }

    #[Test]
    public function khach_xac_nhan_bao_gia_thi_chot_gia_va_tong_tien_dung_bang_bao_gia(): void
    {
        $khach = $this->khach();
        $p = $this->gui($khach, ['yeu_cau_rieng' => 'Thay chậu']);
        $x = $p->extras()->first();
        $this->baoGia($p, $this->admin(), ['extras' => [$x->id => ['gia' => 150000]]]);

        $this->actingAs($khach)->post(route('shop.boarding.quote.accept', $p))->assertSessionHasNoErrors();

        $p->refresh()->load(['extras', 'quotes']);
        $this->assertSame(BoardingStatus::DaXacNhan, $p->status);
        $this->assertTrue($p->daChotGia());
        $this->assertSame(BoardingExtraStatus::DaDongY, $x->fresh()->status);
        $this->assertSame(BoardingQuoteStatus::DaDongY, $p->quotes->first()->status);
        $this->assertSame((string) $p->quotes->first()->total, $p->tongTien(), 'Số phải trả đúng bằng tổng báo giá');
        $this->assertTrue($p->traOnlineDuoc());
    }

    #[Test]
    public function khach_yeu_cau_sua_thi_ve_cho_bao_gia_va_bao_gia_lan_hai_thay_lan_mot(): void
    {
        $khach = $this->khach();
        $admin = $this->admin();
        $p = $this->gui($khach, ['yeu_cau_rieng' => 'Thay chậu']);
        $x = $p->extras()->first();
        $this->baoGia($p, $admin, ['extras' => [$x->id => ['gia' => 150000]]]);

        $this->actingAs($khach)->post(route('shop.boarding.quote.revise', $p), [
            'noi_dung' => 'Xin bỏ phí đến lấy, tôi tự mang cây đến.',
            'them_yeu_cau' => 'Chụp ảnh cây mỗi tuần',
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(BoardingStatus::ChoDuyet, $p->status);
        $this->assertSame(BoardingQuoteStatus::YeuCauSua, BoardingQuote::sole()->status);
        $this->assertSame(BoardingExtraStatus::ChoBaoGia, $x->fresh()->status, 'Việc trong báo giá cũ quay về chờ báo giá');
        $this->assertSame(2, $p->extras()->count());
        $this->assertTrue(Message::where('boarding_booking_id', $p->id)->where('content', 'like', '%bỏ phí đến lấy%')->exists(),
            'Yêu cầu sửa vào hộp thư chat của nhân viên');

        $moi = BoardingExtra::where('boarding_booking_id', $p->id)->orderByDesc('id')->first();
        $this->baoGia($p, $admin, [
            'handover_fee' => 0,
            'extras' => [$x->id => ['gia' => 150000], $moi->id => ['gia' => 0]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, BoardingQuote::count());
        $this->assertSame(BoardingQuoteStatus::DangCho, BoardingQuote::where('version', 2)->sole()->status);
        $this->assertSame('1150000.00', (string) BoardingQuote::where('version', 2)->sole()->total);
    }

    #[Test]
    public function bao_gia_het_han_thi_khong_xac_nhan_duoc_va_khach_huy_duoc_luc_dang_bao_gia(): void
    {
        $khach = $this->khach();
        $p = $this->gui($khach);
        $this->baoGia($p, $this->admin());

        Carbon::setTestNow('2026-09-30 09:00:00');
        $this->actingAs($khach)->post(route('shop.boarding.quote.accept', $p))->assertSessionHasErrors('bao_gia');
        $this->assertSame(BoardingStatus::ChoKhachDuyet, $p->fresh()->status);

        $this->actingAs($khach)->post(route('shop.boarding.cancel', $p))->assertSessionHasNoErrors();
        $this->assertSame(BoardingStatus::DaHuy, $p->fresh()->status);
        $this->assertSame(BoardingQuoteStatus::DaHuy, BoardingQuote::sole()->status);
    }

    #[Test]
    public function cua_hang_rut_bao_gia_de_sua_sau_khi_trao_doi(): void
    {
        $khach = $this->khach();
        $admin = $this->admin();
        $p = $this->gui($khach, ['yeu_cau_rieng' => 'Thay chậu']);
        $x = $p->extras()->first();
        $this->baoGia($p, $admin, ['extras' => [$x->id => ['gia' => 150000]]]);

        $this->actingAs($admin)->patch(route('admin.boarding.quote.withdraw', $p), ['ly_do' => 'Khách hẹn gửi thêm 1 tháng'])->assertSessionHasNoErrors();

        $this->assertSame(BoardingStatus::ChoDuyet, $p->fresh()->status);
        $this->assertSame(BoardingQuoteStatus::ThayThe, BoardingQuote::sole()->status);
        $this->assertSame(BoardingExtraStatus::ChoBaoGia, $x->fresh()->status);
    }

    #[Test]
    public function trao_doi_qua_tin_nhan_gan_ma_phieu_va_hien_trong_hop_thu_chat(): void
    {
        $khach = $this->khach();
        $admin = $this->admin();
        $p = $this->gui($khach);

        $this->actingAs($khach)->post(route('shop.boarding.message', $p), ['noi_dung' => 'Cây nhà tôi cao 1m2, giá có đổi không?'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.boarding.message', $p), ['noi_dung' => 'Chào chị, em xem ảnh rồi báo giá ngay.'])->assertSessionHasNoErrors();

        $this->assertSame(2, Message::where('boarding_booking_id', $p->id)->count());
        $this->actingAs($khach)->get(route('shop.boarding.show', $p))->assertSee('giá có đổi không')->assertSee('em xem ảnh rồi báo giá');
        $this->actingAs($admin)->get(route('admin.boarding.show', $p))->assertSee('giá có đổi không');

        $this->actingAs($khach)->getJson(route('shop.chat.messages'))->assertOk()->assertJsonFragment(['phieu' => $p->code]);

        $this->actingAs(User::factory()->create())->post(route('shop.boarding.message', $p), ['noi_dung' => 'x'])->assertNotFound();
    }

    #[Test]
    public function khach_tai_quay_khong_co_tai_khoan_thi_khong_nhan_tin_duoc(): void
    {
        $gia = BoardingRate::create(['name' => 'Cây nhỏ', 'monthly_price' => 60000, 'is_active' => true]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.boarding.store'), [
            'customer_name' => 'Chị Lan', 'boarding_rate_id' => $gia->id, 'mode' => 'thang', 'months' => 1,
            'drop_off_on' => '2026-09-21', 'plant_name' => 'Sen đá', 'handover' => 'tu_mang', 'contact_phone' => '0912345678',
        ]);
        $p = BoardingBooking::sole();

        $this->actingAs($admin)->post(route('admin.boarding.message', $p), ['noi_dung' => 'Chào chị'])->assertSessionHasErrors('noi_dung');
        $this->actingAs($admin)->get(route('admin.boarding.show', $p))->assertSee('trao đổi qua số 0912345678');
    }
}
