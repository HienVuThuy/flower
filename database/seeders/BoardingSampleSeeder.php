<?php

namespace Database\Seeders;

use App\Enums\BoardingPaymentMethod;
use App\Enums\UserRole;
use App\Models\BoardingBooking;
use App\Models\BoardingExtra;
use App\Models\BoardingRate;
use App\Models\BoardingWindow;
use App\Models\User;
use App\Services\Boarding\BoardingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Dữ liệu mẫu cho dịch vụ chăm cây hộ: giá tham khảo, lịch Tết, vài phiếu ở đủ các trạng thái
 * việc làm thêm (đã đồng ý, cửa hàng đề xuất, chờ báo giá) và một phiếu đang chờ khách xác nhận
 * báo giá kèm đoạn trao đổi với nhân viên.
 * ⚠️ DỮ LIỆU MẪU — giá là mức gợi ý để trình diễn; cửa hàng sửa ở Quản trị › Chăm cây hộ › Bảng giá.
 * Phiếu đi qua đúng BoardingService (lùi đồng hồ về từng thời điểm), không chèn thẳng vào bảng,
 * nên tiền, nhật ký và thông báo khớp như dùng thật. Chạy lại không tạo trùng.
 */
class BoardingSampleSeeder extends Seeder
{
    private BoardingService $dv;

    private User $admin;

