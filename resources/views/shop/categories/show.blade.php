@extends('layouts.app')

@section('title', $category->name)

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[
            ['label' => 'Danh mục', 'url' => route('shop.categories.index')],
            ['label' => $category->name],
        ]" />

        <div class="section-header">
            <div>
                <h1 class="text-h1 section-header__title">{{ $category->name }}</h1>
                @if($category->description)
                    <p class="text-body-sm mt-2 mb-0" style="max-width: 46ch;">{{ $category->description }}</p>
                @endif
            </div>
        </div>

        @if($products->isEmpty())
            <x-site.empty-state
                title="Danh mục này chưa có sản phẩm"
                text="Hãy quay lại sau hoặc xem các danh mục khác."
            />
        @else
            <div class="row g-4">
                @foreach($products as $product)
                    <div class="col-6 col-md-4 col-lg-3">
                        {{--
                            :show-category="false" — tiêu đề trang đã là
                            tên danh mục này rồi, in lại trên từng thẻ là
                            viết cùng một chữ 12 lần trong một màn hình.
                        --}}
                        <x-product.card :product="$product" :show-category="false" />
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $products->links() }}</div>
        @endif

    </div>
</section>

@endsection
