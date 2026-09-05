@extends('layouts.app')

@section('title', 'Giỏ hàng')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Giỏ hàng']]" />

        <h1 class="text-h2 mb-4">Giỏ hàng</h1>

        {{--
            KHUNG ĐỂ THAY RUỘT.

            `data-cart-live` là chỗ resources/js/cart-live.js đặt HTML mới
            do máy chủ vẽ, sau mỗi lần sửa giỏ. Nó phải bọc CẢ trạng thái
            trống lẫn trạng thái có hàng: xoá món cuối cùng thì khối này
            biến thành màn hình "Giỏ hàng đang trống", không phải một
            danh sách rỗng.
        --}}
        <div data-cart-live="{{ route('shop.cart.fragment') }}">
            @include('shop.cart.partials.noi-dung')
        </div>

    </div>
</section>

@endsection
