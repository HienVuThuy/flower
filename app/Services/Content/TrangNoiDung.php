<?php

namespace App\Services\Content;

use App\Services\Shop\Money;
use App\Services\Shop\StoreProfile;
use Illuminate\Support\HtmlString;

/**
 * Nội dung các trang giới thiệu / chính sách: văn bản → HTML an toàn.
 * ============================================================
 * MỘT NGUỒN CHO CẢ BẢN VIẾT SẴN LẪN BẢN CỬA HÀNG SỬA.
 *
 * Trước đây nội dung mặc định nằm trong năm tệp Blade, còn bản sửa là văn
 * bản thuần — hai định dạng, nên muốn sửa một câu thì phải chép tay cả
 * trang sang ô soạn. Nay bản viết sẵn cũng là văn bản cùng định dạng
 * (resources/content/trang/*.txt), điền sẵn vào ô soạn, sửa chỗ nào thì
 * sửa chỗ đó.
 *
 * ============================================================
 * AN TOÀN TRƯỚC, ĐẸP SAU.
 *
 * Mọi chữ được escape TRƯỚC khi áp bất kỳ quy ước nào. Quy ước chỉ thêm
 * đúng vài thẻ do chính lớp này sinh ra; không có đường nào để một đoạn
 * HTML gõ trong ô soạn chạy trên trang công khai. Liên kết chỉ nhận đường
 * dẫn nội bộ (/...), http(s):// và mailto: — `javascript:` thành chữ thường.
 *
 * ============================================================
 * QUY ƯỚC (khối cách nhau bằng một dòng trống):
 *
 *   ## Tiêu đề            ### Tiêu đề nhỏ
 *   - mục danh sách       1. mục đánh số
 *   Nhãn:: nội dung       (khối thông tin — mỗi dòng một cặp)
 *   | ô | ô |             (bảng — dòng đầu là tiêu đề)
 *   > nội dung            (khung lưu ý; bên trong dùng được mọi quy ước)
 *   **chữ đậm**   [chữ](/duong-dan)
 *
 *   {ten_cua_hang} {hotline} {email} {dia_chi} {mien_phi_giao_tu}
 *   {bang_phi_giao_hang}  (một dòng riêng — bảng phí đọc từ cấu hình)
 *
 * Đoạn văn đầu tiên của trang là đoạn mở đầu (chữ lớn hơn).
 */
class TrangNoiDung
{
    /** Tệp bản viết sẵn của một trang, hoặc chuỗi rỗng nếu chưa có. */
    public function banVietSan(string $slug): string
    {
        $tep = resource_path('content/trang/' . basename($slug) . '.txt');

        return is_file($tep) ? $this->chuanHoa((string) file_get_contents($tep)) : '';
    }

    /** Cùng một chuẩn cho mọi phép so: bỏ \r và khoảng trắng hai đầu. */
    public function chuanHoa(string $van): string
    {
        return trim(str_replace("\r\n", "\n", $van));
    }

    public function html(string $van): HtmlString
    {
        return new HtmlString($this->khoi($this->chuanHoa($van), true));
    }

    /* ================= KHỐI ================= */