    public function run(): void
    {
        $this->dv = app(BoardingService::class);
        $this->admin = User::query()->where('role', UserRole::Admin->value)->orderBy('id')->firstOrFail();

        if (BoardingBooking::query()->exists()) {
            $this->command?->warn('Đã có phiếu chăm hộ — chỉ bổ sung phần mẫu còn thiếu (việc làm thêm, báo giá, trao đổi).');
            $this->danhDauDacThu();
            $this->viecMau();
            $this->baoGiaMau();

            return;
        }

        $gia = $this->bangGia();
        $this->lichDip();

        $homNay = Carbon::now(\App\Services\Time\Gio::mui())->startOfDay();
        $tet = BoardingWindow::query()->where('group_key', 'tet')->whereDate('return_on', '>', $homNay)->orderBy('return_on')->first();

        /* 1. Đào thế gửi đến Tết, cửa hàng đến lấy, lặp lại mỗi năm — đang chăm, đã trả trước một phần. */
        $p = $this->luc($homNay->copy()->subDays(60), fn () => $this->dv->taoPhieu($this->khach('maianh@khachmau.test'), [
            'boarding_rate_id' => $gia['dao']->id, 'mode' => $tet ? 'theo_dip' : 'thang', 'months' => 4,
            'boarding_window_id' => $tet?->id, 'repeat_yearly' => 1,
            'drop_off_on' => $homNay->copy()->subDays(57)->toDateString(),
            'plant_name' => 'Đào thế trực chậu gốm, cao khoảng 1,3m', 'plant_note' => 'Mua ở cửa hàng Tết năm ngoái, sau Tết lá vàng nhiều.',
            'handover' => 'cua_hang_lay', 'contact_phone' => '0912000101', 'address' => 'Ngõ 42 Phú Diễn, Bắc Từ Liêm',
        ], null));
        $this->luc($homNay->copy()->subDays(59), fn () => $this->dv->guiBaoGia($p, $this->admin, [
            'drop_off_on' => $homNay->copy()->subDays(57)->toDateString(), 'return_on' => $p->return_on?->toDateString(),
            'monthly_price' => 300000, 'yearly_price' => 3000000, 'handover_fee' => 100000,
            'note' => 'Cây cao 1,3m, giá theo bảng. Thứ bảy nhân viên qua lấy cây buổi sáng.',
        ]));
        $this->luc($homNay->copy()->subDays(58), fn () => $this->dv->chapNhanBaoGia($p->fresh(), $p->user));
        $this->luc($homNay->copy()->subDays(57), fn () => $this->dv->nhanCay($p->fresh(), $this->admin, $homNay->copy()->subDays(57)->toDateString(), 'Cây khoẻ, rễ hơi chặt chậu.'));
        $this->luc($homNay->copy()->subDays(57), fn () => $this->dv->ghiThu($p->fresh(), $this->admin, '500000.00', BoardingPaymentMethod::ChuyenKhoan, 'Đặt cọc khi lấy cây'));
        $this->luc($homNay->copy()->subDays(40), fn () => $this->dv->capNhat($p->fresh(), $this->admin, 'Đã thay đất, cắt bớt rễ già, bón phân hữu cơ.', null));
        $this->luc($homNay->copy()->subDays(12), fn () => $this->dv->capNhat($p->fresh(), $this->admin, 'Cây ra lộc mới đều, đang cho nghỉ nước dần để chuẩn bị tuốt lá.', null));

        /* 2. Bonsai gửi 3 tháng — đã trả cây, đã thanh toán đủ. */
        $p = $this->luc($homNay->copy()->subDays(100), fn () => $this->dv->taoPhieu($this->khach('quocbao@khachmau.test'), [
            'boarding_rate_id' => $gia['bonsai']->id, 'mode' => 'thang', 'months' => 3,
            'drop_off_on' => $homNay->copy()->subDays(98)->toDateString(),
            'plant_name' => 'Bonsai tùng la hán dáng trực', 'plant_note' => 'Đi công tác 3 tháng.',
            'handover' => 'tu_mang', 'contact_phone' => '0912000102',
        ], null));
        $this->luc($homNay->copy()->subDays(99), fn () => $this->dv->guiBaoGia($p, $this->admin, [
            'drop_off_on' => $homNay->copy()->subDays(98)->toDateString(), 'return_on' => $p->return_on?->toDateString(),
            'monthly_price' => 400000, 'yearly_price' => 4000000,
        ]));
        $this->luc($homNay->copy()->subDays(99), fn () => $this->dv->chapNhanBaoGia($p->fresh(), $p->user));
        $this->luc($homNay->copy()->subDays(98), fn () => $this->dv->nhanCay($p->fresh(), $this->admin, $homNay->copy()->subDays(98)->toDateString()));
        $this->luc($homNay->copy()->subDays(60), fn () => $this->dv->capNhat($p->fresh(), $this->admin, 'Đã tỉa tán, uốn lại hai cành phụ.', null));
        $this->luc($homNay->copy()->subDays(7), fn () => $this->dv->traCay($p->fresh(), $this->admin, $homNay->copy()->subDays(7)->toDateString()));
        $this->luc($homNay->copy()->subDays(7), fn () => $this->dv->ghiThu($p->fresh(), $this->admin, $p->fresh()->tongTien(), BoardingPaymentMethod::TienMat, 'Khách trả khi nhận cây'));

        /* 3. Lan hồ điệp, chưa hẹn ngày trả — đang chăm, chưa thanh toán. */
        $p = $this->luc($homNay->copy()->subDays(22), fn () => $this->dv->taoPhieu($this->khach('thuha@khachmau.test'), [
            'boarding_rate_id' => $gia['lan']->id, 'mode' => 'khong_hen',
            'drop_off_on' => $homNay->copy()->subDays(20)->toDateString(),
            'plant_name' => 'Lan hồ điệp tím 3 cành', 'plant_note' => 'Hoa đã tàn, muốn giữ cây cho năm sau ra hoa lại.',
            'handover' => 'tu_mang', 'contact_phone' => '0912000103',
        ], null));
        $this->luc($homNay->copy()->subDays(21), fn () => $this->dv->xacNhan($p, $this->admin, ['drop_off_on' => $homNay->copy()->subDays(20)->toDateString()]));
        $this->luc($homNay->copy()->subDays(20), fn () => $this->dv->nhanCay($p->fresh(), $this->admin, $homNay->copy()->subDays(20)->toDateString()));

        /* 4. Phiếu lập tại quầy (khách không có tài khoản) — sắp đến hạn trả, đã trả tiền mặt. */
        $p = $this->luc($homNay->copy()->subDays(26), fn () => $this->dv->taoTaiQuay($this->admin, [
            'customer_name' => 'Chị Hương (khách tại quầy)',
            'boarding_rate_id' => $gia['nho']->id, 'mode' => 'thang', 'months' => 1,
            'drop_off_on' => $homNay->copy()->subDays(26)->toDateString(), 'nhan_cay_ngay' => 1,
            'plant_name' => 'Ba chậu sen đá và một chậu xương rồng', 'handover' => 'tu_mang', 'contact_phone' => '0912000104',
        ], null));
        $this->luc($homNay->copy()->subDays(26), fn () => $this->dv->ghiThu($p->fresh(), $this->admin, $p->fresh()->tongTien(), BoardingPaymentMethod::TienMat, 'Trả khi gửi cây tại quầy'));
        $this->dv->nhacDenHan();

        /* 5. Yêu cầu mới chờ cửa hàng xác nhận. */
        $this->luc($homNay->copy()->subDay(), fn () => $this->dv->taoPhieu($this->khach('minhduc@khachmau.test'), [
            'boarding_rate_id' => $gia['vua']->id, 'mode' => 'thang', 'months' => 2,
            'drop_off_on' => $homNay->copy()->addDays(3)->toDateString(),
            'plant_name' => 'Monstera chậu gốm cao 90cm', 'plant_note' => 'Chuyển nhà, cần gửi 2 tháng.',
            'handover' => 'cua_hang_lay', 'contact_phone' => '0912000105', 'address' => 'Số 8 Hồ Tùng Mậu, Cầu Giấy',
        ], null));

        $this->viecMau();
        $this->baoGiaMau();

        $this->command?->info('Đã tạo ' . count($gia) . ' dòng giá, lịch Tết và ' . BoardingBooking::count() . ' phiếu chăm hộ mẫu.');
    }

