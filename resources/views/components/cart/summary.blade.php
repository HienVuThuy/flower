@props([
    'basket',
    'showAction' => false,
    'itemized' => false,
    'couponIsAuto' => false,
])

{{-- TÓM TẮT TIỀN — các dòng phải CỘNG ĐÚNG ra dòng tổng. --}}

@php
    $hasDiscount = bccomp($basket->discountTotal(), '0', 2) > 0;
    $shippingDiscount = $basket->shippingDiscount();
    $hasShippingDiscount = bccomp($shippingDiscount, '0', 2) > 0;

    $money = fn (string $v) => \App\Services\Shop\Money::format($v);

    $thue = $basket->tax();
@endphp

<div class="order-summary">

    <h2 class="text-h4 order-summary__title">Tóm tắt đơn hàng</h2>

    @if($itemized && $basket->lines->isNotEmpty())
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
                <span class="order-summary__count" data-ship-note>
                    @if($basket->hasDestination())
                        {{ $basket->shippingZoneLabel() }}
                    @else
                        nhập địa chỉ để tính phí giao
                    @endif
                </span>
            </dt>
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
            <dd data-grand-total
                data-items-total="{{ $basket->payableItemsTotal() }}">{{ $money($basket->grandTotal()) }}</dd>
        </div>

        <x-order.tax-lines :total="$thue->total()" :rows="$thue->byRate()" />

    </dl>

    @php
        $boQua = app(\App\Services\Gift\GiftResolver::class);
        $quaKem = $boQua->choGio($basket, auth()->user());
        $goiYQua = $boQua->goiYMuaThem($basket, auth()->user());
    @endphp
    @if($quaKem->isNotEmpty())
        <div class="order-summary__gifts" data-qua-kem>
            <p class="fw-bold mb-1">
                <x-site.icon name="flower1" /> Quà tặng kèm
            </p>
            <ul class="list-unstyled mb-0">
                @foreach($quaKem as $qua)
                    <li data-qua="{{ $qua['nguon'] }}-{{ $qua['campaign']?->id ?? $qua['product_gift']?->id }}">
                        {{ $qua['item']->name }} × {{ $qua['quantity'] }}
                        <span class="order-summary__count">
                            {{ $qua['campaign']?->name ?? 'Quà miễn phí kèm sản phẩm' }}@if($qua['item']->value !== null) · trị giá {{ $money((string) $qua['item']->value) }}@endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @foreach($goiYQua as $goiY)
        <p class="order-summary__hint" data-goi-y-qua="{{ $goiY['khuyen_mai']->id }}">
            <x-site.icon name="gift" />
            Mua thêm <strong>{{ $money($goiY['con_thieu']) }}</strong> để nhận
            {{ $goiY['khuyen_mai']->gift_quantity }} × {{ $goiY['khuyen_mai']->giftItem->name }} ({{ $goiY['khuyen_mai']->name }}).
        </p>
    @endforeach

    @if($hasDiscount || $hasShippingDiscount || bccomp($basket->orderDiscountTotal(), '0', 2) > 0)
        @php
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