    private function khoi(string $van, bool $coMoDau): string
    {
        $ra = [];
        $daCoMoDau = ! $coMoDau;

        foreach (preg_split('/\n\s*\n/u', $van) as $doan) {
            $dong = array_values(array_filter(array_map('rtrim', explode("\n", trim($doan))), fn ($d) => $d !== ''));

            if ($dong === []) {
                continue;
            }

            $dau = $dong[0];

            if (str_starts_with($dau, '### ') || str_starts_with($dau, '## ')) {
                $cap = str_starts_with($dau, '### ') ? 'h3' : 'h2';
                $ra[] = '<' . $cap . '>' . $this->dong(substr($dau, $cap === 'h3' ? 4 : 3)) . '</' . $cap . '>';

                // Chữ viết liền ngay dưới tiêu đề (không cách dòng) là một khối mới.
                if (count($dong) > 1) {
                    $ra[] = $this->khoi(implode("\n", array_slice($dong, 1)), false);
                }

                continue;
            }

            if ($this->moiDong($dong, fn ($d) => str_starts_with($d, '> ') || $d === '>')) {
                $ben = implode("\n", array_map(fn ($d) => ltrim(substr($d, 1)), $dong));
                $ra[] = '<div class="static-page__notice">' . $this->khoi($ben, false) . '</div>';

                continue;
            }

            if (count($dong) === 1 && trim($dau) === '{bang_phi_giao_hang}') {
                $ra[] = $this->bangPhiGiao();

                continue;
            }

            if ($this->moiDong($dong, fn ($d) => str_starts_with($d, '- '))) {
                $ra[] = '<ul>' . implode('', array_map(fn ($d) => '<li>' . $this->dong(substr($d, 2)) . '</li>', $dong)) . '</ul>';

                continue;
            }

            if ($this->moiDong($dong, fn ($d) => preg_match('/^\d+\.\s/u', $d) === 1)) {
                $ra[] = '<ol>' . implode('', array_map(fn ($d) => '<li>' . $this->dong(preg_replace('/^\d+\.\s+/u', '', $d)) . '</li>', $dong)) . '</ol>';

                continue;
            }

            if ($this->moiDong($dong, fn ($d) => str_starts_with(ltrim($d), '|'))) {
                $ra[] = $this->bang($dong);

                continue;
            }

            if ($this->moiDong($dong, fn ($d) => str_contains($d, '::'))) {
                $ra[] = '<dl class="static-page__facts">' . implode('', array_map(function ($d) {
                    [$nhan, $noiDung] = array_map('trim', explode('::', $d, 2));

                    return '<div><dt>' . $this->dong($nhan) . '</dt><dd>' . $this->dong($noiDung) . '</dd></div>';
                }, $dong)) . '</dl>';

                continue;
            }

            // Đoạn văn: các dòng liền nhau là MỘT đoạn (dòng gõ xuống chỉ để dễ đọc khi soạn).
            $lop = $daCoMoDau ? '' : ' class="lead"';
            $daCoMoDau = true;
            $ra[] = '<p' . $lop . '>' . $this->dong(implode(' ', array_map('trim', $dong))) . '</p>';
        }

        return implode("\n", $ra);
    }

    /** @param list<string> $dong */
    private function moiDong(array $dong, callable $dieuKien): bool
    {
        foreach ($dong as $d) {
            if (! $dieuKien($d)) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $dong */
    private function bang(array $dong): string
    {
        $hang = [];

        foreach ($dong as $d) {
            $o = array_map('trim', explode('|', trim(trim($d), '|')));

            // Dòng kẻ kiểu |---|---| chỉ để dễ nhìn khi soạn.
            if ($this->moiDong($o, fn ($x) => preg_match('/^:?-{2,}:?$/', $x) === 1)) {
                continue;
            }

            $hang[] = $o;
        }

        if ($hang === []) {
            return '';
        }

        $dau = array_shift($hang);

        return '<table class="static-page__table"><thead><tr>'
            . implode('', array_map(fn ($o) => '<th>' . $this->dong($o) . '</th>', $dau))
            . '</tr></thead><tbody>'
            . implode('', array_map(fn ($h) => '<tr>' . implode('', array_map(fn ($o) => '<td>' . $this->dong($o) . '</td>', $h)) . '</tr>', $hang))
            . '</tbody></table>';
    }

    /**
     * Bảng phí giao đọc THẲNG từ config/shipping.php — bảng trên trang và
     * số tiền khách thực trả không được là hai nguồn khác nhau.
     */
    private function bangPhiGiao(): string
    {
        $dong = ['| Khu vực | Phí giao |'];

        foreach ((array) config('shipping.zones', []) as $zone) {
            $dong[] = '| ' . str_replace('|', '/', (string) ($zone['label'] ?? '')) . ' | ' . Money::format((string) ($zone['fee'] ?? 0)) . ' |';
        }

        return $this->bang($dong);
    }

    /* ================= TRONG MỘT DÒNG ================= */

    private function dong(string $chu): string
    {
        // 1) Escape TRƯỚC — mọi thẻ phía dưới đều do lớp này sinh ra.
        $chu = e($chu);

        // 2) Chỗ điền tự động; giá trị cũng được escape.
        $chu = strtr($chu, [
            '{ten_cua_hang}' => e(StoreProfile::name()),
            '{hotline}' => e((string) StoreProfile::hotline()),
            '{email}' => e((string) StoreProfile::email()),
            '{dia_chi}' => e((string) StoreProfile::address()),
            '{mien_phi_giao_tu}' => e(Money::format((string) config('shipping.free_from', 0))),
        ]);

        // 3) Liên kết — chỉ đích an toàn; đích lạ để nguyên thành chữ.
        $chu = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($m) {
            $dich = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (preg_match('#^(/(?!/)|https?://|mailto:)#i', $dich) !== 1) {
                return $m[0];
            }

            return '<a href="' . e($dich) . '">' . $m[1] . '</a>';
        }, $chu);

        // 4) Chữ đậm.
        return preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $chu);
    }
}
