@extends('layouts.admin')

@section('title', 'Thêm sản phẩm')

@section('content')

<div class="mb-4">

    <h1 class="admin-page-title">
        Thêm sản phẩm
    </h1>

    <p class="admin-page-subtitle">
        Tạo sản phẩm hoa hoặc cây cảnh mới.
    </p>

</div>

<form
    action="{{ route('admin.products.store') }}"
    method="POST"
    enctype="multipart/form-data"
>

    @csrf

    @include('admin.products._form')

    <div class="d-flex justify-content-end gap-2 mt-4">

        <a
            href="{{ route('admin.products.index') }}"
            class="btn btn-light px-4"
        >
            Hủy
        </a>

        <button
            type="submit"
            class="btn btn-primary-brand px-4"
        >
            Lưu sản phẩm
        </button>

    </div>

</form>

@endsection