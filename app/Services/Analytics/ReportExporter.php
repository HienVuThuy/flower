<?php

namespace App\Services\Analytics;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ghi báo cáo ra tệp, ba định dạng.
 * ============================================================
 * BA ĐỊNH DẠNG, KHÔNG THÊM MỘT THƯ VIỆN NÀO:
 *
 *   CSV   mở thẳng bằng Excel / Google Sheets, dán vào bảng tính khác
 *   JSON  đưa vào script, Power BI, hay một hệ thống khác
 *   HTML  đọc trên màn hình, và in ra PDF bằng chính trình duyệt
 *
 * KHÔNG LÀM XLSX. PhpSpreadsheet kéo theo khoảng 40MB phụ thuộc cho
 * đúng một việc mà CSV đã làm được — Excel mở CSV không khác gì. Và
 * không làm PDF bằng thư viện: mọi trình duyệt đều in ra PDF được, còn
 * một bộ dựng PDF trong PHP thì phải tự lo phông tiếng Việt.
 *
 * ============================================================
 * GHI THẲNG RA LUỒNG, không dựng chuỗi trong bộ nhớ.
 *
 * Hôm nay dữ liệu còn nhỏ, nhưng bảng bán chạy và bảng doanh thu theo
 * ngày dài ra theo thời gian. Một hàm xuất tệp ngốn bộ nhớ tỉ lệ thuận
 * với dữ liệu là quả bom hẹn giờ — nó nổ vào đúng ngày cửa hàng bán
 * được nhiều nhất.
 */
class ReportExporter
{
    public const DINH_DANG = [
        'csv' => 'CSV — mở bằng Excel, Google Sheets',
        'json' => 'JSON — đưa vào script hoặc hệ thống khác',
        'html' => 'HTML — đọc trên màn hình, in ra PDF',
    ];

    /**
     * @param  Collection<int, array{label: string, columns: list<string>, rows: list}>  $bang
     */
    public function xuat(string $dinhDang, Collection $bang, string $tenKy): StreamedResponse
    {
        $ten = 'bao-cao-' . now()->format('Ymd-His');

        return match ($dinhDang) {
            'json' => $this->json($bang, $tenKy, $ten . '.json'),
            'html' => $this->html($bang, $tenKy, $ten . '.html'),
            default => $this->csv($bang, $tenKy, $ten . '.csv'),
        };
    }

    private function csv(Collection $bang, string $tenKy, string $ten): StreamedResponse
    {
        return response()->streamDownload(function () use ($bang, $tenKy) {
            $out = fopen('php://output', 'w');

            /*
             * BOM UTF-8 ở đầu tệp.
             *
             * Không có nó, Excel trên Windows đọc CSV theo bảng mã hệ
             * thống và mọi tên sản phẩm tiếng Việt thành ký tự rác. Ba
             * byte này là khác biệt giữa một tệp dùng được và một tệp
             * người nhận phải tự đi dò bảng mã.
             */
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Báo cáo', $tenKy]);
            fputcsv($out, ['Xuất lúc', now()->format('H:i d/m/Y')]);

            foreach ($bang as $b) {
                fputcsv($out, []);
                fputcsv($out, [mb_strtoupper($b['label'])]);

                if ($b['columns'] !== []) {
                    fputcsv($out, $b['columns']);
                }

                foreach ($b['rows'] as $dong) {
                    fputcsv($out, $dong);
                }

                if ($b['rows'] === []) {
                    fputcsv($out, ['(chưa có dữ liệu trong kỳ này)']);
                }
            }

            fclose($out);
        }, $ten, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function json(Collection $bang, string $tenKy, string $ten): StreamedResponse
    {
        return response()->streamDownload(function () use ($bang, $tenKy) {
            /*
             * DÒNG THÀNH ĐỐI TƯỢNG CÓ TÊN KHOÁ, không phải mảng vị trí.
             *
             * `["Kim tiền", 12]` bắt người nhận phải đọc thứ tự cột ở chỗ
             * khác rồi tự đếm. `{"Sản phẩm": "Kim tiền", "Số lượng": 12}`
             * thì tự nó nói ra — và không hỏng khi thứ tự cột đổi.
             */
            $duLieu = [
                'ky' => $tenKy,
                'xuat_luc' => now()->toIso8601String(),
                'phan' => $bang->map(fn (array $b) => [
                    'ten' => $b['label'],
                    'cot' => $b['columns'],
                    'dong' => array_map(
                        fn (array $d) => $b['columns'] === []
                            ? $d
                            : array_combine(
                                array_slice($b['columns'], 0, count($d)),
                                $d,
                            ),
                        $b['rows'],
                    ),
                ])->values(),
            ];

            echo json_encode($duLieu, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }, $ten, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    private function html(Collection $bang, string $tenKy, string $ten): StreamedResponse
    {
        return response()->streamDownload(function () use ($bang, $tenKy) {
            $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

            /*
             * TỆP TỰ ĐỨNG MỘT MÌNH: kiểu dáng nhúng thẳng, không tham
             * chiếu tệp CSS nào. Người nhận mở tệp trên máy họ, ở đó
             * không có máy chủ nào để tải CSS về.
             */
            echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">';
            echo '<title>' . $e('Báo cáo — ' . $tenKy) . '</title>';
            echo '<style>'
                . 'body{font:14px/1.5 system-ui,-apple-system,Segoe UI,sans-serif;margin:2rem;color:#1e231f}'
                . 'h1{font-size:1.25rem;margin:0 0 .25rem}'
                . 'h2{font-size:1rem;margin:2rem 0 .5rem;border-bottom:1px solid #ddd;padding-bottom:.25rem}'
                . 'table{border-collapse:collapse;width:100%;font-size:13px}'
                . 'th,td{border:1px solid #ddd;padding:.35rem .5rem;text-align:left}'
                . 'td+td,th+th{text-align:right;white-space:nowrap}'
                . '.meta{color:#5d6660;font-size:12px;margin:0 0 1rem}'
                . '.trong{color:#5d6660;font-style:italic}'
                . '@media print{body{margin:0}h2{page-break-after:avoid}}'
                . '</style></head><body>';

            echo '<h1>' . $e('Báo cáo phân tích') . '</h1>';
            echo '<p class="meta">' . $e($tenKy) . ' &middot; xuất lúc ' . $e(now()->format('H:i d/m/Y')) . '</p>';

            foreach ($bang as $b) {
                echo '<h2>' . $e($b['label']) . '</h2>';

                if ($b['rows'] === []) {
                    echo '<p class="trong">Chưa có dữ liệu trong kỳ này.</p>';

                    continue;
                }

                echo '<table><thead><tr>';

                foreach ($b['columns'] as $c) {
                    echo '<th>' . $e($c) . '</th>';
                }

                echo '</tr></thead><tbody>';

                foreach ($b['rows'] as $dong) {
                    echo '<tr>';

                    foreach ($dong as $o) {
                        echo '<td>' . $e($o) . '</td>';
                    }

                    echo '</tr>';
                }

                echo '</tbody></table>';
            }

            echo '</body></html>';
        }, $ten, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
