@extends('layouts.admin')

@section('title', 'Danh mục')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

    <div>
        <h1 class="admin-page-title">
            Danh mục
        </h1>

        <p class="admin-page-subtitle">
            Quản lý nhóm sản phẩm hoa và cây cảnh.
        </p>
    </div>

    <a
        href="{{ route('admin.categories.create') }}"
        class="btn btn-primary-brand px-4"
    >
        + Thêm danh mục
    </a>

</div>

<x-admin.filter-bar
    :action="route('admin.categories.index')"
    placeholder="Tìm theo tên danh mục…"
    :total="$categories->total()"
>
    <select name="kind" class="form-select" aria-label="Lọc theo nhóm">
        <option value="">Mọi nhóm</option>
        <option value="plant" @selected(request('kind') === 'plant')>Hoa &amp; cây cảnh</option>
        <option value="supply" @selected(request('kind') === 'supply')>Phụ kiện &amp; vật tư</option>
    </select>
</x-admin.filter-bar>

<div class="admin-panel">

    <div class="table-responsive">

        <table class="admin-table align-middle mb-0">

            <thead>
                <tr>
                    <th>Ảnh</th>
                    <th>Danh mục</th>
                    <th>Slug</th>
                    <th>Sản phẩm</th>
                    <th>Trạng thái</th>
                    <th>Thứ tự</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>

            <tbody>

            @forelse($categories as $category)

                <tr>

                    <td>
                        @if($category->image)

                            <img
                                src="{{ asset('storage/' . $category->image) }}"
                                alt="{{ $category->name }}"
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
                            {{ $category->name }}
                        </div>

                        @if($category->description)
                            <small class="text-muted">
                                {{ Str::limit($category->description, 60) }}
                            </small>
                        @endif
                    </td>

                    <td>
                        <code>
                            {{ $category->slug }}
                        </code>
                    </td>

                    <td>
                        <span class="badge text-bg-light">
                            {{ $category->products_count }}
                        </span>
                    </td>

                    <td>

                        @if($category->is_active)

                            <span class="badge text-bg-success">
                                Đang hoạt động
                            </span>

                        @else

                            <span class="badge text-bg-secondary">
                                Tạm ẩn
                            </span>

                        @endif

                    </td>

                    <td>
                        {{ $category->sort_order }}
                    </td>

                    <td class="text-end">

                        <div class="d-inline-flex gap-2">

                            <a
                                href="{{ route('admin.categories.edit', $category) }}"
                                class="btn btn-sm btn-outline-secondary"
                            >
                                Sửa
                            </a>

                            <form
                                action="{{ route('admin.categories.destroy', $category) }}"
                                method="POST"
                                onsubmit="return confirm('Bạn có chắc muốn xóa danh mục này?');"
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

                    </td>

                </tr>

            @empty

                <tr>
                    <td
                        colspan="7"
                        class="text-center py-5 text-muted"
                    >
                        Chưa có danh mục nào.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    @if($categories->hasPages())

        <div class="p-3 border-top">
            {{ $categories->links() }}
        </div>

    @endif

</div>

@endsection