<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Export\PdfWriter;
use App\Services\Analytics\Export\ReportHtml;
use App\Services\Analytics\Export\XlsxWriter;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ghi báo cáo ra tệp, năm định dạng. */
class ReportExporter
{
    public const DINH_DANG = [
        'csv' => 'CSV — mở bằng Excel, Google Sheets',
        'xlsx' => 'XLSX — tệp Excel, mỗi phần một trang tính',
        'json' => 'JSON — đưa vào script hoặc hệ thống khác',
        'html' => 'HTML — đọc trên màn hình, sửa lại được',
        'pdf' => 'PDF — gửi cho người khác, in ra giấy',
    ];

    public function __construct(
        private readonly ReportHtml $html,
        private readonly XlsxWriter $xlsx,
        private readonly PdfWriter $pdf,
    ) {
    }

    public function xuat(string $dinhDang, Collection $bang, string $tenKy): Response
    {
        $ten = 'bao-cao-' . now()->format('Ymd-His');

        return match ($dinhDang) {
            'xlsx' => $this->xlsxTai($bang, $tenKy, $ten . '.xlsx'),
            'json' => $this->json($bang, $tenKy, $ten . '.json'),
            'html' => $this->htmlTai($bang, $tenKy, $ten . '.html'),
            'pdf' => $this->pdfTai($bang, $tenKy, $ten . '.pdf'),
            default => $this->csv($bang, $tenKy, $ten . '.csv'),
        };
    }

    private function csv(Collection $bang, string $tenKy, string $ten): StreamedResponse
    {
        return response()->streamDownload(function () use ($bang, $tenKy) {
            $out = fopen('php://output', 'w');

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

    private function xlsxTai(Collection $bang, string $tenKy, string $ten): Response
    {
        $tam = tempnam(sys_get_temp_dir(), 'bao-cao-') . '.xlsx';

        $this->xlsx->ghi($bang, $tenKy, $tam);

        return response()->download($tam, $ten, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function pdfTai(Collection $bang, string $tenKy, string $ten): Response
    {
        return response($this->pdf->ghi($bang, $tenKy), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $ten . '"',
        ]);
    }

    private function json(Collection $bang, string $tenKy, string $ten): StreamedResponse
    {
        return response()->streamDownload(function () use ($bang, $tenKy) {
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

    private function htmlTai(Collection $bang, string $tenKy, string $ten): StreamedResponse
    {
        return response()->streamDownload(function () use ($bang, $tenKy) {
            $this->html->viet($bang, $tenKy, function (string $doan): void {
                echo $doan;
            });
        }, $ten, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
