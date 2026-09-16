@extends('layouts.app')

@section('title', $title)

@section('content')

<section class="section-sm">
    <div class="container-narrow">

        <x-site.breadcrumb :items="[['label' => $title]]" />

        <article class="static-page">

            <h1 class="text-h2 mb-4">{{ $title }}</h1>

            @yield('page')

        </article>

        <nav class="static-page__more">
            <a href="{{ route('shop.products.index') }}" class="btn btn-primary-brand">Xem sản phẩm</a>
            <a href="{{ route('shop.pages.show', 'lien-he') }}" class="btn btn-ghost">Liên hệ</a>
            <a href="{{ route('shop.orders.lookup') }}" class="btn btn-ghost">Tra cứu đơn hàng</a>
        </nav>

    </div>
</section>

@endsection
