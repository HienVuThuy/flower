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

    <script>document.documentElement.classList.add('has-js');</script>

    <x-site.scheme-boot />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <x-site.speculation-rules />
</head>

<body>

<x-site.icon-sprite />

<x-site.announcement-bar />
<x-site.header />

<main>

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
