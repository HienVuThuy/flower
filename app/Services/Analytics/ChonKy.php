<?php

namespace App\Services\Analytics;

use App\Services\Time\Gio;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Kỳ mà admin đang xem: một mốc dựng sẵn, hoặc một khoảng ngày tự chọn.
 * ============================================================
 * VÌ SAO LÀ MỘT LỚP chứ không phải hai biến truyền tay.
 *
 * Trước đây kỳ chỉ là một chuỗi (`'7'`, `'30'`, `'all'`) nên truyền đi
 * đâu cũng gọn. Thêm khoảng ngày tự chọn là thành BA giá trị phải đi
 * cùng nhau — và chỉ cần một liên kết quên mang theo `tu`/`den` là bấm
 * sang tab khác thì lặng lẽ nhảy về "30 ngày qua", trong khi tiêu đề
 * trang vẫn ghi khoảng cũ.
 *
 * Gom lại một chỗ thì mọi liên kết gọi `thamSo()` và không thể quên.
 *
 * ============================================================
 * MỐC KẾT THÚC LÀ MỐC MỞ.
 *
 * `den` giữ NỬA ĐÊM CỦA NGÀY HÔM SAU, không phải 23:59:59 của ngày cuối.
 * `KhoangThoiGian::apDung()` so bằng `<` ở đầu kết thúc, nên để 23:59:59
 * là mất mọi đơn đặt trong giây cuối cùng — và mất chúng một cách im
 * lặng, vào đúng ngày cuối kỳ.
 *
 * ============================================================
 * NGÀY LÀ NGÀY Ở VIỆT NAM.
 *
 * Người chọn "01/09 đến 12/09" nghĩ theo lịch treo tường của họ. Nửa đêm
 * giờ Hà Nội là 17:00 hôm trước theo giờ lưu — lấy nửa đêm UTC là gom
 * nhầm 7 tiếng đầu mỗi ngày sang ngày hôm trước (xem QĐ-245).
 */
final class ChonKy
{
    public const TUY_CHON = 'tuy-chon';

    private function __construct(
        public readonly string $ma,
        public readonly ?Carbon $tu = null,
        public readonly ?Carbon $den = null,
    ) {
    }

    public static function tuRequest(Request $request): self
    {
        return self::tuThamSo(
            $request->query('ky'),
            $request->query('tu'),
            $request->query('den'),
        );
    }

    /**
     * Đọc ba tham số URL, trả về một kỳ CHẮC CHẮN dùng được.
     *
     * Tham số lạ thì lùi về mặc định chứ không nổ: `?tu=<script>` là thứ
     * bất kỳ ai cũng gõ được vào thanh địa chỉ.
     */
    public static function tuThamSo(mixed $ky, mixed $tu = null, mixed $den = null): self
    {
        $a = self::ngay($tu);
        $b = self::ngay($den);

        if ($a === null || $b === null) {
            // Thiếu một đầu thì không có khoảng nào — dùng mốc dựng sẵn.
            return new self(AnalyticsService::hopLeKy($ky));
        }

        /*
         * Chọn ngược thì đổi chỗ, không báo lỗi.
         *
         * "Từ 12/09 đến 01/09" chỉ có một cách hiểu hợp lý, và bắt người
         * dùng bấm lại chỉ để nói điều họ đã nói rõ là phiền vô ích.
         */
        if ($b->lessThan($a)) {
            [$a, $b] = [$b, $a];
        }

        $luu = Gio::muiLuu();

        return new self(
            self::TUY_CHON,
            $a->copy()->startOfDay()->setTimezone($luu),
            // Mốc MỞ: nửa đêm của ngày kế tiếp.
            $b->copy()->addDay()->startOfDay()->setTimezone($luu),
        );
    }

    public function laTuyChon(): bool
    {
        return $this->ma === self::TUY_CHON;
    }

    /** Áp kỳ này lên bộ tính, dùng chung cho mọi trang con. */
    public function apDung(AnalyticsService $analytics): AnalyticsService
    {
        return $this->laTuyChon()
            ? $analytics->forRange($this->tu, $this->den)
            : $analytics->forPeriod($this->ma);
    }

    /** Nhãn hiện trên tiêu đề và trong tệp xuất ra. */
    public function nhan(): string
    {
        if (! $this->laTuyChon()) {
            return AnalyticsService::PERIODS[$this->ma];
        }

        return Gio::hien($this->tu)->format('d/m/Y')
            . ' – '
            // Trừ một ngày để hiện NGÀY CUỐI người dùng đã chọn, không
            // phải mốc mở nằm sau nó.
            . Gio::hien($this->den)->subDay()->format('d/m/Y');
    }

    /**
     * Tham số URL để mọi liên kết mang kỳ này đi theo.
     *
     * @return array<string, string>
     */
    public function thamSo(): array
    {
        if (! $this->laTuyChon()) {
            return ['ky' => $this->ma];
        }

        return [
            'ky' => self::TUY_CHON,
            'tu' => $this->oTu(),
            'den' => $this->oDen(),
        ];
    }

    /** Giá trị điền sẵn cho ô chọn ngày bắt đầu. */
    public function oTu(): ?string
    {
        return $this->tu ? Gio::hien($this->tu)->format('Y-m-d') : null;
    }

    /** Giá trị điền sẵn cho ô chọn ngày kết thúc — ngày người dùng thấy. */
    public function oDen(): ?string
    {
        return $this->den ? Gio::hien($this->den)->subDay()->format('Y-m-d') : null;
    }

    /**
     * Một ngày `Y-m-d` hợp lệ, hiểu theo lịch Việt Nam.
     *
     * `Carbon::parse()` nhận cả "tomorrow", "+3 days" và nhiều chuỗi lạ
     * khác. Ở đây chỉ nhận đúng dạng ô `date` gửi lên, và phải khớp lại
     * sau khi dựng — `2026-02-31` qua được createFromFormat nhưng nó dồn
     * thành 03/03.
     */
    private static function ngay(mixed $gt): ?Carbon
    {
        if (! is_string($gt) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $gt)) {
            return null;
        }

        $ngay = Carbon::createFromFormat('!Y-m-d', $gt, Gio::mui());

        return ($ngay !== false && $ngay->format('Y-m-d') === $gt) ? $ngay : null;
    }
}
