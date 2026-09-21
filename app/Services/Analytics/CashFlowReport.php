<?php

namespace App\Services\Analytics;

use App\Models\BoardingPayment;
use App\Models\Expense;
use Illuminate\Support\Carbon;

/** Thu chi một tháng: DÒNG TIỀN và LÃI RÒNG ƯỚC TÍNH — hai câu hỏi, hai bảng. */
class CashFlowReport
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {
    }

    public static function khoangThang(string $thang): KhoangThoiGian
    {
        $mui = \App\Services\Time\Gio::mui();
        $dau = Carbon::createFromFormat('!Y-m', $thang, $mui);

        return new KhoangThoiGian(
            $dau->copy()->setTimezone((string) config('app.timezone')),
            $dau->copy()->addMonth()->setTimezone((string) config('app.timezone')),
        );
    }

    public function thang(string $thang): array
    {
        $khoang = self::khoangThang($thang);

        $this->analytics->forRange($khoang->tu, $khoang->den);
        $don = $this->analytics->orderStats();
        $buShip = $this->analytics->shippingCost();

        $loi = app(ProfitReport::class)->trong($khoang)->baoCao();
        $hoa = app(FlowerCostReport::class)->trong($khoang)->baoCao();
        $thuMua = app(PurchasingReport::class)->trong($khoang)->tongQuan();

        $chiPhi = self::chiPhi($khoang);
        $chamHo = self::chamHo($khoang);

        $tienVao = bcadd((string) $don['net_revenue'], $chamHo, 2);
        $tienRa = bcadd($thuMua['tong'], $chiPhi['tong'], 2);

        $buShipTien = ($buShip['tinh_duoc'] ?? 0) > 0 && (float) $buShip['chenh'] > 0
            ? bcadd((string) $buShip['chenh'], '0', 2)
            : '0.00';

        $laiHoa = $hoa['lai_gop'] ?? '0.00';

        $laiRong = bcsub(
            bcsub(
                bcsub(
                    bcsub(bcadd(bcadd($loi['lai_gop'], $laiHoa ?? '0', 2), $chamHo, 2), $chiPhi['tong'], 2),
                    $buShipTien,
                    2,
                ),
                (string) $loi['hoan_tien'],
                2,
            ),
            $loi['chi_phi_qua']['tien'],
            2,
        );

        return [
            'thang' => $thang,

            'dong_tien' => [
                'tien_vao' => $tienVao,
                'tu_don' => bcadd((string) $don['net_revenue'], '0', 2),
                'cham_ho' => $chamHo,
                'thu_mua' => $thuMua['tong'],
                'chi_phi' => $chiPhi['tong'],
                'tien_ra' => $tienRa,
                'chenh' => bcsub($tienVao, $tienRa, 2),
            ],

            'lai' => [
                'lai_gop_hang' => $loi['lai_gop'],
                'lai_gop_hoa' => $hoa['lai_gop'],
                'cham_ho' => $chamHo,
                'chi_phi' => $chiPhi['tong'],
                'bu_ship' => $buShipTien,
                'hoan_tien' => bcadd((string) $loi['hoan_tien'], '0', 2),
                'chi_phi_qua' => $loi['chi_phi_qua']['tien'],
                'lai_rong' => $laiRong,

                'ti_le_phu' => $loi['ti_le_phu'],
                'co_lo_hoa_mo' => $hoa['so_lo_con_mo'] > 0,
            ],

            'chi_phi_theo_loai' => $chiPhi['theo_loai'],
        ];
    }

    /**
     * Tiền dịch vụ chăm cây hộ thực thu trong kỳ (trừ khoản trả lại), theo NGÀY THU TIỀN.
     * Không có giá vốn riêng: công chăm, phân, nước đã nằm trong chi phí vận hành.
     */
    public static function chamHo(KhoangThoiGian $khoang): string
    {
        $q = BoardingPayment::query();
        $khoang->apDung($q, 'paid_at');

        return bcadd((string) $q->sum('amount'), '0', 2);
    }

    public static function chiPhi(KhoangThoiGian $khoang): array
    {
        $q = Expense::query();
        $khoang->apDungNgay($q, 'spent_on');

        $tong = '0.00';
        $theoLoai = [];
        $soKhoan = 0;

        foreach ($q->get(['category', 'amount']) as $c) {
            $loai = $c->category->value;
            $theoLoai[$loai] = bcadd($theoLoai[$loai] ?? '0.00', (string) $c->amount, 2);
            $tong = bcadd($tong, (string) $c->amount, 2);
            $soKhoan++;
        }

        uasort($theoLoai, fn ($a, $b) => bccomp($b, $a, 2));

        return ['tong' => $tong, 'so_khoan' => $soKhoan, 'theo_loai' => $theoLoai];
    }
}
