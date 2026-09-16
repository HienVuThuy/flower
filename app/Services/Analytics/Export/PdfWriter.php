<?php

namespace App\Services\Analytics\Export;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;

/** Ghi báo cáo ra tệp PDF. */
class PdfWriter
{
    public function __construct(
        private readonly ReportHtml $html,
    ) {
    }

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

        $options->setIsPhpEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($noiDung, 'UTF-8');

        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
