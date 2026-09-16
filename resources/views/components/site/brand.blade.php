@props(['size' => 28, 'showText' => true])

{{-- KHỐI NHẬN DIỆN: logo + tên + dòng phụ. --}}
@php
    $logo = \App\Services\Shop\StoreProfile::logoUrl();
    $ten = \App\Services\Shop\StoreProfile::name();
    $tagline = \App\Services\Shop\StoreProfile::tagline();
@endphp

@if($logo)
    <img src="{{ $logo }}"
         alt="{{ $ten }}"
         width="{{ $size }}"
         height="{{ $size }}"
         class="site-brand__logo"
         style="width: {{ $size }}px; height: {{ $size }}px; object-fit: contain;">
@else
    <x-site.brand-mark :size="$size" />
@endif

@if($showText)
    <span class="site-header__brand-text">
        <span class="site-header__brand-name">{{ $ten }}</span>
        @if($tagline)
            <span class="site-header__brand-tagline">{{ $tagline }}</span>
        @endif
    </span>
@endif
