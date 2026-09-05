<?php

namespace App\Services\Media;

/**
 * Làm sạch HTML của bài Cẩm nang.
 * ============================================================
 * BÀI VIẾT LÀ CHỖ DUY NHẤT TRONG DỰ ÁN IN HTML THÔ RA TRANG.
 *
 * Mọi nơi khác đều `{{ }}` (escape). Nhưng một bài hướng dẫn cần đoạn
 * văn, tiêu đề phụ, danh sách, chữ đậm — escape hết thì admin nhìn thấy
 * `<p>` hiện ra thành chữ.
 *
 * ============================================================
 * LÀM SẠCH LÚC LƯU, KHÔNG PHẢI LÚC HIỆN.
 *
 * Nếu để lúc hiện thì mọi chỗ in bài ra phải nhớ gọi hàm này — trang
 * bài, trang xem trước ở quản trị, thẻ mô tả, bản RSS về sau. Chỗ thứ ba
 * sẽ quên, và chỗ đó là lỗ hổng.
 *
 * Làm sạch một lần lúc ghi thì thứ nằm trong cơ sở dữ liệu ĐÃ sạch, và
 * không có đường nào lấy ra bản chưa sạch.
 *
 * Đánh đổi đã chấp nhận: nếu danh sách thẻ cho phép nới rộng về sau,
 * những bài cũ vẫn thiếu các thẻ đã bị gỡ. Chấp nhận được — số bài nhỏ
 * và admin sửa lại được. Chiều ngược lại (siết danh sách lại) thì bài cũ
 * vẫn sạch theo luật cũ, nên phải chạy lại một lượt; ghi rõ ở đây để lần
 * sau không quên.
 *
 * ============================================================
 * DANH SÁCH CHO PHÉP, KHÔNG PHẢI DANH SÁCH CẤM.
 *
 * Danh sách cấm luôn thiếu: cấm `<script>` thì còn `<iframe>`, cấm cả
 * hai thì còn `<object>`, `<embed>`, `<svg onload=>`, `<math>`… Không
 * bao giờ liệt kê hết được.
 *
 * Danh sách CHO PHÉP thì thứ chưa nghĩ tới mặc định bị gỡ — sai về phía
 * an toàn.
 */
