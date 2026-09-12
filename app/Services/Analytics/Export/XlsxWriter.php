<?php

namespace App\Services\Analytics\Export;

use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Ghi báo cáo ra tệp Excel thật (.xlsx).
 * ============================================================
 * VÌ SAO KHÔNG PHẢI "CSV LÀ ĐỦ" NỮA:
 *
 * CSV dồn mọi phần vào MỘT bảng, cách nhau bằng dòng trống — Excel mở ra
 * là một trang tính dài không lọc, không xoay bảng, không cộng cột được.
 * Và CSV không có kiểu dữ liệu: `1234567.00` vào Excel là CHỮ, cộng
 * không ra, và `0912345678` thì Excel tự nuốt số 0 đầu.
 *
 * Tệp này sửa đúng hai điều đó: MỖI PHẦN MỘT TRANG TÍNH, và số được ghi
 * là số.
 *
 * ============================================================
 * DÙNG openspout CHỨ KHÔNG PhpSpreadsheet.
 *
 * PhpSpreadsheet giữ cả bảng tính trong bộ nhớ. openspout ghi thẳng theo
 * luồng — đúng nguyên tắc mà bộ xuất CSV/JSON ở đây đã theo từ đầu: bộ
 * nhớ không phình theo số dòng, nên tệp không nổ vào đúng ngày cửa hàng
 * bán được nhiều nhất.
 */
class XlsxWriter
{
    /** Excel chỉ cho tên trang tính dài 31 ký tự. */
    private const DAI_TEN_TRANG = 31;

    /**
     * @param  Collection<int, array{label: string, columns: list<string>, rows: list}>  $bang
     */
    public function ghi(Collection $bang, string $tenKy, string $duongDan): void
    {
        $options = new Options;

        /*
         * ĐỘ RỘNG CỘT LÀ CÀI ĐẶT CỦA CẢ TỆP, không phải của từng trang.
         *
         * openspout khai độ rộng ở cấp workbook, nên không thể đo riêng
         * cho từng bảng. Cột đầu luôn là cột tên (sản phẩm, khách, ngày,
         * chỉ số) nên để rộng; các cột sau là số nên vừa phải. Đây là
         * thoả hiệp có chủ ý, không phải quên.
         */
        $options->setColumnWidth(42, 1);
        $options->setColumnWidthForRange(17, 2, 12);

        $writer = new Writer($options);
        $writer->openToFile($duongDan);

        $dam = (new Style)->setFontBold();

        $daDung = [];

        $this->trangThongTin($writer, $bang, $tenKy, $dam, $daDung);

        foreach ($bang as $b) {
            // Trang đầu đã là trang Thông tin, nên phần nào cũng mở trang mới.
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($this->tenTrang($b['label'], $daDung));

            /*
             * KHOÁ DÒNG TIÊU ĐỀ. Bảng tồn kho hay bán chạy cuộn vài trăm
             * dòng; không khoá thì cuộn xuống là mất tên cột và người đọc
             * phải đếm cột bằng mắt.
             */
            $sheet->setSheetView((new SheetView)->setFreezeRow(2));

            if ($b['columns'] !== []) {
                $writer->addRow(Row::fromValues($b['columns'], $dam));
            }

            foreach ($b['rows'] as $dong) {
                $writer->addRow(new Row(array_map(
                    fn ($o) => $this->o($o),
                    array_values($dong),
                )));
            }

            if ($b['rows'] === []) {
                $writer->addRow(Row::fromValues(['(chưa có dữ liệu trong kỳ này)']));
            }
        }

        $writer->close();
    }

    /**
     * Trang đầu nói tệp này là gì.
     *
     * Một tệp .xlsx bị đổi tên rồi gửi qua Zalo thì không còn gì cho biết
     * nó là kỳ nào — trong khi mọi con số bên trong chỉ có nghĩa khi biết
     * kỳ. Ghi ngay vào tệp thì không mất được.
     *
     * @param  array<string, true>  $daDung
     */
    private function trangThongTin(
        Writer $writer,
        Collection $bang,
        string $tenKy,
        Style $dam,
        array &$daDung,
    ): void {
        $writer->getCurrentSheet()->setName($this->tenTrang('Thông tin', $daDung));

        $writer->addRow(Row::fromValues(['Báo cáo phân tích'], $dam));
        $writer->addRow(Row::fromValues(['Kỳ', $tenKy]));
        $writer->addRow(Row::fromValues(['Xuất lúc', now()->format('H:i d/m/Y')]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Các phần trong tệp'], $dam));

        foreach ($bang as $b) {
            $writer->addRow(Row::fromValues([$b['label'], count($b['rows']) . ' dòng']));
        }
    }

    /**
     * Một ô: số ghi thành số, còn lại ghi thành chữ.
     *
     * ============================================================
     * VÌ SAO KHÔNG DÙNG `is_numeric()`:
     *
     * `is_numeric('0912345678')` là true, và ghi nó thành số thì Excel
     * hiện `912345678` — MẤT SỐ 0 ĐẦU, số điện thoại thành sai. Mã đơn
     * `0034` cũng vậy.
     *
     * Nên chỉ nhận đúng dạng số chuẩn: không số 0 thừa ở đầu, không dấu
     * `+`, không dấu phân cách nghìn. Tiền từ bcmath (`1234567.00`) khớp;
     * số điện thoại và mã có 0 đầu thì không, và được giữ nguyên làm chữ.
     */
    private function o(mixed $v): Cell
    {
        if (is_int($v) || is_float($v)) {
            return Cell::fromValue($v);
        }

        if (is_string($v) && preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $v) === 1) {
            return Cell::fromValue($v + 0);
        }

        // null cũng rơi vào đây và thành ô rỗng — đúng: null nghĩa là
        // KHÔNG CÓ SỐ LIỆU, ghi 0 vào là nói một câu khác hẳn.
        return Cell::fromValue($v === null ? '' : (string) $v);
    }

    /**
     * Tên trang tính hợp lệ và không trùng.
     *
     * Excel cấm `: \ / ? * [ ]`, cấm dài quá 31 ký tự, và cấm hai trang
     * trùng tên — vi phạm bất kỳ điều nào là tệp KHÔNG MỞ ĐƯỢC, chứ
     * không phải hiện xấu. Nhãn phần ở đây là câu tiếng Việt dài nên cả
     * ba đều có thể xảy ra.
     *
     * @param  array<string, true>  $daDung
     */
    private function tenTrang(string $nhan, array &$daDung): string
    {
        // Dùng str_replace chứ không biểu thức chính quy: dấu `/` và `\`
        // nằm ngay trong danh sách ký tự cấm, mà cả hai đều là thứ hay
        // làm hỏng biểu thức — đã dính đúng lỗi đó một lần lúc dựng.
        $ten = str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $nhan);
        $ten = trim(preg_replace('/\s+/u', ' ', $ten) ?? '');
        $ten = mb_substr($ten, 0, self::DAI_TEN_TRANG);

        if ($ten === '') {
            $ten = 'Phần';
        }

        $goc = $ten;
        $lan = 2;

        while (isset($daDung[mb_strtolower($ten)])) {
            $duoi = ' (' . $lan . ')';
            $ten = mb_substr($goc, 0, self::DAI_TEN_TRANG - mb_strlen($duoi)) . $duoi;
            $lan++;
        }

        $daDung[mb_strtolower($ten)] = true;

        return $ten;
    }
}
