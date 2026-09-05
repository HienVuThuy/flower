@extends('layouts.admin')

@section('title', $product->name)

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h1 class="admin-page-title">
            {{ $product->name }}
        </h1>

        <p class="admin-page-subtitle">
            Chi tiết sản phẩm
        </p>

    </div>

    <div class="d-flex gap-2">

        <a
            href="{{ route('admin.products.edit', $product) }}"
            class="btn btn-primary-brand px-4"
        >
            Sửa
        </a>

        <a
            href="{{ route('admin.products.index') }}"
            class="btn btn-light px-4"
        >
            Quay lại
        </a>

    </div>

</div>

<div class="row g-4">

    <div class="col-lg-5">

        <div class="admin-panel p-3">

            @if($product->main_image)

                <img
                    src="{{ asset('storage/' . $product->main_image) }}"
                    alt="{{ $product->name }}"
                    class="img-fluid rounded-4 w-100"
                >

            @else

                <div
                    class="d-flex align-items-center justify-content-center text-muted"
                    style="height: 400px;"
                >
                    <x-site.leaf-placeholder style="width: 30%; height: 30%;" />
                </div>

            @endif

        </div>

    </div>

    <div class="col-lg-7">

        <div class="admin-panel p-4">

            <div class="mb-4">

                <span class="text-muted small">
                    Mã sản phẩm
                </span>

                <div class="fw-semibold">
                    {{ $product->product_code }}
                </div>

            </div>

            <div class="mb-4">

                <span class="text-muted small">
                    Danh mục
                </span>

                <div class="fw-semibold">
                    {{ $product->category->name }}
                </div>

            </div>

            <div class="mb-4">

                <span class="text-muted small">
                    Loại sản phẩm
                </span>

                {{-- Trước đây in giá trị thô ('plant'), không phải nhãn tiếng Việt. --}}
                <div class="fw-semibold">
                    {{ $product->product_type?->label() ?? '—' }}
                </div>

            </div>

            <div class="mb-4">

                <span class="text-muted small">
                    Hình thức bán
                </span>

                {{-- Trước đây in giá trị thô ('bouquet'), không phải nhãn tiếng Việt. --}}
                <div class="fw-semibold">
                    {{ $product->selling_form?->label() ?? '—' }}
                </div>

            </div>

            <div class="mb-4">

                <span class="text-muted small">
                    Giá bán
                </span>

                @php $price = $product->price(); @endphp

                <div class="fs-4 fw-bold">

                    @if($price->isContactForPrice())

                        Liên hệ báo giá

                    @elseif($price->isDiscounted())

                        <span class="text-decoration-line-through text-muted fs-6 fw-normal me-2">
                            <x-site.money :amount="$price->basePrice" />
                        </span>

                        <span class="text-accent">
                            <x-site.money :amount="$price->finalPrice" />
                        </span>

                        <span class="price-badge ms-2 align-middle">−{{ $price->discountPercent() }}%</span>

                    @else

                        <x-site.money :amount="$price->finalPrice" />

                    @endif

                </div>

                @if($price->promotion)
                    <div class="text-caption mt-1">
                        Theo chương trình
                        <a href="{{ route('admin.promotions.edit', $price->promotion) }}">
                            {{ $price->promotion->name }}
                        </a>
                    </div>
                @endif

            </div>

            <div class="mb-4">

                <span class="text-muted small">
                    Tồn kho
                </span>

                <div class="fw-semibold">

                    @if(! $product->track_inventory)

                        <span class="badge text-bg-light">Không quản lý tồn kho</span>

                    @elseif($product->inStock())

                        <span class="badge text-bg-success">Còn {{ $product->stock_quantity }} sản phẩm</span>

                    @else

                        <span class="badge text-bg-danger">Hết hàng</span>

                    @endif

                </div>

            </div>

            <div class="mb-4">

                <span class="text-muted small">
                    Lượt xem
                </span>

                <div class="fw-semibold">
                    {{ number_format($product->view_count) }}
                </div>

            </div>

            <div>

                <span class="text-muted small">
                    Mô tả
                </span>

                <div class="mt-2">
                    {!! nl2br(e($product->description)) !!}
                </div>

            </div>

        </div>

    </div>

</div>

@if(is_array($product->care_info) && array_filter($product->care_info))

    <div class="admin-panel p-4 mt-4">

        <h2 class="h5 fw-bold mb-3">
            Thông tin chăm sóc
        </h2>

        <x-product.care-guide :product="$product" />

    </div>

@endif

<div class="admin-panel mt-4">

    <div class="p-4 border-bottom">

        <div
            class="d-flex
                   flex-column
                   flex-md-row
                   justify-content-between
                   align-items-md-center
                   gap-3"
        >

            <div>

                <h2 class="h5 fw-bold mb-1">
                    Biến thể sản phẩm
                </h2>

                <p class="text-muted mb-0">
                    Các quy cách hoặc phiên bản bán khác nhau
                    của sản phẩm.
                </p>

            </div>

        </div>

    </div>


    @if($product->variants->isNotEmpty())

        <div class="table-responsive">

            <table class="admin-table align-middle mb-0">

                <thead>
                    <tr>
                        <th>Biến thể</th>
                        <th>Mã</th>
                        <th>Giá</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>

                <tbody>

                @foreach($product->variants as $variant)

                    <tr>

                        <td>

                            <div class="fw-semibold">
                                {{ $variant->name }}
                            </div>

                            @if($variant->description)

                                <small class="text-muted">
                                    {{
                                        \Illuminate\Support\Str::limit(
                                            $variant->description,
                                            100
                                        )
                                    }}
                                </small>

                            @endif

                        </td>

                        <td>
                            {{ $variant->code ?: '—' }}
                        </td>

                        <td>

                            @if($variant->price !== null)

                                <x-site.money :amount="$variant->price" />

                            @else

                                <span class="text-muted">
                                    Liên hệ
                                </span>

                            @endif

                        </td>

                        <td>

                            @if($variant->is_active)

                                <span
                                    class="badge text-bg-success"
                                >
                                    Đang bán
                                </span>

                            @else

                                <span
                                    class="badge text-bg-secondary"
                                >
                                    Tạm ẩn
                                </span>

                            @endif

                        </td>

                    </tr>

                @endforeach

                </tbody>

            </table>

        </div>

    @else

        <x-site.empty-state
            title="Chưa có biến thể"
            text="Bạn có thể thêm biến thể trong trang sửa sản phẩm."
        />

    @endif

</div>

@endsection