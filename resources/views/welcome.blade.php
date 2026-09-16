@extends('layouts.app')

@section('title', 'Trang chủ')

@section('content')

{{-- ============ 1. HERO ============ --}}
<section class="hero-section">
    <div class="container-shop">

        <div class="hero-section__grid">

            <div class="hero-section__copy">
                <span class="text-label hero-section__eyebrow d-block">
                    {{ \App\Services\Shop\StoreProfile::tagline() }}
                </span>

                <h1 class="text-h1 hero-section__title">
                    Vẻ đẹp tự nhiên,
                    <em>gần&nbsp;hơn</em> mỗi ngày
                </h1>

                <p class="hero-section__lead">
                    Hoa tươi cắt trong ngày và cây cảnh chọn theo đúng chỗ bạn
                    định đặt. Giao trong nội thành Hà Nội, gói cẩn thận, kèm
                    hướng dẫn chăm sóc.
                </p>

                <div class="hero-section__actions">
                    <a href="{{ route('shop.products.index') }}" class="btn btn-primary-brand">
                        Xem sản phẩm
                    </a>
                    <a href="{{ route('shop.advisor.index') }}" class="btn btn-secondary-brand">
                        Chọn cây theo nhu cầu
                    </a>
                </div>
            </div>

            @if(!empty($heroImages))
                <div class="hero-section__visual hero-carousel"
                     data-hero-carousel
                     data-interval="6000">
                    @foreach($heroImages as $i => $image)
                        <img
                            src="{{ $image['url'] }}"
                            alt="{{ $image['alt'] }}"
                            class="hero-carousel__slide {{ $i === 0 ? 'is-active' : '' }}"
                            loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                            @if($i === 0) fetchpriority="high" @endif
                        >
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</section>

<section class="section-sm">
    <div class="container-shop">

        <div class="section-header">
            <div>
                <h2 class="text-h2 section-header__title">Bạn đang tìm gì?</h2>
            </div>
        </div>

        <div class="intent-grid">
            @foreach(\App\Enums\ShoppingIntent::cases() as $intent)
                <a href="{{ route('shop.intents.show', $intent->value) }}" class="intent-card">
                    <img
                        src="{{ Vite::asset('resources/images/catalog/' . $intent->image()) }}"
                        alt=""
                        class="intent-card__image"
                        loading="lazy"
                    >
                    <span>
                        {{ $intent->label() }}
                        <span class="intent-card__tagline">{{ $intent->tagline() }}</span>
                    </span>
                </a>
            @endforeach
        </div>

    </div>
</section>

<section class="section-sm">
    <div class="container-shop">

        <div class="section-header">
            <div>
                <h2 class="text-h2 section-header__title">Danh mục</h2>
            </div>
            <a href="{{ route('shop.categories.index') }}" class="btn btn-ghost">Xem tất cả</a>
        </div>

        <div class="row g-4">
            @foreach($categories as $category)
                <div class="col-6 col-lg-3">
                    <x-category.card :category="$category" />
                </div>
            @endforeach
        </div>

    </div>
</section>

@if($featuredProducts->isNotEmpty())
<section class="section-sm">
    <div class="container-shop">

        <div class="section-header">
            <div>
                <h2 class="text-h2 section-header__title">Hàng mới về</h2>
            </div>
            <a href="{{ route('shop.products.index') }}" class="btn btn-ghost">Xem tất cả</a>
        </div>

        <div class="row g-4">
            @foreach($featuredProducts as $product)
                <div class="col-6 col-md-4 col-lg-3">
                    <x-product.card :product="$product" />
                </div>
            @endforeach
        </div>

    </div>
</section>
@endif

<x-product.recommendations
    :items="$recommendations"
    :personalized="$recommendationsArePersonal"
    ref="home"
/>

@if($mostWished->isNotEmpty())
<section class="section-sm">
    <div class="container-shop">

        <div class="section-header">
            <div>
                <h2 class="text-h2 section-header__title">Được yêu thích gần đây</h2>
                <p class="text-body-sm mt-2 mb-0">
                    Những món khách vừa lưu vào danh sách yêu thích.
                </p>
            </div>
        </div>

        <div class="row g-4">
            @foreach($mostWished as $product)
                <div class="col-6 col-md-4 col-lg-3">
                    <x-product.card :product="$product" />
                </div>
            @endforeach
        </div>

    </div>
</section>
@endif

<section class="section-sm">
    <div class="container-shop">
        <x-site.banner-carousel />
    </div>
</section>

<section class="section-sm">
    <div class="container-shop">
        <div class="story-section">
            <div class="story-section__grid">

                <div class="story-section__media">
                    <img
                        src="{{ Vite::asset('resources/images/editorial/story-studio.jpg') }}"
                        alt="Không gian chuẩn bị hoa tại {{ \App\Services\Shop\StoreProfile::name() }}"
                        loading="lazy"
                    >
                </div>

                <div class="story-section__copy">
                    <span class="text-label story-section__eyebrow d-block">Về cửa hàng</span>

                    <h2 class="text-h2">Hoa được chọn từng cành, cây được chọn theo chỗ đặt</h2>

                    <p>
                        Mỗi bó hoa được bó trong ngày giao, không giữ sẵn qua đêm.
                        Cây cảnh thì chọn theo ánh sáng và độ ẩm của đúng chỗ bạn định
                        đặt — vì cây hợp chỗ thì mới sống lâu.
                    </p>

                    <div class="hero-section__actions">
                        <a href="{{ route('shop.pages.show', 'gioi-thieu') }}" class="btn btn-secondary-brand">
                            Tìm hiểu thêm
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<section class="section-sm">
    <div class="container-shop">
        <x-site.commitments />
    </div>
</section>

@endsection
