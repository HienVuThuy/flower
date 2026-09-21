<?php

namespace App\Services\Boarding;

use App\Enums\BoardingHandover;
use App\Enums\BoardingExtraStatus;
use App\Enums\BoardingMode;
use App\Enums\BoardingPaymentMethod;
use App\Enums\BoardingSource;
use App\Enums\MomoFlow;
use App\Enums\BoardingStatus;
use App\Enums\NotificationType;
use App\Models\BoardingBooking;
use App\Models\BoardingEvent;
use App\Models\BoardingExtra;
use App\Models\BoardingPayment;
use App\Models\PaymentTransaction;
use App\Models\BoardingRate;
use App\Models\BoardingWindow;
use App\Models\Product;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Media\ImageStore;
use App\Services\Payment\MomoGateway;
use App\Services\Payment\PaymentException;
use App\Services\Shop\Money;
use App\Services\Shop\ThamSoKinhDoanh;
use App\Services\Time\Gio;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Vòng đời phiếu chăm cây hộ: gửi yêu cầu → xác nhận → nhận cây → chăm → trả cây (→ kỳ sau). */
class BoardingService
{
    public function __construct(
        private readonly BoardingPricing $gia,
        private readonly ImageStore $anh,
    ) {
    }

    public function homNay(): CarbonImmutable
    {
        return CarbonImmutable::now(Gio::mui())->startOfDay();
    }

    /** @param array{boarding_rate_id:int, mode:string, drop_off_on:string, months?:?int, years?:?int, return_on?:?string, boarding_window_id?:?int} $d */
    public function baoGia(array $d): array
    {
        $rate = BoardingRate::query()->where('is_active', true)->findOrFail($d['boarding_rate_id']);
        $mode = BoardingMode::from($d['mode']);
        $gui = CarbonImmutable::parse($d['drop_off_on']);

        if ($gui->lt($this->homNay())) {
            throw ValidationException::withMessages(['drop_off_on' => 'Ngày gửi không được ở quá khứ.']);
        }

        $dip = null;

        if ($mode === BoardingMode::TheoDip && ! empty($d['boarding_window_id'])) {
            $dip = BoardingWindow::query()->sapToi($this->homNay())->find($d['boarding_window_id']);
        }

        $kq = $this->gia->duKien(
            $rate,
            $mode,
            $gui,
            isset($d['months']) ? (int) $d['months'] : null,
            isset($d['years']) ? (int) $d['years'] : null,
            ! empty($d['return_on']) ? CarbonImmutable::parse($d['return_on']) : null,
            $dip,
        );

        return $kq + ['rate' => $rate, 'mode' => $mode, 'drop_off_on' => $gui, 'window' => $dip];
    }

    public function taoPhieu(User $khach, array $d, ?UploadedFile $anh): BoardingBooking
    {
        return $this->tao($khach, null, $d, $anh, BoardingSource::Online, $khach);
    }

    /**
     * Phiếu lập TẠI QUẦY từ phiếu giấy khách điền: cùng các ô như phiếu online, khách không cần
     * tài khoản. Có email trùng tài khoản thì gắn vào tài khoản đó để khách xem được trên web.
     * Cửa hàng lập nên phiếu đã xác nhận luôn; khách mang cây đến ngay thì ghi nhận cây luôn.
     */
    public function taoTaiQuay(User $admin, array $d, ?UploadedFile $anh): BoardingBooking
    {
        $khach = ! empty($d['customer_email']) ? User::query()->where('email', $d['customer_email'])->first() : null;

        $p = $this->tao($khach, trim((string) $d['customer_name']), $d, $anh, BoardingSource::TaiQuay, $admin, BoardingStatus::DaXacNhan);

        /* Tại quầy cửa hàng thấy cây tận mắt nên chốt giá luôn (bỏ trống = giá tham khảo). */
        $giaThang = ! empty($d['monthly_price']) ? bcadd((string) $d['monthly_price'], '0', 2) : (string) $p->monthly_price;
        $giaNam = ! empty($d['yearly_price']) ? bcadd((string) $d['yearly_price'], '0', 2) : (! empty($d['monthly_price']) ? bcmul($giaThang, '12', 2) : (string) $p->yearly_price);

        $p->forceFill([
            'monthly_price' => $giaThang,
            'yearly_price' => $giaNam,
            'price_agreed_at' => now(),
            'care_amount' => $this->gia->tienCham($giaThang, $giaNam, $p->return_on ? $this->gia->soThang($p->drop_off_on, $p->return_on) : 1),
        ])->save();

        if (! empty($d['nhan_cay_ngay'])) {
            $this->nhanCay($p, $admin, $p->drop_off_on->toDateString(), 'Khách mang cây đến quầy.');
        }

        return $p->fresh();
    }

