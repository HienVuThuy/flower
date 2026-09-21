<!DOCTYPE html>
{{-- data-theme-effect cho JS biết cần nạp động module hiệu ứng nào
     (rỗng = theme không có chuyển động). --}}
<html lang="vi"
      data-theme="{{ $activeTheme }}"
      data-theme-effect="{{ $activeThemeEffect }}"
      data-scheme="{{ \App\Services\Shop\DisplayScheme::current() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Trang chủ') - {{ \App\Services\Shop\StoreProfile::name() }}</title>

    <meta name="description"
          content="@yield('meta_description', \App\Services\Shop\StoreProfile::name() . ' — ' . \App\Services\Shop\StoreProfile::get('site_tagline'))">

    <x-site.social-meta
        :title="trim($__env->yieldContent('title'))"
        :description="trim($__env->yieldContent('meta_description'))"
        :image="trim($__env->yieldContent('og_image'))"
        :type="trim($__env->yieldContent('og_type')) ?: 'website'"
        :canonical="trim($__env->yieldContent('canonical'))" />

    {{-- Phông chữ nội dung: nạp sớm để chữ không đổi kiểu giữa chừng. --}}
    @foreach(['inter-vietnamese-400-normal', 'inter-latin-400-normal'] as $phong)
        <link rel="preload" as="font" type="font/woff2" crossorigin
              href="{{ Vite::asset('resources/fonts/' . $phong . '.woff2') }}">
    @endforeach

    <script>document.documentElement.classList.add('has-js');</script>

    <x-site.scheme-boot />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <x-site.speculation-rules />

    @stack('head')
</head>

<body>

<a href="#noi-dung" class="skip-link">Bỏ qua tới nội dung</a>

<x-site.icon-sprite />

<x-site.announcement-bar />
<x-site.header />

<main id="noi-dung" tabindex="-1">

    <div class="container-shop pt-3">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert" data-auto-dismiss="success">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert" data-auto-dismiss="error">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session()->has(\App\Services\Checkout\CheckoutSource::COUPON_NOTICE_KEY))
            <div class="alert alert-warning alert-dismissible fade show" role="alert"
                 data-auto-dismiss="warning">
                {{ session()->pull(\App\Services\Checkout\CheckoutSource::COUPON_NOTICE_KEY) }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('info'))
            <div class="alert alert-info alert-dismissible fade show" role="alert" data-auto-dismiss="success">
                {{ session('info') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

    </div>

    @yield('content')

</main>

<x-site.ai-chat />

<x-site.footer />

</body>
</html>