class HtmlSanitizer
{
    /**
     * Thẻ được giữ lại.
     *
     * Không có `<img>`: ảnh trong bài đi qua ô tải ảnh riêng để còn được
     * TƯỚC METADATA (xem ImageMetadataStripper). Cho dán thẻ img tự do
     * thì admin dán link ảnh ngoài — vừa hotlink (điều dự án cấm), vừa
     * bỏ qua bước tước metadata.
     *
     * Không có `<iframe>`: nhúng video là một tính năng riêng cần bàn
     * riêng, không phải thứ lọt vào qua ô soạn thảo.
     *
     * @var list<string>
     */
    private const THE_CHO_PHEP = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'h2', 'h3', 'h4',
        'ul', 'ol', 'li',
        'blockquote', 'figure', 'figcaption',
        'a', 'code', 'pre',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'hr',
    ];

    /**
     * Thuộc tính được giữ, theo từng thẻ.
     *
     * Cố ý RẤT ÍT. Mọi thuộc tính `on*` (onclick, onerror, onload…) đều
     * là đường chạy JavaScript, và `style` cho phép chèn `background:
     * url(javascript:…)` ở vài trình duyệt cũ.
     *
     * @var array<string, list<string>>
     */
    private const THUOC_TINH_CHO_PHEP = [
        'a' => ['href', 'title'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
    ];

    /**
     * Giao thức cho phép trong `href`.
     *
     * `javascript:` là đường chạy mã. `data:` cho phép nhúng cả một
     * trang HTML vào link. Cả hai bị gỡ.
     *
     * @var list<string>
     */
    private const GIAO_THUC_CHO_PHEP = ['http', 'https', 'mailto', 'tel'];

    public function lamSach(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');

        /*
         * Bọc trong `<div>` và khai charset UTF-8 bằng meta.
         *
         * `loadHTML` mặc định đoán bảng mã là ISO-8859-1, nên tiếng Việt
         * có dấu biến thành ký tự lạ. Đây là cái bẫy quen thuộc của
         * DOMDocument và nó hỏng lặng lẽ — chữ vẫn hiện, chỉ sai dấu.
         */
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

    /**
     * Duyệt cây DOM, gỡ thẻ và thuộc tính không cho phép.
     *
     * DUYỆT NGƯỢC danh sách con: gỡ một nút làm `childNodes` co lại ngay
     * lập tức (nó là danh sách SỐNG), nên duyệt xuôi sẽ nhảy cóc qua nút
     * kế tiếp mỗi lần gỡ — và nút bị nhảy qua chính là nút chưa kiểm.
     *
     * ============================================================
     * ĐI XUỐNG TRƯỚC, GỠ VỎ SAU — và đây là một lỗi thật đã sửa.
     *
     * Bản đầu quyết định giữ hay gỡ TRƯỚC rồi mới đệ quy. Với
     * `<div><section><p>…</p></section></div>` thì:
     *
     *   - `<div>` bị gỡ vỏ, `<section>` được đẩy lên thay chỗ nó;
     *   - `<section>` nằm ở chỉ số mà vòng lặp VỪA ĐI QUA;
     *   - vòng lặp kết thúc, `<section>` không bao giờ được kiểm.
     *
     * Kết quả: một thẻ không nằm trong danh sách cho phép vẫn lọt ra
     * trang, chỉ cần bọc nó trong một thẻ cũng không được phép. Bài kiểm
     * thử `the_khong_cho_phep_bi_go_nhung_GIU_LAI_chu_ben_trong` bắt
     * được.
     *
     * Làm sạch cây con TRƯỚC thì lúc được đẩy lên, chúng đã sạch rồi —
     * không phụ thuộc vào việc vòng lặp có quay lại hay không.
     */
    private function duyet(\DOMNode $nut): void
    {
        for ($i = $nut->childNodes->length - 1; $i >= 0; $i--) {
            $con = $nut->childNodes->item($i);

            if (! $con instanceof \DOMElement) {
                continue;
            }

            $ten = strtolower($con->nodeName);

            /*
             * `<script>` và `<style>` xử lý TRƯỚC khi đi xuống: nội dung
             * của chúng chính là mã, không phải thứ cần làm sạch rồi giữ
             * lại.
             */
            if (in_array($ten, ['script', 'style'], true)) {
                $nut->removeChild($con);

                continue;
            }

            // Làm sạch cây con trước — xem chú thích trên.
            $this->duyet($con);

            if (! in_array($ten, self::THE_CHO_PHEP, true)) {
                /*
                 * GIỮ LẠI PHẦN CHỮ BÊN TRONG thẻ bị gỡ.
                 *
                 * Xoá cả cụm thì một thẻ `<div>` bọc ngoài — thứ mọi
                 * trình soạn thảo đều sinh ra — làm bay sạch bài viết.
                 * Thay thẻ bằng chính nội dung của nó thì chữ còn nguyên,
                 * chỉ mất cái vỏ.
                 */
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

        // Duyệt ngược, cùng lý do như trên: `attributes` cũng là danh
        // sách sống.
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

        // Link nội bộ (`/san-pham/...`, `#muc-2`) không có giao thức.
        if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return;
        }

        $giaoThuc = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if (! in_array($giaoThuc, self::GIAO_THUC_CHO_PHEP, true)) {
            $el->removeAttribute('href');

            return;
        }

        /*
         * Link ra ngoài mở tab mới VÀ có `rel="noopener"`.
         *
         * Thiếu `noopener` thì trang đích đọc được `window.opener` và đổi
         * được địa chỉ tab gốc sang một trang giả — kiểu tấn công
         * "tabnabbing". Trình duyệt mới đã mặc định an toàn, nhưng không
         * phải mọi trình duyệt khách đang dùng đều mới.
         */
        if (in_array($giaoThuc, ['http', 'https'], true)) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener nofollow');
        }
    }
}
