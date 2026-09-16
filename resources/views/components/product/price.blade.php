@props(['product', 'size' => 'md'])

@php
    $price = $product->price();

    $finalClass = $size === 'lg' ? 'text-price' : 'text-price-sm';
@endphp

<span class="product-price product-price--{{ $size }}">

    @if($price->isContactForPrice())

        <span class="{{ $size === 'lg' ? 'text-h4' : 'text-body-sm' }} text-muted">
            Liên hệ báo giá
        </span>

    @elseif($price->isDiscounted())

        <span class="text-price-compare">
            <x-site.money :amount="$price->basePrice" />
        </span>

        <span class="{{ $finalClass }} text-accent">
            <x-site.money :amount="$price->finalPrice" />
        </span>

        <span class="price-badge">−{{ $price->discountPercent() }}%</span>

    @else

        <span class="{{ $finalClass }}">
            <x-site.money :amount="$price->finalPrice" />
        </span>

    @endif

</span>
