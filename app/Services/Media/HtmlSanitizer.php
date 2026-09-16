<?php

namespace App\Services\Media;

/** Làm sạch HTML của bài Cẩm nang. */
class HtmlSanitizer
{
    private const THE_CHO_PHEP = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'h2', 'h3', 'h4',
        'ul', 'ol', 'li',
        'blockquote', 'figure', 'figcaption',
        'a', 'code', 'pre',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'hr',
    ];

    private const THUOC_TINH_CHO_PHEP = [
        'a' => ['href', 'title'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
    ];

    private const GIAO_THUC_CHO_PHEP = ['http', 'https', 'mailto', 'tel'];

    public function lamSach(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');

        $truoc = libxml_use_internal_errors(true);

        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="goc">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($truoc);

        $goc = $doc->getElementById('goc');

        if (! $goc) {
            return '';
        }

        $this->duyet($goc);

        $ket = '';

        foreach ($goc->childNodes as $con) {
            $ket .= $doc->saveHTML($con);
        }

        return trim($ket);
    }

    private function duyet(\DOMNode $nut): void
    {
        for ($i = $nut->childNodes->length - 1; $i >= 0; $i--) {
            $con = $nut->childNodes->item($i);

            if (! $con instanceof \DOMElement) {
                continue;
            }

            $ten = strtolower($con->nodeName);

            if (in_array($ten, ['script', 'style'], true)) {
                $nut->removeChild($con);

                continue;
            }

            $this->duyet($con);

            if (! in_array($ten, self::THE_CHO_PHEP, true)) {
                while ($con->firstChild) {
                    $nut->insertBefore($con->firstChild, $con);
                }

                $nut->removeChild($con);

                continue;
            }

            $this->donThuocTinh($con, $ten);
        }
    }

    private function donThuocTinh(\DOMElement $el, string $ten): void
    {
        $choPhep = self::THUOC_TINH_CHO_PHEP[$ten] ?? [];

        for ($i = $el->attributes->length - 1; $i >= 0; $i--) {
            $thuocTinh = $el->attributes->item($i);

            if (! in_array(strtolower($thuocTinh->nodeName), $choPhep, true)) {
                $el->removeAttribute($thuocTinh->nodeName);
            }
        }

        if ($ten === 'a') {
            $this->donLink($el);
        }
    }

    private function donLink(\DOMElement $el): void
    {
        $href = trim($el->getAttribute('href'));

        if ($href === '') {
            return;
        }

        if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return;
        }

        $giaoThuc = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if (! in_array($giaoThuc, self::GIAO_THUC_CHO_PHEP, true)) {
            $el->removeAttribute('href');

            return;
        }

        if (in_array($giaoThuc, ['http', 'https'], true)) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener nofollow');
        }
    }
}
