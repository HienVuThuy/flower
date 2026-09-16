@extends('layouts.app')

@section('title', $promotion->name)

@section('content')

@php
    $deal = $promotion->headlineDiscount();
    $endsIn = $promotion->endsInText();
    $window = $promotion->scheduleText();
@endphp

{{-- KHỐI ĐẦU TRANG MANG MÀU CỦA CHÍNH SỰ KIỆN. --}}
<section class="event-hero" @if($promotion->theme_key) data-event-theme="{{ $promotion->theme_key }}" @endif>

    @if($promotion->banner)
        <img src="{{ asset('storage/'.$promotion->banner) }}" alt="" class="event-hero__bg">
    @endif

    <div class="container-shop event-hero__inner">

        <x-site.breadcrumb :items="[['label' => 'Sự kiện'], ['label' => $promotion->name]]" />

        <div class="event-hero__body">

            @unless($isRunning)
                <span class="event-hero__ended">Chương trình đã kết thúc</span>
            @endunless

            <h1 class="event-hero__title">{{ $promotion->name }}</h1>

            @if($promotion->short_description)
                <p class="event-hero__lead">{{ $promotion->short_description }}</p>
            @endif

            <div class="event-hero__facts">
                @if($deal)
                    <span class="event-hero__deal">{{ $deal }}</span>
                @endif

                @if($promotion->starts_at || $promotion->ends_at)
                    <span class="event-hero__fact">
                        <x-site.icon name="arrow-repeat" />
                        <x-site.time :at="$promotion->starts_at" format="d/m/Y">Từ khi mở</x-site.time>
                        &ndash;
                        <x-site.time :at="$promotion->ends_at" format="d/m/Y">chưa có ngày kết thúc</x-site.time>
                    </span>
                @endif

                @if($window)
                    <span class="event-hero__fact">
                        <x-site.icon name="arrow-repeat" /> {{ $window }}
                    </span>
                @endif

                @if($isRunning && $endsIn)
                    <span class="event-hero__fact event-hero__fact--urgent">{{ $endsIn }}</span>
                @endif
            </div>

        </div>
    </div>
</section>

<section class="section-sm">
    <div class="container-shop">

        @if($coupons->isNotEmpty())
            <div class="mb-5">
                <div class="section-header">
                    <div>
                        <span class="text-label section-header__eyebrow d-block">Ưu đãi kèm theo</span>
                        <h2 class="text-h3 section-header__title">Mã giảm giá của chương trình</h2>
                        <p class="mb-0">
                            Chỉ phát trong chương trình này. Lưu về ví để dùng ở bước thanh toán.
                        </p>
                    </div>
                </div>

                <div class="voucher-grid">
                    @foreach($coupons as $coupon)
                        <x-shop.voucher-card
                            :coupon="$coupon"
                            :saved="in_array($coupon->id, $claimedIds, true)" />
                    @endforeach
                </div>
            </div>
        @endif

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">Hàng trong chương trình</span>
                <h2 class="text-h3 section-header__title">
                    {{ $products->count() }} sản phẩm được giảm giá
                </h2>
            </div>
            <a href="{{ route('shop.products.index') }}" class="btn btn-ghost">Xem tất cả sản phẩm</a>
        </div>

        @if($products->isEmpty())
            <x-site.empty-state
                title="Chương trình chưa gắn sản phẩm nào"
                text="Cửa hàng đang chuẩn bị. Bạn xem tạm những sản phẩm khác nhé."
            >
                <x-slot:actions>
                    <a href="{{ route('shop.products.index') }}" class="btn btn-secondary-brand btn-sm">Xem hoa &amp; cây cảnh</a>
                </x-slot:actions>
            </x-site.empty-state>
        @else
            <div class="row g-4">
                @foreach($products as $product)
                    <div class="col-6 col-md-4 col-lg-3">
                        <x-product.card :product="$product" />
                    </div>
                @endforeach
            </div>
        @endif

        @if($promotion->description)
            <div class="mt-5" style="max-width: 68ch;">
                <h2 class="text-h4 mb-3">Về chương trình</h2>
                <div>{!! nl2br(e($promotion->description)) !!}</div>
            </div>
        @endif

    </div>
</section>

@endsection