    private function tao(?User $khach, ?string $tenKhach, array $d, ?UploadedFile $anh, BoardingSource $nguon, User $nguoiLap, BoardingStatus $trangThai = BoardingStatus::ChoDuyet): BoardingBooking
    {
        $bg = $this->baoGia($d);

        $sanPham = ! empty($d['product_id']) ? Product::query()->whereKey($d['product_id'])->value('id') : null;
        $duongAnh = $anh ? $this->anh->luu($anh, 'boarding') : null;

        return DB::transaction(function () use ($khach, $tenKhach, $nguon, $nguoiLap, $trangThai, $d, $bg, $sanPham, $duongAnh) {
            $p = new BoardingBooking();
            $p->forceFill([
                'code' => $this->maMoi(),
                'user_id' => $khach?->id,
                'customer_name' => $tenKhach,
                'source' => $nguon,
                'boarding_rate_id' => $bg['rate']->id,
                'product_id' => $sanPham,
                'plant_name' => trim($d['plant_name']),
                'plant_note' => $d['plant_note'] ?? null,
                'photo' => $duongAnh,
                'mode' => $bg['mode'],
                'months' => $bg['mode'] === BoardingMode::Thang ? (int) $d['months'] : null,
                'years' => $bg['mode'] === BoardingMode::Nam ? (int) $d['years'] : null,
                'boarding_window_id' => $bg['window']?->id,
                'repeat_yearly' => $bg['mode'] === BoardingMode::TheoDip && ! empty($d['repeat_yearly']),
                'drop_off_on' => $bg['drop_off_on'],
                'return_on' => $bg['return_on'],
                'handover' => BoardingHandover::from($d['handover']),
                'contact_phone' => $d['contact_phone'],
                'address' => $d['address'] ?? null,
                'customer_note' => $d['customer_note'] ?? null,
                'monthly_price' => $bg['rate']->monthly_price,
                'yearly_price' => $bg['rate']->giaNam(),
                'care_amount' => $bg['care_amount'],
                'status' => $trangThai,
            ])->save();

            $this->ghi($p, $nguoiLap, $trangThai, $nguon === BoardingSource::TaiQuay ? 'Cửa hàng lập phiếu tại quầy.' : 'Khách gửi yêu cầu chăm hộ.');

            return $p;
        });
    }

    public function xacNhan(BoardingBooking $p, User $admin, array $d): void
    {
        $this->doi($p, [BoardingStatus::ChoDuyet], function () use ($p, $d) {
            $gui = CarbonImmutable::parse($d['drop_off_on']);

            if ($p->return_on && $gui->gte($p->return_on)) {
                throw ValidationException::withMessages(['drop_off_on' => 'Ngày nhận cây phải trước ngày trả.']);
            }

            $giaThang = ($d['monthly_price'] ?? '') !== '' && $d['monthly_price'] !== null ? bcadd((string) $d['monthly_price'], '0', 2) : (string) $p->monthly_price;
            $giaNam = ($d['yearly_price'] ?? '') !== '' && $d['yearly_price'] !== null ? bcadd((string) $d['yearly_price'], '0', 2) : bcmul($giaThang, '12', 2);

            $p->forceFill([
                'drop_off_on' => $gui,
                'monthly_price' => $giaThang,
                'yearly_price' => $giaNam,
                'price_agreed_at' => now(),
                'handover_fee' => $p->handover === BoardingHandover::CuaHangLay ? (string) ($d['handover_fee'] ?? 0) : 0,
                'adjustment' => (string) ($d['adjustment'] ?? 0),
                'adjustment_reason' => $d['adjustment_reason'] ?? null,
                'status' => BoardingStatus::DaXacNhan,
            ]);

            $p->care_amount = $this->gia->tienCham($giaThang, $giaNam, $p->return_on ? $this->gia->soThang($gui, $p->return_on) : 1);
        }, $admin, 'Giá chốt cho cây này: ' . Money::format((string) (($d['monthly_price'] ?? '') !== '' && $d['monthly_price'] !== null ? $d['monthly_price'] : $p->monthly_price)) . '/tháng.' . (! empty($d['note']) ? ' ' . $d['note'] : ''));
    }

