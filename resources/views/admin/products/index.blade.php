@extends('layouts.admin')

@section('title', 'Sản phẩm')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

    <div>

        <h1 class="admin-page-title">
            Sản phẩm
        </h1>

        <p class="admin-page-subtitle">
            Quản lý hoa, cây cảnh và các hình thức sản phẩm.
        </p>

    </div>

    <div class="d-flex gap-2">
        @if($thungRac)
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-admin">Về danh sách đang dùng</a>
        @else
            <a href="{{ route('admin.products.index', ['thung_rac' => 1]) }}" class="btn btn-outline-admin">
                Thùng rác ({{ $soDaXoa }})
            </a>
        @endif

        <a
            href="{{ route('admin.products.create') }}"
            class="btn btn-primary-brand px-4"
        >
            + Thêm sản phẩm
        </a>
    </div>

</div>

<x-admin.filter-bar
    :action="route('admin.products.index')"
    placeholder="Tìm theo tên hoặc mã sản phẩm…"
    :total="$products->total()"
>
    <select name="category" class="form-select" aria-label="Lọc theo danh mục">
        <option value="">Mọi danh mục</option>
        @foreach($categories as $category)
            <option value="{{ $category->id }}" @selected(request('category') == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>

    <select name="status" class="form-select" aria-label="Lọc theo trạng thái">
        <option value="">Mọi trạng thái</option>
        <option value="active" @selected(request('status') === 'active')>Đang bán</option>
        <option value="draft" @selected(request('status') === 'draft')>Nháp</option>
        <option value="out_of_stock" @selected(request('status') === 'out_of_stock')>Tạm hết hàng</option>
    </select>

    <select name="kho" class="form-select" aria-label="Lọc theo tồn kho">
        <option value="">Mọi mức tồn kho</option>
        <option value="het" @selected(request('kho') === 'het')>Đã hết hàng</option>
        <option value="sap-het" @selected(request('kho') === 'sap-het')>Sắp hết (&le; {{ $lowStock }})</option>
    </select>
</x-admin.filter-bar>

<x-admin.bulk-bar
    :action="route('admin.products.bulk')"
    :viec="[
        'ban' => 'Chuyển sang Đang bán',
        'an' => 'Chuyển sang Tạm ẩn',
        'nhap' => 'Chuyển sang Bản nháp',
        'xoa' => 'Xoá',
    ]"
    :canh-bao="[
        'xoa' => 'Xoá {so} sản phẩm đã chọn?',
    ]"
/>

