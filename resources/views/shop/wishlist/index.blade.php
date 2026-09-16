@extends('layouts.app')

@section('title', 'Sản phẩm yêu thích')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <div class="mb-4">
            <span class="text-label d-block mb-2">Tài khoản</span>
            <h1 class="text-h1 mb-2">Sản phẩm yêu thích</h1>
            <p class="mb-0">
                @if($products->total() > 0)
                    {{ $products->total() }} sản phẩm bạn đã lưu lại.
                @else
                    Nơi lưu những sản phẩm bạn muốn xem lại sau.
                @endif
            </p>
        </div>

        @if($products->isEmpty())

            <div class="surface-card empty-state">
                <x-site.icon name="heart" class="empty-state__figure" />
                <p class="empty-state__title">Bạn chưa lưu sản phẩm nào.</p>
                <p class="mb-0">Bấm hình trái tim trên sản phẩm để lưu lại xem sau.</p>
                <div class="empty-state__actions">
                    <a href="{{ route('shop.products.index') }}" class="btn btn-primary-brand">
                        Xem sản phẩm
                    </a>
                </div>
            </div>

        @else

            <div class="row g-4" data-wishlist-list>
                @foreach($products as $product)
                    <div class="col-6 col-md-4 col-lg-3">
                        <x-product.card :product="$product" />
                    </div>
                @endforeach
            </div>

            @if($products->hasPages())
                <div class="mt-4">{{ $products->links() }}</div>
            @endif

        @endif

    </div>
</section>

@endsection