    /** Bonsai và đào / mai thế: giá trị cao, mỗi cây mỗi khác → luôn báo giá riêng. */
    private function danhDauDacThu(): void
    {
        BoardingRate::query()->whereIn('name', ['Bonsai', 'Đào, mai, quất thế chậu'])->update(['needs_quote' => true]);
    }

    /**
     * Một phiếu đang chờ khách xác nhận báo giá, kèm đoạn trao đổi với nhân viên:
     * khách hỏi → nhân viên trả lời → gửi báo giá chi tiết (tiền chăm, việc làm thêm, phí đến lấy).
     */
    private function baoGiaMau(): void
    {
        if (\App\Models\BoardingQuote::query()->where('status', 'dang_cho')->exists()) {
            return;
        }

        $p = BoardingBooking::query()->where('status', 'cho_duyet')->whereNotNull('user_id')->orderByDesc('id')->first();

        if (! $p || ! $p->user) {
            return;
        }

        $homNay = Carbon::now(\App\Services\Time\Gio::mui())->startOfDay();

        $this->luc($homNay->copy()->subHours(20), fn () => $this->dv->yeuCauThem($p, $p->user, 'Thay chậu gốm lớn hơn', 'Lá đang vàng mép, có cần thay chậu không?'));
        $this->luc($homNay->copy()->subHours(20), fn () => $this->dv->nhanTin($p->fresh(), $p->user, 'Monstera nhà mình cao khoảng 90cm, lá to. Shop xem giúp giá có khác bảng không ạ?'));
        $this->luc($homNay->copy()->subHours(18), fn () => $this->dv->nhanTin($p->fresh(), $this->admin, 'Chào anh, cây cỡ này bên em tính 150.000đ/tháng vì cần chỗ rộng và tưới nhiều hơn. Lá vàng mép thường do chậu chật, em báo giá thay chậu luôn nhé.'));

        $x = BoardingExtra::query()->where('boarding_booking_id', $p->id)->where('status', 'cho_bao_gia')->first();

        $this->luc($homNay->copy()->subHours(17), fn () => $this->dv->guiBaoGia($p->fresh(), $this->admin, [
            'drop_off_on' => $p->drop_off_on->toDateString(), 'return_on' => $p->return_on?->toDateString(),
            'monthly_price' => 150000, 'handover_fee' => 120000,
            'extras' => $x ? [$x->id => ['gia' => 180000, 'ghi_chu' => 'Gồm chậu gốm 40cm và đất trộn']] : [],
            'them' => [['viec' => 'Xử lý nấm lá', 'gia' => 50000]],
            'note' => 'Giá tháng cao hơn bảng vì cây lớn. Nhân viên qua lấy cây theo hẹn.',
        ]));
    }