<div class="admin-panel">

    <div class="table-responsive">

        <table class="admin-table align-middle mb-0">

            <thead>

                <tr>
                    <th style="width: 2.5rem;">
                        <input type="checkbox" class="admin-check"
                               form="bulk-form" data-bulk-all
                               aria-label="Chọn tất cả dòng đang hiện">
                    </th>
                    <th>Ảnh</th>

                    <x-admin.sort-header khoa="ten" nhan="Sản phẩm" />
                    <th>Danh mục</th>
                    <th>Hình thức</th>
                    <x-admin.sort-header khoa="gia" nhan="Giá" dau="giam" />
                    <x-admin.sort-header khoa="ton-kho" nhan="Tồn kho" />
                    <x-admin.sort-header khoa="trang-thai" nhan="Trạng thái" />
                    <th class="text-end">Thao tác</th>
                </tr>

            </thead>

            <tbody>

            @forelse($products as $product)

                <tr>

                    <td>
                        <input type="checkbox" class="admin-check"
                               form="bulk-form" name="ids[]" value="{{ $product->id }}"
                               data-bulk-item
                               aria-label="Chọn {{ $product->name }}">
                    </td>

                    <td>

                        @if($product->main_image)

                            <img
                                src="{{ asset('storage/' . $product->main_image) }}"
                                alt="{{ $product->name }}"
                                class="admin-thumb"
                            >

                        @else

                            <div class="admin-thumb admin-thumb--placeholder">
                                <x-site.leaf-placeholder />
                            </div>

                        @endif

                    </td>

                    <td>

                        <div class="fw-semibold">
                            {{ $product->name }}
                        </div>

                        <small class="text-muted">
                            {{ $product->product_code }}
                        </small>

                    </td>

                    <td>
                        {{ $product->category->name }}
                    </td>

                    <td>
                        {{ $product->selling_form?->label() ?? '—' }}
                    </td>

                    <td>

                        @php $price = $product->price(); @endphp

                        @if($price->isContactForPrice())

                            <span class="text-muted">Liên hệ</span>

                        @elseif($price->isDiscounted())

                            <div class="text-decoration-line-through text-muted small">
                                <x-site.money :amount="$price->basePrice" />
                            </div>

                            <div class="fw-semibold text-accent">
                                <x-site.money :amount="$price->finalPrice" />
                            </div>

                            <div class="text-caption">{{ $price->promotion->name }}</div>

                        @else

                            <x-site.money :amount="$price->finalPrice" />

                        @endif

                    </td>

                    <td>

                        @if($product->track_inventory)
                            <span class="{{ $product->stock_quantity <= 0 ? 'text-danger fw-semibold' : '' }}">
                                {{ number_format((int) $product->stock_quantity, 0, ',', '.') }}
                            </span>
                        @else
                            <span class="text-muted">Làm theo đơn</span>
                        @endif

                    </td>

                    <td>

                        @switch($product->status)

                            @case('active')
                                <span class="badge text-bg-success">
                                    Đang bán
                                </span>
                                @break

                            @case('inactive')
                                <span class="badge text-bg-secondary">
                                    Tạm ẩn
                                </span>
                                @break

                            @case('out_of_stock')
                                <span class="badge text-bg-danger">
                                    Hết hàng
                                </span>
                                @break

                            @default
                                <span class="badge text-bg-warning">
                                    Bản nháp
                                </span>

                        @endswitch

                    </td>

                    <td class="text-end">

                        @if($product->trashed())
                        <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                            <form action="{{ route('admin.products.restore', $product->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-admin">Khôi phục</button>
                            </form>

                            <form action="{{ route('admin.products.force-destroy', $product->id) }}" method="POST" class="d-flex gap-1">
                                @csrf
                                @method('DELETE')
                                <label class="visually-hidden" for="xn-{{ $product->id }}">Gõ tên sản phẩm để xoá vĩnh viễn</label>
                                <input id="xn-{{ $product->id }}" name="xac_nhan" class="form-control form-control-sm"
                                       style="max-width:11rem" placeholder="Gõ đúng tên để xoá hẳn" required>
                                <button type="submit" class="btn btn-sm btn-outline-danger">Xoá vĩnh viễn</button>
                            </form>
                        </div>
                        @else
                        <div class="d-inline-flex gap-2">

                            <a
                                href="{{ route('admin.products.show', $product) }}"
                                class="btn btn-sm btn-outline-primary"
                            >
                                Xem
                            </a>

                            <a
                                href="{{ route('admin.products.edit', $product) }}"
                                class="btn btn-sm btn-outline-secondary"
                            >
                                Sửa
                            </a>

                            <form
                                action="{{ route('admin.products.destroy', $product) }}"
                                method="POST"
                                onsubmit="return confirm('Chuyển sản phẩm này vào thùng rác? Khôi phục được ở mục Thùng rác.');"
                            >

                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-danger"
                                >
                                    Xóa
                                </button>

                            </form>

                        </div>
                        @endif

                    </td>

                </tr>

            @empty

                <x-admin.empty-row :colspan="9" empty="Chưa có sản phẩm nào." />

            @endforelse

            </tbody>

        </table>

    </div>

    @if($products->hasPages())

        <div class="p-3 border-top">
            {{ $products->links() }}
        </div>

    @endif

</div>

@endsection