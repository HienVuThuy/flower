<?php

namespace App\Services\Boarding;

use App\Enums\BoardingMode;
use App\Models\BoardingRate;
use App\Models\BoardingWindow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * GIÁ CHĂM CÂY HỘ — một công thức cho cả lúc báo giá lẫn lúc trả cây:
 *   số tháng tính tiền = số tháng thực gửi (lố dưới NGAY_AN_HAN ngày không tính thêm tháng)
 *   tiền chăm = (số năm tròn × giá năm) + (số tháng lẻ × giá tháng)
 * Báo giá dùng thời gian DỰ KIẾN; lúc trả cây tính lại theo thời gian THỰC.
 */
class BoardingPricing
{
    public const NGAY_AN_HAN = 3;

    public const THANG_TOI_DA = 60;

    public function soThang(CarbonInterface $tu, CarbonInterface $den): int
    {
        $tu = CarbonImmutable::parse($tu)->startOfDay();
        $den = CarbonImmutable::parse($den)->startOfDay();

        if ($den->lte($tu)) {
            return 1;
        }

        $tron = 0;

        while ($tu->addMonthsNoOverflow($tron + 1)->lte($den)) {
            $tron++;
        }

        $du = (int) $tu->addMonthsNoOverflow($tron)->diffInDays($den);

        return max(1, $du > self::NGAY_AN_HAN ? $tron + 1 : $tron);
    }

    public function tienCham(string $giaThang, string $giaNam, int $thang): string
    {
        $nam = intdiv($thang, 12);
        $le = $thang % 12;

        return bcadd(bcmul($giaNam, (string) $nam, 2), bcmul($giaThang, (string) $le, 2), 2);
    }

    /**
     * Báo giá dự kiến cho một yêu cầu. Sai điều kiện (ngày trả trước ngày gửi, thiếu dịp…)
     * thì ném lỗi kiểm tra với đúng tên ô.
     *
     * @return array{return_on: ?CarbonImmutable, months: int, care_amount: string, tam_tinh: bool}
     */
    public function duKien(
        BoardingRate $rate,
        BoardingMode $mode,
        CarbonInterface $ngayGui,
        ?int $soThang = null,
        ?int $soNam = null,
        ?CarbonInterface $ngayTra = null,
        ?BoardingWindow $dip = null,
    ): array {
        $gui = CarbonImmutable::parse($ngayGui)->startOfDay();

        $tra = match ($mode) {
            BoardingMode::Thang => $gui->addMonthsNoOverflow($this->canTren($soThang, 1, self::THANG_TOI_DA, 'months')),
            BoardingMode::Nam => $gui->addYears($this->canTren($soNam, 1, intdiv(self::THANG_TOI_DA, 12), 'years')),
            BoardingMode::DenNgay => $ngayTra ? CarbonImmutable::parse($ngayTra)->startOfDay() : throw ValidationException::withMessages(['return_on' => 'Chọn ngày muốn nhận cây lại.']),
            BoardingMode::TheoDip => $dip ? CarbonImmutable::parse($dip->return_on)->startOfDay() : throw ValidationException::withMessages(['boarding_window_id' => 'Chọn dịp muốn nhận cây lại.']),
            BoardingMode::KhongHen => null,
        };

        if ($tra !== null && $tra->lte($gui)) {
            throw ValidationException::withMessages([
                $mode === BoardingMode::TheoDip ? 'boarding_window_id' : 'return_on' => 'Ngày nhận cây lại phải sau ngày gửi.',
            ]);
        }

        if ($tra !== null && $this->soThang($gui, $tra) > self::THANG_TOI_DA) {
            throw ValidationException::withMessages(['return_on' => 'Gửi tối đa ' . intdiv(self::THANG_TOI_DA, 12) . ' năm mỗi phiếu.']);
        }

        $thang = $tra === null ? 1 : $this->soThang($gui, $tra);

        return [
            'return_on' => $tra,
            'months' => $thang,
            'care_amount' => $this->tienCham((string) $rate->monthly_price, $rate->giaNam(), $thang),
            'tam_tinh' => $tra === null,
        ];
    }

    private function canTren(?int $so, int $min, int $max, string $o): int
    {
        if ($so === null || $so < $min || $so > $max) {
            throw ValidationException::withMessages([$o => "Chọn từ {$min} đến {$max}."]);
        }

        return $so;
    }
}