    public function tuChoi(BoardingBooking $p, User $admin, string $lyDo): void
    {
        $this->doi($p, [BoardingStatus::ChoDuyet], fn () => $p->forceFill(['status' => BoardingStatus::TuChoi, 'reject_reason' => $lyDo]), $admin, $lyDo);
    }

    public function huy(BoardingBooking $p, User $nguoi, ?string $lyDo = null): void
    {
        $this->doi($p, [BoardingStatus::ChoDuyet, BoardingStatus::DaXacNhan], fn () => $p->forceFill(['status' => BoardingStatus::DaHuy, 'reject_reason' => $lyDo]), $nguoi, $lyDo);
    }

    public function nhanCay(BoardingBooking $p, User $admin, string $ngay, ?string $ghiChu = null): void
    {
        $this->doi($p, [BoardingStatus::DaXacNhan], fn () => $p->forceFill([
            'received_on' => CarbonImmutable::parse($ngay),
            'status' => BoardingStatus::DangCham,
        ]), $admin, $ghiChu ?? 'Cửa hàng đã nhận cây.');
    }

    /** Khách cần nhận cây lại sớm hơn hẹn (hoặc chế độ "chưa hẹn ngày" báo trả). */
    public function nhanSom(BoardingBooking $p, User $khach, string $ngay): void
    {
        $ngayNhan = CarbonImmutable::parse($ngay)->startOfDay();

        if ($ngayNhan->lt($this->homNay())) {
            throw ValidationException::withMessages(['ngay' => 'Chọn ngày từ hôm nay trở đi.']);
        }

        $gap = (int) $this->homNay()->diffInDays($ngayNhan) < ThamSoKinhDoanh::so('kinh_doanh.cham_ho.bao_gap_ngay');
        $phi = $gap ? (string) ThamSoKinhDoanh::so('kinh_doanh.cham_ho.phi_gap') : '0';

        $this->doi($p, [BoardingStatus::DangCham, BoardingStatus::ChoTra], fn () => $p->forceFill([
            'return_on' => $ngayNhan,
            'early_return' => true,
            'rush_fee' => $phi,
            'status' => BoardingStatus::ChoTra,
        ]), $khach, 'Khách hẹn nhận cây ngày ' . $ngayNhan->format('d/m/Y') . ($gap && bccomp($phi, '0', 2) > 0 ? ' (gấp, có phí nhận gấp)' : '') . '.');
    }

    public function capNhat(BoardingBooking $p, User $admin, ?string $ghiChu, ?UploadedFile $anh): void
    {
        abort_if($p->status->daKetThuc(), 422, 'Phiếu đã kết thúc.');

        $e = new BoardingEvent();
        $e->forceFill([
            'boarding_booking_id' => $p->id,
            'user_id' => $admin->id,
            'kind' => BoardingEvent::CAP_NHAT,
            'note' => $ghiChu,
            'photo' => $anh ? $this->anh->luu($anh, 'boarding') : null,
        ])->save();

        $this->baoKhach($p, $ghiChu ? Str::limit($ghiChu, 180) : 'Cửa hàng gửi ảnh cây mới.');
    }

    /** Trả cây: tính lại tiền theo thời gian THỰC gửi, rồi mở kỳ sau nếu khách chọn lặp lại theo dịp. */
    public function traCay(BoardingBooking $p, User $admin, string $ngay): ?BoardingBooking
    {
        $tra = CarbonImmutable::parse($ngay)->startOfDay();

        $this->doi($p, [BoardingStatus::DangCham, BoardingStatus::ChoTra], function () use ($p, $tra) {
            $tu = CarbonImmutable::parse($p->received_on ?? $p->drop_off_on);

            if ($tra->lt($tu)) {
                throw ValidationException::withMessages(['ngay' => 'Ngày trả không được trước ngày nhận cây.']);
            }

            $p->forceFill([
                'returned_on' => $tra,
                'care_amount' => $this->gia->tienCham((string) $p->monthly_price, (string) $p->yearly_price, $this->gia->soThang($tu, $tra)),
                'status' => BoardingStatus::DaTra,
            ]);
        }, $admin, 'Cửa hàng đã trả cây.');

        return $p->repeat_yearly && $p->mode === BoardingMode::TheoDip ? $this->taoKySau($p->fresh()) : null;
    }

