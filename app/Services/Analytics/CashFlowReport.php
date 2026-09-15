<?php

namespace App\Services\Analytics;

use App\Models\Expense;
use Illuminate\Support\Carbon;

/**
 * Thu chi một tháng: DÒNG TIỀN và LÃI RÒNG ƯỚC TÍNH — hai câu hỏi, hai bảng.
 * ============================================================
 * VÌ SAO TÁCH HAI BẢNG. "Tháng này cửa hàng có lời không" và "tháng này
 * tiền trong két tăng hay giảm" là hai câu khác nhau. Nhập 20 triệu hàng
 * để bán dần cả quý: dòng tiền tháng này âm 20 triệu, nhưng lãi không âm
 * 20 triệu — phần lớn số hàng đó chưa bán. Gộp một con số là trả lời sai
 * cả hai câu.
 *
 *   DÒNG TIỀN  = tiền vào từ đơn đã giao − tiền thu mua − chi phí vận hành
 *   LÃI RÒNG   = lãi gộp hàng + lãi gộp hoa − chi phí vận hành
 *                − cửa hàng bù ship − hoàn tiền cho đơn đã giao
 *
 * KHÔNG TÍNH LẠI GÌ ĐÃ CÓ: doanh thu, lãi gộp, lãi hoa, thu mua, bù ship
 * đọc từ đúng báo cáo của trang riêng của chúng. Hai trang không được nói
 * hai con số khác nhau cho cùng một câu hỏi.
 *
 * NÓI RA KHI CHƯA ĐỦ: lãi gộp hàng chỉ tính trên phần doanh thu có giá
 * vốn. Phủ chưa đủ 100% thì "lãi ròng" thiếu một phần giá vốn — con số
 * cao hơn sự thật — và báo cáo phải nói điều đó, không để nó đứng một mình.
 */
class CashFlowReport
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {
    }

    /**
     * Khoảng thời gian của một tháng theo giờ Việt Nam, trả về theo giờ lưu.
     *
     * @param  string  $thang  dạng YYYY-MM
     */
    public static function khoangThang(string $thang): KhoangThoiGian
    {
        $mui = \App\Services\Time\Gio::mui();
        $dau = Carbon::createFromFormat('!Y-m', $thang, $mui);

        return new KhoangThoiGian(
            $dau->copy()->setTimezone((string) config('app.timezone')),
            $dau->copy()->addMonth()->setTimezone((string) config('app.timezone')),
        );
    }

    /** @return array<string, mixed> */
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

        $tienVao = bcadd((string) $don['net_revenue'], '0', 2);
        $tienRa = bcadd($thuMua['tong'], $chiPhi['tong'], 2);

        $buShipTien = ($buShip['tinh_duoc'] ?? 0) > 0 && (float) $buShip['chenh'] > 0
            ? bcadd((string) $buShip['chenh'], '0', 2)
            : '0.00';

        $laiHoa = $hoa['lai_gop'] ?? '0.00';

        $laiRong = bcsub(
            bcsub(
                bcsub(
                    bcsub(bcadd($loi['lai_gop'], $laiHoa, 2), $chiPhi['tong'], 2),
                    $buShipTien,
                    2,
                ),
                (string) $loi['hoan_tien'],
                2,
            ),
            // Giá vốn quà tặng: tiền hàng thật đi ra kèm đơn, doanh thu 0.
            $loi['chi_phi_qua']['tien'],
            2,
        );

        return [
            'thang' => $thang,

            'dong_tien' => [
                'tien_vao' => $tienVao,
                'thu_mua' => $thuMua['tong'],
                'chi_phi' => $chiPhi['tong'],
                'tien_ra' => $tienRa,
                'chenh' => bcsub($tienVao, $tienRa, 2),
            ],

            'lai' => [
                'lai_gop_hang' => $loi['lai_gop'],
                'lai_gop_hoa' => $hoa['lai_gop'],   // null = chưa đóng lô nào
                'chi_phi' => $chiPhi['tong'],
                'bu_ship' => $buShipTien,
                'hoan_tien' => bcadd((string) $loi['hoan_tien'], '0', 2),
                'chi_phi_qua' => $loi['chi_phi_qua']['tien'],
                'lai_rong' => $laiRong,

                // % doanh thu hàng có giá vốn. < 100 thì lãi ròng cao hơn sự thật.
                'ti_le_phu' => $loi['ti_le_phu'],
                'co_lo_hoa_mo' => $hoa['so_lo_con_mo'] > 0,
            ],

            'chi_phi_theo_loai' => $chiPhi['theo_loai'],
        ];
    }

    /**
     * Chi phí vận hành trong một khoảng — một chỗ tính, trang Lợi nhuận dùng lại.
     *
     * @return array{tong: string, so_khoan: int, theo_loai: array<string, string>}
     */
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
