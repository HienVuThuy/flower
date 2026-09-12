<?php

namespace App\Services\Analytics\Export;

use Illuminate\Support\Collection;

/**
 * Dựng phần thân HTML của báo cáo, cho CẢ tệp HTML lẫn tệp PDF.
 * ============================================================
 * VÌ SAO MỘT NƠI: tệp HTML và tệp PDF là cùng một báo cáo, chỉ khác cách
 * người nhận mở nó ra. Viết hai lần thì sớm muộn một bên có cột mà bên
 * kia không có, và không có gì báo — hai người cầm hai tệp cãi nhau về
 * cùng một kỳ.
 *
 * ============================================================
 * GHI QUA MỘT HÀM GỌI LẠI, không trả về chuỗi.
 *
 * Tệp HTML được ghi thẳng ra luồng (bộ nhớ phẳng dù bảng dài bao nhiêu);
 * còn dompdf buộc phải cầm cả tài liệu trong bộ nhớ mới dựng được trang.
 * Nhận `$ghi` làm tham số thì một bên truyền `echo`, bên kia truyền cái
 * hộp gom chuỗi — không bên nào phải nhân nhượng bên nào.
 */
class ReportHtml
{
    /**
     * @param  Collection<int, array{label: string, columns: list<string>, rows: list}>  $bang
     * @param  callable(string): void  $ghi
     */
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

    /**
     * Kiểu dáng NHÚNG THẲNG, không tham chiếu tệp CSS nào: người nhận mở
     * tệp trên máy họ, ở đó không có máy chủ nào để tải CSS về.
     */
    private function css(bool $choPdf): string
    {
        /*
         * PHÔNG CHO PDF PHẢI GỌI ĐÍCH DANH "DejaVu Sans".
         *
         * dompdf không đi hỏi phông của hệ điều hành; nó chỉ có mấy bộ
         * dựng sẵn, và trong đó chỉ DejaVu có đủ dấu tiếng Việt. Để
         * `system-ui` như bản HTML thì dompdf rơi về Helvetica và mọi
         * chữ có dấu thành ô vuông.
         */
        $chu = $choPdf
            ? '"DejaVu Sans", sans-serif'
            : 'system-ui,-apple-system,Segoe UI,sans-serif';

        $rieng = $choPdf
            // Lề do @page lo, nên body không tự thêm lề nữa. Chữ nhỏ hơn
            // một nấc vì trang A4 hẹp hơn màn hình.
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