    /** Ghi một lần thu (dương) hoặc trả lại khách (âm). Số đã thu trên phiếu = tổng các dòng. */
    public function ghiThu(BoardingBooking $p, ?User $nguoiGhi, string $soTien, BoardingPaymentMethod $cach, ?string $ghiChu = null, ?PaymentTransaction $gd = null): BoardingPayment
    {
        return DB::transaction(function () use ($p, $nguoiGhi, $soTien, $cach, $ghiChu, $gd) {
            $p = BoardingBooking::query()->lockForUpdate()->findOrFail($p->id);

            $dong = new BoardingPayment();
            $dong->forceFill([
                'boarding_booking_id' => $p->id,
                'amount' => $soTien,
                'method' => $cach,
                'note' => $ghiChu,
                'user_id' => $nguoiGhi?->id,
                'payment_transaction_id' => $gd?->id,
                'paid_at' => now(),
            ])->save();

            $p->forceFill([
                'paid_amount' => (string) BoardingPayment::query()->where('boarding_booking_id', $p->id)->sum('amount'),
                'paid_at' => now(),
            ])->save();

            $moTa = (bccomp($soTien, '0', 2) < 0 ? 'Cửa hàng trả lại khách ' : 'Đã nhận ') . Money::format(ltrim($soTien, '-'))
                . ' (' . mb_strtolower($cach->label()) . ')' . ($ghiChu ? ' — ' . $ghiChu : '');

            $e = new BoardingEvent();
            $e->forceFill([
                'boarding_booking_id' => $p->id,
                'user_id' => $nguoiGhi?->id,
                'kind' => BoardingEvent::CAP_NHAT,
                'note' => $moTa,
            ])->save();

            if ($nguoiGhi === null) {
                $this->baoKhach($p, $moTa);
            }

            return $dong;
        });
    }

    /* ---------- YÊU CẦU THÊM: báo giá từng việc, khách đồng ý mới tính tiền ---------- */

    private const NHAN_YEU_CAU = [BoardingStatus::DaXacNhan, BoardingStatus::DangCham, BoardingStatus::ChoTra];

    public function yeuCauThem(BoardingBooking $p, User $khach, string $viec, ?string $ghiChu): BoardingExtra
    {
        $this->canNhanYeuCau($p);

        $x = new BoardingExtra();
        $x->forceFill([
            'boarding_booking_id' => $p->id,
            'title' => trim($viec),
            'customer_note' => $ghiChu,
            'proposed_by' => BoardingExtra::KHACH,
            'status' => BoardingExtraStatus::ChoBaoGia,
        ])->save();

        $this->ghiViec($p, $khach, 'Khách yêu cầu thêm: ' . $x->title . '.');

        return $x;
    }

    /** Cửa hàng tự đề xuất (cây bị sâu, cần thay chậu…) — vẫn phải chờ khách đồng ý. */
    public function deXuat(BoardingBooking $p, User $admin, string $viec, string $gia, ?string $ghiChu): BoardingExtra
    {
        $this->canNhanYeuCau($p);

        $x = new BoardingExtra();
        $x->forceFill([
            'boarding_booking_id' => $p->id,
            'title' => trim($viec),
            'proposed_by' => BoardingExtra::CUA_HANG,
            'price' => $gia,
            'shop_note' => $ghiChu,
            'status' => BoardingExtraStatus::ChoKhach,
            'quoted_at' => now(),
        ])->save();

        $this->ghiViec($p, $admin, 'Cửa hàng đề xuất: ' . $x->title . ' — ' . Money::format($gia) . '. Chờ khách đồng ý.');

        return $x;
    }

    public function baoGiaThem(BoardingExtra $x, User $admin, string $gia, ?string $ghiChu): void
    {
        $this->doiViec($x, [BoardingExtraStatus::ChoBaoGia], ['price' => $gia, 'shop_note' => $ghiChu, 'quoted_at' => now(), 'status' => BoardingExtraStatus::ChoKhach],
            $admin, 'Báo giá "' . $x->title . '": ' . Money::format($gia) . '. Chờ khách đồng ý.');
    }

    public function tuChoiThem(BoardingExtra $x, User $admin, string $lyDo): void
    {
        $this->doiViec($x, [BoardingExtraStatus::ChoBaoGia], ['shop_note' => $lyDo, 'status' => BoardingExtraStatus::CuaHangTuChoi],
            $admin, 'Cửa hàng không nhận "' . $x->title . '": ' . $lyDo);
    }

