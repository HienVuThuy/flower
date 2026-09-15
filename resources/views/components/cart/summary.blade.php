@props([
    'basket',
    'showAction' => false,
    // Liệt kê từng món. Bật ở bước thanh toán, tắt ở trang giỏ hàng —
    // xem ghi chú ở khối danh sách bên dưới.
    'itemized' => false,
    // Mã đang áp do hệ thống tự chọn hay khách tự chọn.
    'couponIsAuto' => false,
])

{{--
    TÓM TẮT TIỀN — các dòng phải CỘNG ĐÚNG ra dòng tổng.

    Lỗi cũ: "Tạm tính" hiển thị itemsTotal() (giá ĐÃ giảm) rồi bên dưới
    lại trừ tiếp "Đã giảm". Với 1 bó hoa 650.000 giảm còn 250.000, bảng
    hiện: 250.000 − 400.000 + 30.000 = −120.000, trong khi tổng lại là
    280.000. Khách đọc không hiểu vì sao.

    Nay "Tạm tính" là baseTotal() (giá gốc), nên:
        650.000 − 400.000 + 30.000 = 280.000  ✓

    Mọi con số vẫn do CheckoutBasket tính — cùng nơi OrderService dùng
    khi ghi đơn, nên số khách thấy và số ghi vào đơn không thể lệch.
--}}

@php
    $hasDiscount = bccomp($basket->discountTotal(), '0', 2) > 0;
    $shippingDiscount = $basket->shippingDiscount();
    $hasShippingDiscount = bccomp($shippingDiscount, '0', 2) > 0;

    /*
     * $money TRẢ VỀ CẢ KÝ HIỆU TIỀN.
     *
     * Bản trước chỉ trả về phần số, và mỗi nơi gọi tự nối `&#8363;` phía
     * sau — tức là ký hiệu tiền vẫn viết cứng ở mười mấy chỗ trong đúng
     * tệp này. Đổi đơn vị tiền tệ ở trang Cấu hình thì phần số đổi còn
     * ký hiệu thì không.
     */
    $money = fn (string $v) => \App\Services\Shop\Money::format($v);

    /*
     * THUẾ ĐỌC TỪ CHÍNH GIỎ NÀY, không tự tính ở đây.
     *
     * CheckoutBasket là nơi duy nhất chịu trách nhiệm về tiền; Blade chỉ
     * hỏi. Tính lại trong template là dựng bản thứ hai của luật thuế
     * ngay tại chỗ khó kiểm thử nhất.
     */
    $thue = $basket->tax();
@endphp

