@extends('layouts.app')

@section('title', 'Phụ kiện & vật tư chăm sóc')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Phụ kiện & vật tư']]" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">Mua kèm</span>
                <h1 class="text-h2 section-header__title">Phụ kiện &amp; vật tư chăm sóc</h1>
                <p class="mb-0">
                    {{--
                        Nói rõ trang này là HÀNG PHỤ TRỢ và chỉ đường về hàng
                        chính. Khách lạc vào đây khi đang tìm hoa phải có lối
                        ra ngay, không phải bấm Back.
                    --}}
                    Chậu, đất, phân bón và dụng cụ để cây bạn sống lâu hơn.
                    Đang tìm hoa hoặc cây?
                    <a href="{{ route('shop.products.index') }}">Xem hoa &amp; cây cảnh</a>.
                </p>
            </div>
        </div>

        {{--
            HAI NHÓM, KHÁC NHAU Ở HÀNH VI MUA:
              Phụ kiện       - đồ dùng bền, mua một lần dùng lâu
              Vật tư chăm sóc - thứ tiêu hao, hết là mua lại
            Xem QĐ-29.
        --}}
        <div class="supply-filters">
            <a href="{{ route('shop.supplies.index') }}"
               class="filter-chip {{ ! request('category') ? 'is-active' : '' }}">
                Tất cả
            </a>

            @foreach($categories as $category)
                <a href="{{ request()->fullUrlWithQuery(['category' => $category->slug, 'page' => null]) }}"
                   class="filter-chip {{ request('category') === $category->slug ? 'is-active' : '' }}">
                    {{ $category->name }}
                    <span class="filter-chip__count">{{ $category->products_count }}</span>
                </a>
            @endforeach

            <label class="supply-filters__sort">
                <span class="visually-hidden">Sắp xếp</span>
                <select class="form-select form-select-sm" onchange="location.href = this.value">
                    @foreach([
                        '' => 'Giá thấp đến cao',
                        'price_desc' => 'Giá cao đến thấp',
                        'newest' => 'Mới nhất',
                    ] as $value => $label)
                        <option
                            value="{{ request()->fullUrlWithQuery(['sort' => $value ?: null, 'page' => null]) }}"
                            @selected(request('sort', '') === $value)
                        >{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @if($products->isEmpty())
            <x-site.empty-state
                title="Chưa có phụ kiện nào trong nhóm này"
                text="Thử bỏ bộ lọc, hoặc xem toàn bộ phụ kiện và vật tư."
            >
                <x-slot:actions>
                    <a href="{{ route('shop.supplies.index') }}" class="btn btn-secondary-brand btn-sm">Xem tất cả</a>
                </x-slot:actions>
            </x-site.empty-state>
        @else
            <div class="row g-4 mt-1">
                @foreach($products as $product)
                    <div class="col-6 col-md-4 col-lg-3">
                        <x-product.card :product="$product" />
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $products->links() }}</div>
        @endif

        <p class="text-caption mt-4 mb-0">
            Chưa chắc cần gì? Mở trang một cây bất kỳ — khối
            <strong>&ldquo;Mua kèm cho món này&rdquo;</strong> chỉ gợi đúng thứ
            dùng được với cây đó.
        </p>

    </div>
</section>

@endsection
