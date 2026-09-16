@extends('layouts.app')

@section('title', 'Giỏ hàng')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Giỏ hàng']]" />

        <h1 class="text-h2 mb-4">Giỏ hàng</h1>

        {{-- KHUNG ĐỂ THAY RUỘT. --}}
        <div data-cart-live="{{ route('shop.cart.fragment') }}">
            @include('shop.cart.partials.noi-dung')
        </div>

    </div>
</section>

@endsection
