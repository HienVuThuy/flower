<?php

namespace App\Services\Analytics\Export;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;

/**
 * Ghi báo cáo ra tệp PDF.
 * ============================================================
 * VÌ SAO GIỜ MỚI LÀM, sau khi từng từ chối:
 *
 * Lý do từ chối cũ là "một bộ dựng PDF trong PHP phải tự lo phông tiếng
 * Việt". Đã kiểm lại: dompdf mang sẵn DejaVu Sans, và bộ phông đó có đủ
 * dấu tiếng Việt — đã dựng thử rồi rút chữ ra khỏi tệp PDF, `ăâêôơưđ`,
 * `ạảãáàặẳẵắằệểễếềộổỗốồợởỡớờựửữứừ`, dấu `₫` đều đúng, không ô vuông nào.
 *
 * Còn "in từ trình duyệt cũng ra PDF" thì đúng, nhưng nó bắt người nhận
 * làm thêm ba bước và tệp in ra kèm theo đầu trang, chân trang, địa chỉ
 * URL của trình duyệt. Báo cáo gửi cho người khác thì nên là một tệp
 * hoàn chỉnh ngay lúc tải.
 *
 * ============================================================
 * TẮT MỌI THỨ ĐI RA NGOÀI MẠNG.
 *
 * `isRemoteEnabled = false`: dompdf không được tự đi tải ảnh hay CSS
 * theo đường dẫn nằm trong HTML. Nội dung báo cáo là dữ liệu từ cơ sở dữ
 * liệu (tên sản phẩm, từ khoá khách gõ) — thứ do người ngoài nhập vào.
 * Bật tuỳ chọn đó lên là biến bộ dựng PDF thành công cụ gọi hộ đường dẫn
 * nội bộ cho người lạ.
 */
class PdfWriter
{
    public function __construct(
        private readonly ReportHtml $html,
    ) {
    }

    /**
     * @param  Collection<int, array{label: string, columns: list<string>, rows: list}>  $bang
     */
    public function ghi(Collection $bang, string $tenKy): string
    {
        $noiDung = '';

        $this->html->viet(
            $bang,
            $tenKy,
            function (string $doan) use (&$noiDung): void {
                $noiDung .= $doan;
            },
            choPdf: true,
        );

        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);

        // Không cho phép HTML nhúng chạy PHP — mặc định đã tắt, khai lại
        // ở đây để đổi mặc định ở nơi khác không lặng lẽ mở nó ra.
        $options->setIsPhpEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($noiDung, 'UTF-8');

        /*
         * NGANG chứ không dọc. Bảng ở đây hay 5–7 cột (tồn kho, lãi gộp,
         * vận chuyển); trang dọc thì cột cuối bị ép nát hoặc rơi xuống
         * dòng dưới, đọc thành một cột lệch.
         */
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
