<?php

namespace App\Services\Tax;

use App\Services\Checkout\CheckoutBasket;
use App\Services\Checkout\CheckoutLine;

/**
 * Phần thuế GTGT nằm trong một lần thanh toán, TÁCH THEO TỪNG DÒNG.
 * ============================================================
 * VÌ SAO KHÔNG TÍNH THẲNG TRÊN TỔNG ĐƠN NỮA.
 *
 * Bản trước làm đúng một phép: `extract(grand_total, mức_cửa_hàng)`.
 * Nó đúng khi cả cửa hàng chỉ có một mức thuế. Từ khi mỗi sản phẩm có
 * thể thuộc một nhóm thuế riêng, phép đó sai ngay ở đơn hỗn hợp:
 *
 *     Bó hoa 300.000₫   (không chịu VAT)
 *     Chậu sứ 200.000₫  (VAT 10%)
 *     ------------------------------------
 *     Tính trên tổng 8%:  500.000 -> 37.037₫ thuế   ✗ sai cả hai vế
 *     Tính theo dòng:       0 + 18.182         = 18.182₫  ✓
 *
 * Con số sai không làm gãy trang nào — nó chỉ đi thẳng vào sổ kế toán.
 *
 * ============================================================
 * BỐN BƯỚC, ĐÚNG THỨ TỰ:
 *
 *     giá niêm yết (đã gồm VAT)
 *            ↓  trừ khuyến mại        -> đã nằm trong lineTotal()
 *            ↓  trừ mã giảm giá       -> PHÂN BỔ theo tỉ lệ, xem dưới
 *            ↓  tách VAT theo mức của chính dòng đó
 *            ↓  cộng thuế của phí vận chuyển
 *          tổng thuế của đơn
 *
 * Thứ tự này quan trọng: mã giảm giá làm GIẢM số tiền chịu thuế. Tách
 * thuế trước rồi mới trừ mã là ghi nhiều thuế hơn số thật sự thu được.
 */