<div class="order-summary">

    <h2 class="text-h4 order-summary__title">Tóm tắt đơn hàng</h2>

    @if($itemized && $basket->lines->isNotEmpty())
        {{--
            DANH SÁCH TỪNG MÓN.

            Chỉ bật ở bước thanh toán. Trang giỏ hàng đã liệt kê hàng ngay
            bên trái nên lặp lại ở đây là thừa; còn ở bước thanh toán thì
            không còn danh sách nào khác trên màn hình, và khách phải xem
            lại được mình đang mua gì trước khi trả tiền.

            Mỗi dòng ghi đủ: tên × số lượng — đơn giá = thành tiền. Chỉ
            ghi thành tiền thì khách không kiểm tra được đơn giá có đúng
            với giá họ đã thấy trên trang sản phẩm hay không.
        --}}
        <ul class="order-summary__items">
            @foreach($basket->lines as $line)
                <li class="order-summary__item">
                    <div class="order-summary__item-main">
                        <span class="order-summary__item-name">
                            {{ $line->product->name }}
                            @if($line->variant)
                                <span class="order-summary__item-variant">{{ $line->variant->name }}</span>
                            @endif
                        </span>

                        <span class="order-summary__item-calc">
                            {{ $line->quantity }} &times; {{ $money($line->unitPrice()) }}
                        </span>
                    </div>

                    <span class="order-summary__item-total">{{ $money($line->lineTotal()) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <dl class="order-summary__lines">

        <div class="order-summary__row">
            <dt>
                Tạm tính
                {{--
                    "đã chọn" chứ không phải "trong giỏ".

                    Từ khi chọn được từng món, con số này chỉ đếm hàng đã
                    tích. Ghi "3 sản phẩm" trong khi giỏ có 5 là làm khách
                    tưởng hệ thống đếm sai.
                --}}
                <span class="order-summary__count">({{ $basket->totalQuantity() }} sản phẩm đã chọn)</span>
            </dt>
            <dd>{{ $money($basket->baseTotal()) }}</dd>
        </div>

        @if($hasDiscount)
            <div class="order-summary__row order-summary__row--discount">
                <dt>Giảm giá khuyến mại</dt>
                <dd>&minus;{{ $money($basket->discountTotal()) }}</dd>
            </div>
        @endif

        @if($basket->coupon)
            <div class="order-summary__row order-summary__row--discount">
                <dt>
                    Mã {{ $basket->coupon->code }}
                    <span class="order-summary__count">
                        {{ $basket->coupon->name }}
                        @if($couponIsAuto)
                            {{--
                                NÓI RÕ MÃ NÀY DO HỆ THỐNG TỰ CHỌN.

                                Tự áp mã mà không nói là đổi số tiền sau
                                lưng khách. Họ phải biết mình đang được
                                giảm nhờ đâu, và biết là có thể đổi.
                            --}}
                            &middot; tự chọn giúp bạn
                        @endif
                    </span>
                </dt>
                <dd>&minus;{{ $money($basket->couponDiscount()) }}</dd>
            </div>
        @endif

        @if(bccomp($basket->memberDiscount(), '0', 2) > 0)
            <div class="order-summary__row order-summary__row--discount" data-uu-dai-hang="{{ $basket->memberDiscount() }}">
                <dt>
                    Ưu đãi hạng {{ $basket->memberTier->name }}
                    <span class="order-summary__count">{{ rtrim(rtrim((string) $basket->memberTier->discount_percent, '0'), '.') }}% tiền hàng</span>
                </dt>
                <dd>&minus;{{ $money($basket->memberDiscount()) }}</dd>
            </div>
        @elseif($basket->memberDiscountBlockedByCoupon())
            {{-- Nói vì sao ưu đãi hạng không có mặt, không để khách tưởng nó bị hỏng. --}}
            <div class="order-summary__row" data-uu-dai-hang-bi-chan>
                <dt>
                    <span class="order-summary__count">
                        Mã {{ $basket->coupon->code }} không cộng dồn với ưu đãi hạng {{ $basket->memberTier->name }}
                        — bỏ mã để được giảm theo hạng.
                    </span>
                </dt>
                <dd></dd>
            </div>
        @endif

        @if($basket->pointsUsed() > 0)
            <div class="order-summary__row order-summary__row--discount" data-diem-giam="{{ $basket->pointsDiscount() }}">
                <dt>
                    Điểm thưởng
                    <span class="order-summary__count">{{ number_format($basket->pointsUsed(), 0, ',', '.') }} điểm</span>
                </dt>
                <dd>&minus;{{ $money($basket->pointsDiscount()) }}</dd>
            </div>
        @endif

        <div class="order-summary__row">
            <dt>
                Phí giao hàng
                {{--
                    NÓI RÕ CON SỐ NÀY LÀ THẬT HAY TẠM TÍNH.

                    Phí nay phụ thuộc tỉnh nhận hàng, mà ở trang giỏ hàng
                    thì khách chưa nhập địa chỉ — con số hiện ra là mức
                    của vùng mặc định. Im lặng để đó rồi tới bước thanh
                    toán mới nhảy lên là kiểu "phí phát sinh phút chót"
                    khiến khách bỏ giỏ hàng.
                --}}
                {{--
                    data-ship-note / data-ship-fee / data-grand-total:
                    chỗ bám cho JavaScript cập nhật khi khách vừa chọn
                    xong địa chỉ GHN.

                    VÌ SAO PHẢI CẬP NHẬT: bảng này dựng ở MÁY CHỦ từ mã
                    địa giới trong phiên, mà lúc khách còn đang chọn thì
                    phiên chưa có gì — nên nó hiện mức tạm theo tỉnh.
                    Hộp cước ngay dưới ô địa chỉ thì đã có cước GHN thật.

                    Hai con số tiền khác nhau trên cùng một màn hình là
                    lỗi nặng hơn cả việc hiện sai một con số: khách không
                    biết tin cái nào, và mất tin vào cả trang.
                --}}
                <span class="order-summary__count" data-ship-note>
                    @if($basket->hasDestination())
                        {{ $basket->shippingZoneLabel() }}
                    @else
                        {{--
                            Nay phí là 0₫ khi chưa có địa chỉ, nên câu
                            "tạm tính" thành sai: 0₫ không phải một mức
                            tạm, nó là "chưa tính".

                            Câu này phải nói RÕ VIỆC KHÁCH CẦN LÀM, vì
                            nếu không thì 0₫ đọc như "được miễn phí".
                        --}}
                        nhập địa chỉ để tính phí giao
                    @endif
                </span>
            </dt>
            {{--
                LUÔN IN CON SỐ PHÍ GỐC, kể cả khi được miễn.

                Chỉ in "Miễn phí" thì khách không biết mình vừa được miễn
                bao nhiêu, và các dòng không cộng lại đúng bằng dòng tổng.
                Được miễn thì hiện thêm một dòng ÂM ngay bên dưới — đúng
                cách mọi trang thương mại điện tử thật vẫn làm.
            --}}
            <dd data-ship-fee>{{ $money($basket->baseShippingFee()) }}</dd>
        </div>

        @if($hasShippingDiscount)
            <div class="order-summary__row order-summary__row--discount">
                <dt>
                    Miễn phí giao hàng
                    <span class="order-summary__count">
                        @if($basket->freeShippingByTier())
                            ưu đãi hạng {{ $basket->memberTier->name }}
                        @else
                            đơn từ {{ $money($basket->freeShippingFrom()) }}
                        @endif
                    </span>
                </dt>
                <dd>&minus;{{ $money($shippingDiscount) }}</dd>
            </div>
        @endif

        <div class="order-summary__row order-summary__row--total">
            <dt>Tổng thanh toán</dt>
            {{--
                data-items-total giữ TIỀN HÀNG (đã trừ khuyến mại và mã
                giảm giá) để JavaScript cộng lại với cước mới.

                Cộng ở trình duyệt CHỈ ĐỂ HIỂN THỊ. Lúc ghi đơn, máy chủ
                tính lại toàn bộ từ đầu và không đọc con số nào từ biểu
                mẫu — xem ShippingQuote và CheckoutBasket::grandTotal().
            --}}
            <dd data-grand-total
                data-items-total="{{ $basket->payableItemsTotal() }}">{{ $money($basket->grandTotal()) }}</dd>
        </div>

        {{--
            THUẾ GTGT — nằm dưới dòng tổng vì nó NẰM TRONG dòng tổng.
            Toàn bộ cách trình bày ở x-order.tax-lines, dùng chung với
            trang đơn hàng của khách.
        --}}
        <x-order.tax-lines :total="$thue->total()" :rows="$thue->byRate()" />

    </dl>

    @if($hasDiscount || $hasShippingDiscount || bccomp($basket->orderDiscountTotal(), '0', 2) > 0)
        @php
            // Mã + điểm lấy chung từ orderDiscountTotal() — cùng con số BasketTax phân bổ.
            $saved = bcadd(
                bcadd($basket->discountTotal(), $shippingDiscount, 2),
                $basket->orderDiscountTotal(),
                2,
            );
        @endphp

        <p class="order-summary__saved">
            Bạn tiết kiệm được <strong>{{ $money($saved) }}</strong>
        </p>
    @endif

    @unless($basket->isFreeShipping())
        <p class="order-summary__hint">
            Mua thêm <strong>{{ $money($basket->amountToFreeShipping()) }}</strong>
            để được miễn phí giao hàng.
        </p>
    @endunless

    @if($showAction)
        <a href="{{ route('shop.checkout.details') }}" class="btn btn-primary-brand w-100">
            Tiến hành thanh toán
        </a>

        <a href="{{ route('shop.products.index') }}" class="btn btn-ghost w-100 mt-2">
            Tiếp tục mua sắm
        </a>
    @endif

</div>
