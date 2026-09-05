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

            {{--
                Trạng thái rỗng phải chỉ đường đi tiếp, không chỉ báo "trống".
            --}}
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

            {{--
                data-wishlist-list: đánh dấu ĐÂY LÀ CHÍNH DANH SÁCH YÊU
                THÍCH, để resources/js/wishlist.js KHÔNG chặn nút tim ở
                trang này.

                Ở nơi khác, bấm tim chỉ đổi một cái icon nên vẽ lại tại
                chỗ là đúng. Ở đây, bấm tim làm sản phẩm RỜI KHỎI danh
                sách — kéo theo số trang, phân trang và cả trạng thái
                "danh sách đang trống". Xoá một thẻ trong DOM rồi để
                nguyên "Trang 1/3" là hiển thị một con số không còn đúng.
                Tải lại trang ở đây là hành vi trung thực.
            --}}
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
