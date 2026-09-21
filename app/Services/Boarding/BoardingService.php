<?php

namespace App\Services\Boarding;

use App\Enums\BoardingHandover;
use App\Enums\BoardingMode;
use App\Enums\BoardingStatus;
use App\Enums\NotificationType;
use App\Models\BoardingBooking;
use App\Models\BoardingEvent;
use App\Models\BoardingRate;
use App\Models\BoardingWindow;
use App\Models\Product;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Media\ImageStore;
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
        $bg = $this->baoGia($d);

        $sanPham = ! empty($d['product_id']) ? Product::query()->whereKey($d['product_id'])->value('id') : null;
        $duongAnh = $anh ? $this->anh->luu($anh, 'boarding') : null;

        return DB::transaction(function () use ($khach, $d, $bg, $sanPham, $duongAnh) {
            $p = new BoardingBooking();
            $p->forceFill([
                'code' => $this->maMoi(),
                'user_id' => $khach->id,
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
                'status' => BoardingStatus::ChoDuyet,
            ])->save();

            $this->ghi($p, $khach, BoardingStatus::ChoDuyet, 'Khách gửi yêu cầu chăm hộ.');

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

            $p->forceFill([
                'drop_off_on' => $gui,
                'handover_fee' => $p->handover === BoardingHandover::CuaHangLay ? (string) ($d['handover_fee'] ?? 0) : 0,
                'adjustment' => (string) ($d['adjustment'] ?? 0),
                'adjustment_reason' => $d['adjustment_reason'] ?? null,
                'status' => BoardingStatus::DaXacNhan,
            ]);

            if ($p->return_on) {
                $p->care_amount = $this->gia->tienCham((string) $p->monthly_price, (string) $p->yearly_price, $this->gia->soThang($gui, $p->return_on));
            }
        }, $admin, $d['note'] ?? null);
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

    /** Ghi tiền đã thu (âm = cửa hàng trả lại khách). */
    public function ghiThu(BoardingBooking $p, User $admin, string $soTien, ?string $ghiChu = null): void
    {
        DB::transaction(function () use ($p, $admin, $soTien, $ghiChu) {
            $p = BoardingBooking::query()->lockForUpdate()->findOrFail($p->id);

            $p->forceFill([
                'paid_amount' => bcadd((string) $p->paid_amount, $soTien, 2),
                'paid_at' => now(),
            ])->save();

            $e = new BoardingEvent();
            $e->forceFill([
                'boarding_booking_id' => $p->id,
                'user_id' => $admin->id,
                'kind' => BoardingEvent::CAP_NHAT,
                'note' => (bccomp($soTien, '0', 2) < 0 ? 'Cửa hàng trả lại khách ' : 'Cửa hàng đã thu ') . \App\Services\Shop\Money::format(ltrim($soTien, '-')) . ($ghiChu ? ' — ' . $ghiChu : ''),
            ])->save();
        });
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

        $rate = $truoc->rate()->first();
        $gui = CarbonImmutable::parse($dipCu->take_back_on ?? $truoc->returned_on ?? $this->homNay());

        return DB::transaction(function () use ($truoc, $dipMoi, $rate, $gui) {
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
                'monthly_price' => $rate->monthly_price,
                'yearly_price' => $rate->giaNam(),
                'care_amount' => $this->gia->tienCham((string) $rate->monthly_price, $rate->giaNam(), $this->gia->soThang($gui, $dipMoi->return_on)),
                'status' => BoardingStatus::ChoDuyet,
            ])->save();

            $this->ghi($p, null, BoardingStatus::ChoDuyet, 'Kỳ lặp lại của phiếu ' . $truoc->code . ': nhận cây lại sau dịp, trả trước ' . $dipMoi->name . '.');

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

        if ($nguoi === null || $nguoi->id !== $p->user_id) {
            $this->baoKhach($p, $tt->label() . ($ghiChu ? ' — ' . $ghiChu : ''));
        }
    }

    private function baoKhach(BoardingBooking $p, string $noiDung): void
    {
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