    /** Khách trả lời báo giá. Khách tại quầy không có tài khoản thì cửa hàng ghi hộ (khách đồng ý qua điện thoại). */
    public function traLoiThem(BoardingExtra $x, User $nguoi, bool $dongY): void
    {
        $this->doiViec($x, [BoardingExtraStatus::ChoKhach], ['answered_at' => now(), 'status' => $dongY ? BoardingExtraStatus::DaDongY : BoardingExtraStatus::KhachTuChoi],
            $nguoi, ($dongY ? 'Khách đồng ý: ' : 'Khách không làm: ') . $x->title . '.');
    }

    public function xongThem(BoardingExtra $x, User $admin, ?string $ghiChu, ?UploadedFile $anh): void
    {
        $this->doiViec($x, [BoardingExtraStatus::DaDongY], ['done_at' => now(), 'status' => BoardingExtraStatus::DaLam], $admin, null);

        $this->capNhat($x->booking, $admin, 'Đã làm: ' . $x->title . ($ghiChu ? ' — ' . $ghiChu : '') . '.', $anh);
    }

    private function canNhanYeuCau(BoardingBooking $p): void
    {
        if (! in_array($p->status, self::NHAN_YEU_CAU, true)) {
            throw ValidationException::withMessages(['viec' => 'Phiếu đang "' . $p->status->label() . '" nên không nhận thêm việc.']);
        }
    }

    private function doiViec(BoardingExtra $x, array $tu, array $du, User $nguoi, ?string $ghiChu): void
    {
        DB::transaction(function () use ($x, $tu, $du, $nguoi, $ghiChu) {
            $moi = BoardingExtra::query()->lockForUpdate()->findOrFail($x->id);

            if (! in_array($moi->status, $tu, true)) {
                throw ValidationException::withMessages(['viec' => 'Việc này đang "' . $moi->status->label() . '", không làm được thao tác này.']);
            }

            $moi->forceFill($du)->save();
            $x->setRawAttributes($moi->getAttributes(), true);

            if ($ghiChu !== null) {
                $this->ghiViec($x->booking, $nguoi, $ghiChu);
            }
        });
    }

    private function ghiViec(BoardingBooking $p, ?User $nguoi, string $ghiChu): void
    {
        $e = new BoardingEvent();
        $e->forceFill([
            'boarding_booking_id' => $p->id,
            'user_id' => $nguoi?->id,
            'kind' => BoardingEvent::CAP_NHAT,
            'note' => $ghiChu,
        ])->save();

        if ($nguoi === null || (int) $nguoi->id !== (int) $p->user_id) {
            $this->baoKhach($p, $ghiChu);
        }
    }

    /** Tạo lượt trả online qua MoMo cho phần khách còn thiếu. */
    public function moMomo(BoardingBooking $p, ?MomoFlow $cach = null): string
    {
        if (! $p->traOnlineDuoc()) {
            throw new PaymentException('Phiếu này hiện không có khoản nào cần trả.');
        }

        return app(MomoGateway::class)->createBoardingPayment($p, $p->conLai(), $cach);
    }

    public function thuMomo(PaymentTransaction $gd): void
    {
        $p = BoardingBooking::query()->findOrFail($gd->boarding_booking_id);

        $this->ghiThu($p, null, bcadd((string) $gd->amount, '0', 2), BoardingPaymentMethod::Momo, 'Mã giao dịch ' . ($gd->transaction_id ?? $gd->gateway_order_id), $gd);
    }

    public function doiLapLai(BoardingBooking $p, bool $bat): void
    {
        abort_unless($p->mode === BoardingMode::TheoDip && ! $p->status->daKetThuc(), 422);

        $p->forceFill(['repeat_yearly' => $bat])->save();
    }

    /** Hằng ngày: phiếu sắp đến hạn chuyển sang "Sắp trả cây" và nhắc khách. */
    public function nhacDenHan(): int
    {
        $moc = $this->homNay()->addDays(ThamSoKinhDoanh::so('kinh_doanh.cham_ho.nhac_truoc_ngay'));
        $n = 0;

        BoardingBooking::query()
            ->where('status', BoardingStatus::DangCham->value)
            ->whereNotNull('return_on')
            ->whereDate('return_on', '<=', $moc)
            ->each(function (BoardingBooking $p) use (&$n) {
                $p->forceFill(['status' => BoardingStatus::ChoTra])->save();
                $this->ghi($p, null, BoardingStatus::ChoTra, 'Sắp đến ngày trả cây ' . $p->return_on->format('d/m/Y') . '.');
                $n++;
            });

        return $n;
    }

