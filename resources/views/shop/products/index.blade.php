@extends('layouts.app')

@section('title', request('q') ? 'Tìm kiếm: ' . request('q') : 'Sản phẩm')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Sản phẩm']]" />

        <div class="section-header">
            <div>
                {{-- NHÃN NHỎ CHỈ HIỆN KHI NÓ NÓI THÊM ĐƯỢC GÌ ĐÓ. --}}
                @if($activePromotion)
                    <span class="text-label section-header__eyebrow d-block">Chương trình khuyến mại</span>
                @endif
                <h1 class="text-h1 section-header__title">
                    @if($activePromotion)
                        {{ $activePromotion->name }}
                    @elseif(request('q'))
                        Kết quả cho "{{ request('q') }}"
                    @else
                        Tất cả sản phẩm
                    @endif
                </h1>

                @if($activePromotion && $activePromotion->short_description)
                    <p class="text-body-sm mt-2 mb-0" style="max-width: 52ch;">
                        {{ $activePromotion->short_description }}
                    </p>
                @endif
            </div>
        </div>

        @if(! request()->hasAny($moiThamSoLoc))
            <a href="{{ route('shop.advisor.index') }}" class="advisor-invite">
                <x-site.icon name="sliders" class="advisor-invite__icon" />

                <span class="advisor-invite__text">
                    <strong>Chưa biết chọn cây nào?</strong>
                    Trả lời vài câu về chỗ đặt và kinh nghiệm chăm cây —
                    chúng tôi lọc sẵn cho bạn.
                </span>

                <span class="advisor-invite__cta">Chọn theo nhu cầu</span>
            </a>
        @endif

        <div class="row g-4">

            <div class="col-lg-3">

                <button
                    type="button"
                    class="btn btn-secondary-brand w-100 d-lg-none mb-3"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#filterDrawer"
                >
                    <x-site.icon name="sliders" />
                    Bộ lọc &amp; sắp xếp
                </button>

                <div class="d-none d-lg-block">
                    @include('shop.products._filter-form')
                </div>

            </div>

            <div class="col-lg-9">

                <x-product.search-notice
                    :terms="$search"
                    :relaxed="$searchRelaxed"
                    :total="$products->total()"
                />

                @if($products->isEmpty())
                    <x-site.empty-state
                        title="Không tìm thấy sản phẩm phù hợp"
                        :text="$search->isNotEmpty()
                            ? 'Đã thử cả cách viết không dấu và các lỗi gõ gần giống nhưng vẫn không có kết quả. Cửa hàng có thể chưa bán mặt hàng này.'
                            : 'Thử từ khóa khác, hoặc xem danh mục và sản phẩm nổi bật.'"
                    >
                        <x-slot:actions>
                            <a href="{{ route('shop.products.index') }}" class="btn btn-secondary-brand btn-sm">Xóa bộ lọc</a>
                            <a href="{{ route('shop.categories.index') }}" class="btn btn-ghost btn-sm">Xem danh mục</a>
                        </x-slot:actions>
                    </x-site.empty-state>
                @else
                    <div class="row g-4">
                        @foreach($products as $product)
                            <div class="col-6 col-md-4">
                                <x-product.card :product="$product" />
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4">{{ $products->links() }}</div>
                @endif

            </div>

        </div>

    </div>
</section>

<div class="offcanvas offcanvas-bottom mobile-drawer" tabindex="-1" id="filterDrawer" style="height: 80vh;">
    <div class="offcanvas-header">
        <span class="fw-bold">Bộ lọc &amp; sắp xếp</span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng"></button>
    </div>
    <div class="offcanvas-body">
        @include('shop.products._filter-form')
    </div>
</div>

@endsection
