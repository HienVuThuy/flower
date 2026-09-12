<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Export\PdfWriter;
use App\Services\Analytics\Export\ReportHtml;
use App\Services\Analytics\Export\XlsxWriter;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ghi báo cáo ra tệp, năm định dạng.
 * ============================================================
 * MỖI ĐỊNH DẠNG CHO MỘT VIỆC KHÁC NHAU — không phải năm cách làm cùng
 * một việc:
 *
 *   CSV   dán vào bảng tính khác, đưa vào công cụ đọc tệp phẳng
 *   XLSX  mỗi phần một trang tính, số là số — lọc, xoay bảng, cộng cột
 *   JSON  đưa vào script, Power BI, hay một hệ thống khác
 *   HTML  đọc trên màn hình, và sửa lại được trước khi gửi đi
 *   PDF   gửi cho người khác, in ra giấy — không sửa được, không lệch
 *
 * CSV và XLSX không thừa nhau: CSV là một bảng phẳng cho máy đọc, XLSX
 * là tệp nhiều trang tính có kiểu dữ liệu cho người dùng Excel. HTML và
 * PDF cũng vậy: một cái để sửa, một cái để gửi.
 *
 * ============================================================
 * GHI THẲNG RA LUỒNG nếu định dạng cho phép.
 *
 * CSV, JSON, HTML ghi thẳng ra `php://output` — bộ nhớ phẳng dù bảng dài
 * bao nhiêu. XLSX là tệp nén nên phải qua tệp tạm (đĩa, không phải bộ
 * nhớ); PDF thì bộ dựng buộc phải cầm cả tài liệu mới chia trang được.
 * Chỗ nào ép được thì ép, chỗ nào không thì nói rõ vì sao.
 */
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

    /**
     * @param  Collection<int, array{label: string, columns: list<string>, rows: list}>  $bang
     */
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

    /**
     * XLSX phải qua một TỆP TẠM.
     *
     * Tệp .xlsx là một tệp nén: mục lục của nó nằm ở cuối và chỉ viết
     * được sau khi biết mọi thứ bên trong nằm ở đâu. Không có cách nào
     * đẩy thẳng ra trình duyệt mà vẫn ghi theo luồng. Đổi lại, đây là
     * ĐĨA chứ không phải bộ nhớ — số dòng tăng thì tệp tạm to ra, còn
     * tiến trình PHP vẫn phẳng.
     *
     * `deleteFileAfterSend` dọn tệp ngay sau khi gửi xong, nên thư mục
     * tạm không phình theo số lần bấm tải.
     */
    private function xlsxTai(Collection $bang, string $tenKy, string $ten): Response
    {
        $tam = tempnam(sys_get_temp_dir(), 'bao-cao-') . '.xlsx';

        $this->xlsx->ghi($bang, $tenKy, $tam);

        return response()->download($tam, $ten, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * PDF dựng xong trong bộ nhớ rồi mới gửi.
     *
     * Không giấu điều này sau `streamDownload`: gói nó vào luồng chỉ làm
     * mã trông như đang chảy trong khi thực ra dompdf vẫn cầm cả tài liệu
     * — và người đọc mã sau này sẽ tin nhầm là nó an toàn với bảng dài.
     */
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

    private function htmlTai(Collection $bang, string $tenKy, string $ten): StreamedResponse
    {
        return response()->streamDownload(function () use ($bang, $tenKy) {
            $this->html->viet($bang, $tenKy, function (string $doan): void {
                echo $doan;
            });
        }, $ten, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
