<?php

namespace App\Services\Analytics\Export;

use Illuminate\Support\Collection;

/** Dựng phần thân HTML của báo cáo, cho CẢ tệp HTML lẫn tệp PDF. */
class ReportHtml
{
    public function viet(Collection $bang, string $tenKy, callable $ghi, bool $choPdf = false): void
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $ghi('<!doctype html><html lang="vi"><head><meta charset="utf-8">');
        $ghi('<title>' . $e('Báo cáo — ' . $tenKy) . '</title>');
        $ghi('<style>' . $this->css($choPdf) . '</style></head><body>');

        $ghi('<h1>' . $e('Báo cáo phân tích') . '</h1>');
        $ghi('<p class="meta">' . $e($tenKy) . ' &middot; xuất lúc ' . $e(now()->format('H:i d/m/Y')) . '</p>');

        foreach ($bang as $b) {
            $ghi('<h2>' . $e($b['label']) . '</h2>');

            if ($b['rows'] === []) {
                $ghi('<p class="trong">Chưa có dữ liệu trong kỳ này.</p>');

                continue;
            }

            $ghi('<table><thead><tr>');

            foreach ($b['columns'] as $c) {
                $ghi('<th>' . $e($c) . '</th>');
            }

            $ghi('</tr></thead><tbody>');

            foreach ($b['rows'] as $dong) {
                $ghi('<tr>');

                foreach ($dong as $o) {
                    $ghi('<td>' . $e($o) . '</td>');
                }

                $ghi('</tr>');
            }

            $ghi('</tbody></table>');
        }

        $ghi('</body></html>');
    }

    private function css(bool $choPdf): string
    {
        $chu = $choPdf
            ? '"DejaVu Sans", sans-serif'
            : 'system-ui,-apple-system,Segoe UI,sans-serif';

        $rieng = $choPdf
            ? '@page{margin:14mm 12mm}body{margin:0;font-size:10px}'
                . 'table{font-size:9px}'
                . 'h2{page-break-after:avoid}tr{page-break-inside:avoid}'
            : 'body{margin:2rem}'
                . '@media print{body{margin:0}h2{page-break-after:avoid}}';

        return 'body{font:14px/1.5 ' . $chu . ';color:#1e231f}'
            . 'h1{font-size:1.25rem;margin:0 0 .25rem}'
            . 'h2{font-size:1rem;margin:1.5rem 0 .5rem;border-bottom:1px solid #ddd;padding-bottom:.25rem}'
            . 'table{border-collapse:collapse;width:100%;font-size:13px}'
            . 'th,td{border:1px solid #ddd;padding:.35rem .5rem;text-align:left}'
            . 'td+td,th+th{text-align:right;white-space:nowrap}'
            . 'thead th{background:#f2f4f2}'
            . '.meta{color:#5d6660;font-size:12px;margin:0 0 1rem}'
            . '.trong{color:#5d6660;font-style:italic}'
            . $rieng;
    }
}