    /** Khi admin thêm đợt dịp mới: mở kỳ sau cho những cây đang chờ lịch của đúng nhóm dịp đó. */
    public function moKyChoLich(BoardingWindow $dip): Collection
    {
        return BoardingBooking::query()
            ->where('waiting_next_window', true)
            ->whereHas('window', fn ($q) => $q->where('group_key', $dip->group_key))
            ->get()
            ->map(fn (BoardingBooking $p) => $this->taoKySau($p))
            ->filter()
            ->values();
    }

    private function taoKySau(BoardingBooking $truoc): ?BoardingBooking
    {
        $dipCu = $truoc->window;

        $dipMoi = $dipCu ? BoardingWindow::query()
            ->where('group_key', $dipCu->group_key)
            ->where('is_active', true)
            ->whereDate('return_on', '>', $dipCu->return_on)
            ->orderBy('return_on')
            ->first() : null;

        if ($dipMoi === null) {
            $truoc->forceFill(['waiting_next_window' => true])->save();

            return null;
        }

        $gui = CarbonImmutable::parse($dipCu->take_back_on ?? $truoc->returned_on ?? $this->homNay());

        return DB::transaction(function () use ($truoc, $dipMoi, $gui) {
            $truoc->forceFill(['waiting_next_window' => false])->save();

            $p = $truoc->replicate([
                'code', 'status', 'received_on', 'returned_on', 'early_return', 'rush_fee', 'adjustment',
                'adjustment_reason', 'paid_amount', 'paid_at', 'reject_reason', 'handover_fee', 'waiting_next_window',
            ]);

            $p->forceFill([
                'code' => $this->maMoi(),
                'parent_id' => $truoc->id,
                'boarding_window_id' => $dipMoi->id,
                'drop_off_on' => $gui,
                'return_on' => $dipMoi->return_on,
                'price_agreed_at' => null,
                'care_amount' => $this->gia->tienCham((string) $truoc->monthly_price, (string) $truoc->yearly_price, $this->gia->soThang($gui, $dipMoi->return_on)),
                'status' => BoardingStatus::ChoDuyet,
            ])->save();

            $this->ghi($p, null, BoardingStatus::ChoDuyet, 'Kỳ lặp lại của phiếu ' . $truoc->code . ': nhận cây lại sau dịp, trả trước ' . $dipMoi->name . '. Tạm tính theo giá đã chốt kỳ trước; cửa hàng xác nhận lại giá.');

            return $p;
        });
    }

    /** Đổi trạng thái có khoá dòng: hai người bấm cùng lúc không làm phiếu nhảy sai. */
    private function doi(BoardingBooking $p, array $tuTrangThai, \Closure $lam, ?User $nguoi, ?string $ghiChu): void
    {
        DB::transaction(function () use ($p, $tuTrangThai, $lam, $nguoi, $ghiChu) {
            $moi = BoardingBooking::query()->lockForUpdate()->findOrFail($p->id);

            if (! in_array($moi->status, $tuTrangThai, true)) {
                throw ValidationException::withMessages(['status' => 'Phiếu đang ở trạng thái "' . $moi->status->label() . '", không làm được thao tác này.']);
            }

            $p->setRawAttributes($moi->getAttributes(), true);
            $lam();
            $p->save();

            $this->ghi($p, $nguoi, $p->status, $ghiChu);
        });
    }

    private function ghi(BoardingBooking $p, ?User $nguoi, BoardingStatus $tt, ?string $ghiChu): void
    {
        $e = new BoardingEvent();
        $e->forceFill([
            'boarding_booking_id' => $p->id,
            'user_id' => $nguoi?->id,
            'kind' => BoardingEvent::DOI_TRANG_THAI,
            'status' => $tt,
            'note' => $ghiChu,
        ])->save();

        if ($nguoi === null || (int) $nguoi->id !== (int) $p->user_id) {
            $this->baoKhach($p, $tt->label() . ($ghiChu ? ' — ' . $ghiChu : ''));
        }
    }

    private function baoKhach(BoardingBooking $p, string $noiDung): void
    {
        if ($p->user_id === null) {
            return;
        }

        (new UserNotification())->forceFill([
            'user_id' => $p->user_id,
            'type' => NotificationType::ChamHo,
            'boarding_booking_id' => $p->id,
            'note' => Str::limit($noiDung, 195),
        ])->save();
    }

    private function maMoi(): string
    {
        do {
            $ma = 'CH' . now()->format('ymd') . strtoupper(Str::random(4));
        } while (BoardingBooking::where('code', $ma)->exists());

        return $ma;
    }
}