final readonly class BasketTax
{
    /**
     * @param  list<array{line: CheckoutLine, discount: string, taxable: string, rate: ?string, tax: ?string}>  $lines
     */
    private function __construct(
        public array $lines,
        public bool $enabled,
        /** Phí vận chuyển thực thu, đã gồm VAT. */
        public string $shippingGross,
        public ?string $shippingRate,
        public ?string $shippingTax,
    ) {
    }

    public static function for(CheckoutBasket $basket): self
    {
        $may = app(TaxCalculator::class);

        if (! $may->enabled()) {
            /*
             * TẮT THUẾ = KHÔNG CÓ SỐ LIỆU, không phải thuế bằng 0.
             *
             * Mọi con số trả về null từ đây, và OrderService ghi NULL vào
             * cơ sở dữ liệu. Ghi 0 sẽ khiến báo cáo đọc ra "cửa hàng bán
             * hàng miễn thuế", một câu hoàn toàn khác.
             */
            return new self([], false, '0.00', null, null);
        }

        $phanBo = self::phanBoMaGiamGia($basket);

        $lines = [];

        foreach ($basket->lines->values() as $i => $line) {
            $giam = $phanBo[$i] ?? '0.00';
            $chiuThue = bcsub($line->lineTotal(), $giam, 2);
            $muc = $may->rateFor($line->product);

            $lines[] = [
                'line' => $line,
                'discount' => $giam,
                'taxable' => $chiuThue,
                'rate' => $muc,
                // Hàng không chịu VAT: KHÔNG có số thuế, chứ không phải 0₫.
                'tax' => $muc === null ? null : $may->extract($chiuThue, $muc),
            ];
        }

        /*
         * PHÍ VẬN CHUYỂN CHỊU MỨC MẶC ĐỊNH CỦA CỬA HÀNG.
         *
         * Nó là một DỊCH VỤ do cửa hàng cung cấp, không phải hàng hoá,
         * nên nó không mượn mức của bất kỳ sản phẩm nào trong giỏ. Lấy
         * mức của sản phẩm đầu giỏ thì cùng một quãng đường sẽ mang thuế
         * suất khác nhau tuỳ khách mua gì — không có nghiệp vụ nào biện
         * minh được cho điều đó.
         */
        $phi = $basket->shippingFee();
        $mucPhi = $may->rate();

        return new self(
            $lines,
            true,
            $phi,
            $mucPhi,
            $may->extract($phi, $mucPhi),
        );
    }

    /**
     * Chia tiền mã giảm giá cho từng dòng theo tỉ lệ thành tiền.
     *
     * ============================================================
     * VÌ SAO PHẢI CHIA, KHÔNG TRỪ THẲNG VÀO TỔNG:
     *
     * Mã giảm giá áp cho cả đơn, còn thuế thì tính theo từng dòng với
     * mức riêng. Muốn biết dòng nào còn chịu thuế trên bao nhiêu tiền,
     * bắt buộc phải biết dòng đó gánh bao nhiêu phần của mã.
     *
     * ============================================================
     * DÒNG CUỐI GÁNH PHẦN LẺ — có chủ ý.
     *
     * Chia 100.000₫ cho ba dòng bằng nhau: mỗi dòng 33.333,33₫, cộng lại
     * còn thiếu 0,01₫. Làm tròn từng dòng rồi cộng thì tổng KHÔNG bằng
     * số tiền đã giảm, và khi ấy:
     *
     *     SUM(order_items.discount_amount)  !=  orders.coupon_discount
     *
     * Kế toán đối chiếu ra ngay, và không ai giải thích được phần chênh.
     * Cho dòng cuối nhận đúng phần còn lại thì đẳng thức luôn khít, và
     * sai lệch tối đa là một xu trên MỘT dòng chứ không phải trên tổng.
     *
     * @return list<string>  cùng thứ tự với $basket->lines
     */
    private static function phanBoMaGiamGia(CheckoutBasket $basket): array
    {
        $tong = $basket->itemsTotal();

        /*
         * MÃ GIẢM GIÁ + ĐIỂM THƯỞNG — cả hai áp cho cả đơn, nên cùng phân
         * bổ. Điểm cũng làm giảm số tiền thật sự thu, tức giảm tiền chịu
         * thuế. Đẳng thức đối soát nay là:
         *     SUM(items.discount_amount) = coupon_discount + points_discount
         */
        $giamGia = $basket->orderDiscountTotal();
        $soDong = $basket->lines->count();

        if ($soDong === 0) {
            return [];
        }

        // Không có mã, hoặc giỏ 0₫ (chỉ xảy ra với dữ liệu hỏng): không
        // chia gì cả. Chia cho 0 ở dưới sẽ nổ.
        if (bccomp($giamGia, '0', 2) <= 0 || bccomp($tong, '0', 2) <= 0) {
            return array_fill(0, $soDong, '0.00');
        }

        $ket = [];
        $daChia = '0.00';

        foreach ($basket->lines->values() as $i => $line) {
            if ($i === $soDong - 1) {
                $ket[] = bcsub($giamGia, $daChia, 2);

                break;
            }

            $phan = bcdiv(bcmul($giamGia, $line->lineTotal(), 6), $tong, 2);
            $ket[] = $phan;
            $daChia = bcadd($daChia, $phan, 2);
        }

        return $ket;
    }

    /** Tổng thuế của các dòng hàng; null khi tính thuế đang tắt. */
    public function itemsTax(): ?string
    {
        if (! $this->enabled) {
            return null;
        }

        $tong = '0.00';

        foreach ($this->lines as $dong) {
            // Dòng không chịu thuế đóng góp 0 vào TỔNG, nhưng bản thân
            // nó vẫn được ghi là null ở cột của riêng nó.
            $tong = bcadd($tong, $dong['tax'] ?? '0.00', 2);
        }

        return $tong;
    }

    /** Tổng thuế của cả đơn, gồm cả phần của phí vận chuyển. */
    public function total(): ?string
    {
        if (! $this->enabled) {
            return null;
        }

        return bcadd($this->itemsTax() ?? '0.00', $this->shippingTax ?? '0.00', 2);
    }

    /** Có ít nhất một đồng thuế để hiển thị không. */
    public function hasTax(): bool
    {
        return $this->enabled && bccomp($this->total() ?? '0', '0', 2) > 0;
    }

    /**
     * Tách theo từng MỨC THUẾ SUẤT — dạng hoá đơn GTGT phải ghi.
     *
     * Hoá đơn không ghi "7.000.000₫ thuế hỗn hợp"; nó ghi riêng phần
     * chịu 8% và phần chịu 10%. Nhóm ở đây một lần để cả màn hình thanh
     * toán, trang đơn hàng và dữ liệu hoá đơn dùng chung một bảng.
     *
     * Khoá nhóm là chuỗi mức thuế, `''` cho phần không chịu thuế — mảng
     * PHP không nhận khoá null.
     *
     * @return list<array{rate: ?string, net: string, tax: string}>
     */
    public function byRate(): array
    {
        if (! $this->enabled) {
            return [];
        }

        $nhom = [];

        foreach ($this->lines as $dong) {
            $this->gop($nhom, $dong['rate'], $dong['taxable'], $dong['tax']);
        }

        /*
         * PHÍ VẬN CHUYỂN GỘP VÀO ĐÚNG MỨC CỦA NÓ, không thành một dòng
         * riêng: hoá đơn tách theo thuế suất, không tách theo "hàng" và
         * "phí". Nếu phí chịu 8% và trong giỏ đã có hàng 8% thì hai phần
         * đứng chung một dòng — đúng như hoá đơn thật.
         */
        if (bccomp($this->shippingGross, '0', 2) > 0) {
            $this->gop($nhom, $this->shippingRate, $this->shippingGross, $this->shippingTax);
        }

        $rows = array_values($nhom);

        /*
         * Sắp theo mức thuế giảm dần, phần KHÔNG chịu thuế xuống cuối.
         *
         * Thứ tự cố định để bảng không nhảy loạn mỗi lần khách đổi giỏ —
         * mảng PHP giữ thứ tự chèn, mà thứ tự chèn là thứ tự sản phẩm
         * trong giỏ, tức là ngẫu nhiên với người đọc.
         */
        usort($rows, function (array $a, array $b): int {
            if ($a['rate'] === null) {
                return $b['rate'] === null ? 0 : 1;
            }

            if ($b['rate'] === null) {
                return -1;
            }

            return bccomp($b['rate'], $a['rate'], 6);
        });

        return $rows;
    }

    /**
     * @param  array<string, array{rate: ?string, net: string, tax: string}>  $nhom
     */
    private function gop(array &$nhom, ?string $rate, string $gross, ?string $tax): void
    {
        $khoa = $rate ?? '';

        $nhom[$khoa] ??= ['rate' => $rate, 'net' => '0.00', 'tax' => '0.00'];

        $nhom[$khoa]['tax'] = bcadd($nhom[$khoa]['tax'], $tax ?? '0.00', 2);
        $nhom[$khoa]['net'] = bcadd($nhom[$khoa]['net'], bcsub($gross, $tax ?? '0.00', 2), 2);
    }

    /** Tổng tiền hàng + phí CHƯA thuế — con số hoá đơn phải ghi. */
    public function netTotal(?string $grandTotal = null): ?string
    {
        if (! $this->enabled || $grandTotal === null) {
            return null;
        }

        return bcsub($grandTotal, $this->total() ?? '0.00', 2);
    }
}
