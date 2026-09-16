<?php

namespace App\Services\Analytics\Export;

use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/** Ghi báo cáo ra tệp Excel thật (.xlsx). */
class XlsxWriter
{
    private const DAI_TEN_TRANG = 31;

    public function ghi(Collection $bang, string $tenKy, string $duongDan): void
    {
        $options = new Options;

        $options->setColumnWidth(42, 1);
        $options->setColumnWidthForRange(17, 2, 12);

        $writer = new Writer($options);
        $writer->openToFile($duongDan);

        $dam = (new Style)->setFontBold();

        $daDung = [];

        $this->trangThongTin($writer, $bang, $tenKy, $dam, $daDung);

        foreach ($bang as $b) {
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($this->tenTrang($b['label'], $daDung));

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

    private function o(mixed $v): Cell
    {
        if (is_int($v) || is_float($v)) {
            return Cell::fromValue($v);
        }

        if (is_string($v) && preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $v) === 1) {
            return Cell::fromValue($v + 0);
        }

        return Cell::fromValue($v === null ? '' : (string) $v);
    }

    private function tenTrang(string $nhan, array &$daDung): string
    {
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