    /** Việc làm thêm mẫu: khách xin → cửa hàng báo giá → khách đồng ý; cửa hàng đề xuất; một yêu cầu đang chờ báo giá. */
    private function viecMau(): void
    {
        if (BoardingExtra::query()->exists()) {
            return;
        }

        $homNay = Carbon::now(\App\Services\Time\Gio::mui())->startOfDay();
        $dangCham = fn (string $email) => BoardingBooking::query()
            ->whereIn('status', ['dang_cham', 'cho_tra'])
            ->whereHas('user', fn ($q) => $q->where('email', $email))
            ->first();

        if ($p = $dangCham('maianh@khachmau.test')) {
            $khach = $p->user;
            $x = $this->luc($homNay->copy()->subDays(10), fn () => $this->dv->yeuCauThem($p, $khach, 'Canh nụ để hoa nở đúng mùng 1 Tết', 'Nhà có khách đầu năm, muốn hoa nở đẹp nhất đúng dịp.'));
            $this->luc($homNay->copy()->subDays(9), fn () => $this->dv->baoGiaThem($x, $this->admin, '200000.00', 'Gồm tuốt lá đúng ngày và điều chỉnh nước, sáng.'));
            $this->luc($homNay->copy()->subDays(9), fn () => $this->dv->traLoiThem($x->fresh(), $khach, true));
            $this->luc($homNay->copy()->subDays(3), fn () => $this->dv->deXuat($p->fresh(), $this->admin, 'Thay chậu gốm to hơn một cỡ', '150000.00', 'Rễ đã kín chậu, thay chậu giúp cây khoẻ qua Tết.'));
        }

        if ($p = $dangCham('thuha@khachmau.test')) {
            $this->luc($homNay->copy()->subDays(2), fn () => $this->dv->yeuCauThem($p, $p->user, 'Tách chiết thêm một chậu con', 'Cây có một mầm con ở gốc.'));
        }
    }

    private function bangGia(): array
    {
        $dong = [
            'nho' => ['Cây để bàn, sen đá, xương rồng', 'easy', 60000, 600000, 'Tưới theo lịch, lau lá, xoay chậu đón sáng.'],
            'vua' => ['Cây chậu cỡ vừa (dưới 1m)', 'medium', 120000, 1200000, 'Tưới, bón phân định kỳ, cắt lá úa, phòng sâu.'],
            'lan' => ['Lan hồ điệp, lan rừng', 'hard', 150000, 1500000, 'Giữ ẩm giá thể, bón phân lan, xử lý nhiệt độ để ra hoa lại.'],
            'dao' => ['Đào, mai, quất thế chậu', 'hard', 300000, 3000000, 'Thay đất sau Tết, bón thúc, tuốt lá và canh nụ nở đúng Tết.'],
            'bonsai' => ['Bonsai', 'hard', 400000, 4000000, 'Tỉa tán, uốn dáng, chăm rễ, giữ dáng thế.'],
        ];

        $out = [];
        $thuTu = 0;

        foreach ($dong as $ma => [$ten, $doKho, $thang, $nam, $moTa]) {
            $out[$ma] = BoardingRate::query()->firstOrCreate(['name' => $ten], [
                'care_difficulty' => $doKho, 'monthly_price' => $thang, 'yearly_price' => $nam,
                'description' => $moTa, 'is_active' => true, 'sort_order' => $thuTu += 10,
                'needs_quote' => in_array($ma, ['dao', 'bonsai'], true),
            ]);
        }

        return $out;
    }

    /** Ngày Tết âm lịch theo lịch vạn niên: 06/02/2027 và 26/01/2028. Mang cây về trước Tết ~1 tuần. */
    private function lichDip(): void
    {
        foreach ([
            ['Tết Đinh Mùi 2027', '2027-01-30', '2027-02-20'],
            ['Tết Mậu Thân 2028', '2028-01-19', '2028-02-10'],
        ] as [$ten, $ve, $lai]) {
            BoardingWindow::query()->firstOrCreate(['name' => $ten], [
                'group_key' => 'tet', 'return_on' => $ve, 'take_back_on' => $lai, 'is_active' => true,
            ]);
        }
    }

    /** Khách mẫu có sẵn thì dùng; không có thì phiếu vẫn tạo được cho một tài khoản khách bất kỳ. */
    private function khach(string $email): User
    {
        return User::query()->where('email', $email)->first()
            ?? User::query()->where('role', UserRole::Customer->value)->orderBy('id')->firstOrFail();
    }

    private function luc(Carbon $moc, \Closure $lam): mixed
    {
        Carbon::setTestNow($moc->copy()->setTime(9, 30)->setTimezone(config('app.timezone')));

        try {
            return $lam();
        } finally {
            Carbon::setTestNow();
        }
    }
}
