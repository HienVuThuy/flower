<?php

namespace App\Services\Tax;

use App\Models\Setting;

/**
 * NƠI DUY NHẤT tính thuế giá trị gia tăng.
 * ============================================================
 * GIÁ NIÊM YẾT ĐÃ BAO GỒM VAT. Vì thế hàm chính ở đây là `extract()` —
 * TÁCH phần thuế ra khỏi một số tiền đã có thuế, chứ không phải cộng
 * thêm thuế vào.
 *
 * Công thức:
 *
 *     thuế = tổng − tổng / (1 + thuế suất)
 *
 * Ví dụ với 8%: một đơn 108.000₫ chứa 8.000₫ tiền thuế và 100.000₫ tiền
 * hàng chưa thuế. KHÔNG phải 108.000 × 0,08 = 8.640₫ — đó là lỗi hay gặp
 * nhất khi làm thuế kiểu giá-đã-gồm, và nó làm con số lệch 8% mãi mãi.
 *
 * ============================================================
 * DÙNG bcmath, KHÔNG dùng số thực.
 *
 * Cùng lý do với mọi phép tính tiền khác trong dự án. `0.1 + 0.2` trong
 * số thực không bằng `0.3`, và một cột thuế lệch vài đồng mỗi đơn là thứ
 * kế toán sẽ phát hiện vào cuối năm — sau khi đã có vài nghìn đơn.
 *
 * Thuế suất thì là số thực (0.08), nhưng nó được đổi sang chuỗi ngay khi
 * vào phép tính.
 */
class TaxCalculator
{
    /** Khoá cấu hình mà admin sửa được ở trang Cấu hình. */
    public const SETTING_KEY = 'tax_rate';

    /**
     * Thuế suất đang áp dụng, dạng thập phân ('0.08').
     *
     * ƯU TIÊN GIÁ TRỊ ADMIN ĐẶT, rồi mới tới mặc định trong config. Cửa
     * hàng phải hỏi kế toán rồi tự chỉnh — mã nguồn không biết mặt hàng
     * của họ chịu thuế suất nào.
     */
    public function rate(): string
    {
        if (! $this->enabled()) {
            return '0';
        }

        $luu = Setting::get(self::SETTING_KEY);

        /*
         * Chuỗi rỗng và giá trị lạ đều rơi về mặc định.
         *
         * Một ô nhập bị xoá trắng KHÔNG được biến thành thuế suất 0 —
         * đó là "chưa điền", không phải "miễn thuế". Muốn miễn thuế thì
         * tắt hẳn ở config, và đó là một quyết định rõ ràng.
         */
        if (is_numeric($luu) && (float) $luu >= 0 && (float) $luu < 1) {
            return (string) (float) $luu;
        }

        return (string) (float) config('tax.default_rate', 0.08);
    }

    public function enabled(): bool
    {
        return (bool) config('tax.enabled', true);
    }

    /** Thuế suất hiển thị cho người đọc: 8 (phần trăm). */
    public function ratePercent(): string
    {
        return rtrim(rtrim(bcmul($this->rate(), '100', 2), '0'), '.') ?: '0';
    }

    /**
     * Phần thuế NẰM TRONG một số tiền đã bao gồm thuế.
     *
     * @param  string  $grossAmount  số tiền đã gồm thuế, dạng chuỗi bcmath
     */
    public function extract(string $grossAmount, ?string $rate = null): string
    {
        $rate ??= $this->rate();

        if (bccomp($rate, '0', 6) <= 0) {
            return '0.00';
        }

        $scale = (int) config('tax.scale', 2);

        /*
         * Chia ở ĐỘ CHÍNH XÁC CAO HƠN rồi mới trừ và làm tròn.
         *
         * Chia thẳng ở 2 chữ số thì phần dư bị cắt trước khi trừ, và sai
         * số dồn vào chính con số thuế. Dùng thêm 4 chữ số đệm rồi làm
         * tròn một lần duy nhất ở bước cuối.
         */
        $net = bcdiv($grossAmount, bcadd('1', $rate, 6), $scale + 4);
        $tax = bcsub($grossAmount, $net, $scale + 4);

        return $this->lamTron($tax, $scale);
    }

    /** Phần tiền hàng CHƯA thuế trong một số tiền đã gồm thuế. */
    public function net(string $grossAmount, ?string $rate = null): string
    {
        return bcsub($grossAmount, $this->extract($grossAmount, $rate), (int) config('tax.scale', 2));
    }

    /**
     * Làm tròn nửa lên — bcmath không có hàm làm tròn.
     *
     * bcadd() với scale nhỏ hơn chỉ CẮT phần dư (làm tròn xuống), nên
     * 8.005 thành 8.00 thay vì 8.01. Cộng thêm nửa đơn vị cuối rồi cắt
     * là cách làm tròn nửa lên chuẩn với bcmath.
     */
    private function lamTron(string $so, int $scale): string
    {
        $nua = '0.' . str_repeat('0', $scale) . '5';

        return bcadd($so, bccomp($so, '0', $scale + 4) < 0 ? '-' . $nua : $nua, $scale);
    }
}
