<?php

namespace App\Services\Checkout;

use App\Models\Coupon;
use App\Services\Coupon\CouponService;
use App\Services\Shipping\ShippingQuote;
use App\Services\Shipping\ShippingRates;
use Illuminate\Support\Collection;

/**
 * Toàn bộ số tiền của một lần thanh toán.
 * ============================================================
 * NƠI DUY NHẤT tính tiền cho việc thanh toán.
 *
 * Trước đây phí giao hàng và tổng tiền được tính ở HAI chỗ: trong
 * component Blade x-cart.summary và trong OrderService. Hai chỗ dùng
 * cùng một quy tắc nhưng không có gì bảo đảm chúng còn khớp sau này —
 * đúng loại trùng lặp mà quy tắc "pricing phải có một nơi chịu trách
 * nhiệm chính" cấm.
 *
 * Nay Blade và OrderService cùng đọc từ đây.
 */
final readonly class CheckoutBasket
{
    /** @param Collection<int, CheckoutLine> $lines */
    public function __construct(
        public Collection $lines,
        /** Nguồn hàng: 'cart' hoặc 'direct' (mua ngay). */
        public string $source = 'cart',
        /** Mã giảm giá khách đã áp, nếu có. */
        public ?Coupon $coupon = null,
        /**
         * Tỉnh/thành nhận hàng — quyết định phí giao.
         *
         * null khi khách chưa nhập địa chỉ (đang ở trang giỏ hàng). Lúc
         * đó phí được tính theo vùng mặc định và giao diện phải nói rõ là
         * TẠM TÍNH; xem x-cart.summary.
         */
        public ?string $province = null,

        /**
         * Mã quận/huyện và phường/xã của GHN cho nơi nhận hàng.
         *
         * VÌ SAO CẦN CẢ HAI, KHI ĐÃ CÓ TÊN TỈNH: GHN tính cước theo
         * QUẬN/HUYỆN và PHƯỜNG/XÃ, không theo tỉnh. Giao trong nội thành
         * Hà Nội và giao lên một xã miền núi cùng tỉnh chênh nhau rất
         * nhiều, và bảng phí phẳng theo tỉnh không diễn tả được điều đó.
         *
         * null khi khách chưa chọn xong địa chỉ, hoặc khi đơn được đặt
         * trước lúc bật tính cước GHN. Khi đó lùi về bảng phí theo tỉnh —
         * xem ShippingQuote.
         */
        public ?int $toDistrictId = null,
        public ?string $toWardCode = null,
    ) {
    }

    /** Bản sao có gắn thêm mã giảm giá. */
    public function withCoupon(?Coupon $coupon): self
    {
        return new self(
            $this->lines, $this->source, $coupon,
            $this->province, $this->toDistrictId, $this->toWardCode,
        );
    }

    /** Bản sao có gắn nơi nhận hàng. */
    public function withProvince(?string $province): self
    {
        return new self(
            $this->lines, $this->source, $this->coupon,
            $province, $this->toDistrictId, $this->toWardCode,
        );
    }

    /** Bản sao có gắn mã địa giới GHN của nơi nhận. */
    public function withGhnDestination(?int $districtId, ?string $wardCode): self
    {
        return new self(
            $this->lines, $this->source, $this->coupon,
            $this->province, $districtId, $wardCode,
        );
    }

    /** Đã biết nơi giao chưa — giao diện dùng để phân biệt phí thật/tạm tính. */
    public function hasDestination(): bool
    {
        return $this->province !== null && trim($this->province) !== '';
    }

    public function isEmpty(): bool
    {
        return $this->lines->isEmpty();
    }

    public function totalQuantity(): int
    {
        return (int) $this->lines->sum(fn (CheckoutLine $l) => $l->quantity);
    }

    /** Tổng tiền hàng sau khuyến mại, chưa gồm phí giao. */
    public function itemsTotal(): string
    {
        return $this->lines->reduce(
            fn (string $carry, CheckoutLine $l) => bcadd($carry, $l->lineTotal(), 2),
            '0',
        );
    }

    /** Tổng tiền hàng theo giá gốc, dùng để hiện phần đã giảm. */
    public function baseTotal(): string
    {
        return $this->lines->reduce(
            fn (string $carry, CheckoutLine $l) => bcadd(
                $carry,
                bcmul($l->unitBasePrice(), (string) $l->quantity, 2),
                2,
            ),
            '0',
        );
    }

    public function discountTotal(): string
    {
        return bcsub($this->baseTotal(), $this->itemsTotal(), 2);
    }

    /**
     * Số tiền mã giảm giá trừ đi.
     *
     * Tính lại mỗi lần từ CouponService — không lưu con số, và tuyệt
     * đối không nhận con số nào từ trình duyệt.
     */
    public function couponDiscount(): string
    {
        if (! $this->coupon) {
            return '0.00';
        }

        return app(CouponService::class)->discountFor($this->coupon, $this->itemsTotal());
    }

    /** Tiền hàng sau khi trừ mã giảm giá. */
    public function payableItemsTotal(): string
    {
        return bcsub($this->itemsTotal(), $this->couponDiscount(), 2);
    }

    /**
     * Ngưỡng miễn phí giao xét trên tiền hàng TRƯỚC khi trừ mã giảm giá.
     *
     * Chọn vậy để dùng mã không đẩy khách xuống dưới ngưỡng rồi bị tính
     * thêm phí ship — vừa giảm được ít tiền vừa mất phí giao thì khách
     * thấy như bị phạt vì dùng mã.
     */
    public function isFreeShipping(): bool
    {
        return bccomp($this->itemsTotal(), $this->freeShippingThreshold(), 2) >= 0;
    }

    /**
     * Phí giao hàng.
     *
     * Mức phí do App\Services\Shipping\ShippingRates tra theo tỉnh nhận
     * hàng. Chưa biết tỉnh thì trả về mức của vùng mặc định — một con số
     * để hiển thị tạm ở trang giỏ hàng, và giao diện phải ghi rõ là tạm
     * tính (xem hasDestination()).
     */
    public function shippingFee(): string
    {
        if ($this->isFreeShipping()) {
            return '0.00';
        }

        return $this->baseShippingFee();
    }

    /**
     * Phí giao TRƯỚC khi miễn — luôn là con số của vùng, kể cả khi được
     * miễn phí.
     *
     * VÌ SAO CẦN, KHI ĐÃ CÓ shippingFee():
     * Tóm tắt đơn phải cho khách ĐỐI SOÁT được từng dòng. Chỉ in "Miễn
     * phí" thì họ không biết mình vừa được miễn bao nhiêu, và cũng không
     * cộng tay lại được. Hiện đủ hai dòng:
     *
     *     Phí giao hàng          30.000đ
     *     Miễn phí giao hàng    −30.000đ
     *
     * là cách mọi trang thương mại điện tử thật vẫn làm, và là cách duy
     * nhất để các dòng cộng lại đúng bằng dòng tổng.
     */
    public function baseShippingFee(): string
    {
        /*
         * CHƯA CÓ ĐỊA CHỈ THÌ PHÍ GIAO LÀ 0₫, KHÔNG PHẢI MỘT MỨC ĐOÁN.
         *
         * Bản trước hiện mức của vùng mặc định ("Tỉnh xa", 50.000₫) kèm
         * chú thích "tạm tính". Vấn đề: đó là con số CAO NHẤT trong
         * bảng, nên trang giỏ hàng luôn dựng ra mức xấu nhất cho một địa
         * chỉ chưa ai nhập.
         *
         * Khách ở nội thành Hà Nội nhìn thấy "Phí giao hàng 50.000₫"
         * ngay khi vừa bỏ hàng vào giỏ, trong khi cước thật của họ là
         * 42.900₫ — và một số người bỏ giỏ ngay tại đó, trước cả khi có
         * cơ hội nhập địa chỉ để thấy con số thật.
         *
         * Chữ "tạm tính" không cứu được: người ta đọc CON SỐ trước, và
         * phần lớn không đọc chú thích bên cạnh.
         *
         * 0₫ không phải là nói dối "được miễn phí": dòng ghi chú ngay
         * cạnh nói rõ "nhập địa chỉ để tính phí giao". Chưa biết giao
         * tới đâu thì thành thật nhất là chưa đưa ra con số nào.
         */
        if (! $this->hasDestination()) {
            return '0.00';
        }

        /*
         * CƯỚC THẬT TỪ GHN KHI ĐÃ BIẾT ĐỦ ĐỊA CHỈ.
         *
         * ShippingQuote tự lùi về bảng phí theo tỉnh khi chưa có mã địa
         * giới, hoặc khi GHN không trả lời được. Nhờ vậy chỗ này không
         * phải rẽ nhánh, và cũng chỉ có MỘT nơi quyết định lùi về đâu.
         *
         * Con số này luôn tính lại Ở MÁY CHỦ, không bao giờ nhận từ
         * trình duyệt — xem chú thích đầu ShippingQuote.
         */
        return app(ShippingQuote::class)->feeFor($this, $this->toDistrictId, $this->toWardCode);
    }

    /**
     * Số tiền phí giao được miễn. 0 khi không được miễn.
     *
     * Trả về SỐ DƯƠNG; giao diện tự thêm dấu trừ. Trả về số âm ở đây thì
     * mọi nơi cộng dồn phải nhớ đảo dấu, và sẽ có nơi quên.
     */
    public function shippingDiscount(): string
    {
        /*
         * Chưa có địa chỉ thì KHÔNG hiện dòng "Miễn phí giao hàng".
         *
         * Phí đang là 0₫ vì chưa biết giao tới đâu, không phải vì được
         * miễn. Hiện dòng miễn phí lúc này là hứa một thứ chưa chắc có —
         * và khi khách nhập địa chỉ xong, dòng đó biến mất còn tổng tiền
         * thì tăng lên.
         */
        if (! $this->hasDestination()) {
            return '0.00';
        }

        return $this->isFreeShipping() ? $this->baseShippingFee() : '0.00';
    }

    /**
     * Nhãn giải thích VÌ SAO phí là con số này.
     *
     * PHẢI NÓI ĐÚNG NGUỒN CỦA CON SỐ ĐANG HIỆN CẠNH NÓ.
     *
     * LỖI ĐO ĐƯỢC TRƯỚC KHI SỬA: nhãn luôn lấy từ bảng vùng, kể cả khi
     * phí đến từ GHN. Đặt hàng về Bắc Từ Liêm — cách cửa hàng 2km — thì
     * màn hình hiện "Phí giao hàng (Tỉnh xa) 42.900₫".
     *
     * "Tỉnh xa" ở đây vô nghĩa gấp đôi: địa chỉ không hề xa, và bảng
     * vùng KHÔNG phải nơi ra con số 42.900₫. Nó rơi vào vùng mặc định
     * chỉ vì GHN gọi tỉnh đó là "Hà Nội" còn bảng vùng ghi "Thành phố
     * Hà Nội" — hai danh mục lệch nhau sau đợt sáp nhập 2025.
     *
     * Một lời giải thích sai còn tệ hơn không giải thích: khách đọc
     * "Tỉnh xa" rồi tưởng cửa hàng tính nhầm, hoặc tưởng mình gõ nhầm
     * địa chỉ.
     */
    public function shippingZoneLabel(): string
    {
        if ($this->hasGhnDestination()) {
            return 'cước Giao Hàng Nhanh';
        }

        return app(ShippingRates::class)->zoneLabel($this->province);
    }

    /**
     * Đã có đủ mã địa giới để hỏi cước GHN chưa.
     *
     * Chỉ nói "đã có mã", KHÔNG nói "GHN đã trả lời được". GHN vẫn có
     * thể im lặng và phí lùi về bảng vùng — nhưng lúc đó nhãn cũng
     * không còn quan trọng bằng việc khách đặt được hàng.
     */
    public function hasGhnDestination(): bool
    {
        return $this->toDistrictId !== null && $this->toWardCode !== null;
    }

    /** Còn thiếu bao nhiêu nữa thì được miễn phí giao. */
    public function amountToFreeShipping(): string
    {
        return bcsub($this->freeShippingThreshold(), $this->itemsTotal(), 2);
    }

    public function grandTotal(): string
    {
        return bcadd($this->payableItemsTotal(), $this->shippingFee(), 2);
    }

    /**
     * Ngưỡng miễn phí giao, công khai cho giao diện.
     *
     * Tóm tắt đơn phải giải thích được VÌ SAO dòng "Miễn phí giao hàng"
     * xuất hiện — "đơn từ 500.000đ" — chứ không chỉ trừ tiền rồi thôi.
     * Con số vẫn do ShippingRates quyết định, đây chỉ là cửa đọc.
     */
    public function freeShippingFrom(): string
    {
        return $this->freeShippingThreshold();
    }

    private function freeShippingThreshold(): string
    {
        return app(ShippingRates::class)->freeFrom();
    }

    /** config trả về float; bcmath cần chuỗi thập phân chuẩn. */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
