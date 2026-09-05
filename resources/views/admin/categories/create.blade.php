@extends('layouts.admin')

@section('title', 'Thêm danh mục')

@section('content')

<div class="mb-4">

    <h1 class="admin-page-title">
        Thêm danh mục
    </h1>

    <p class="admin-page-subtitle">
        Tạo một nhóm sản phẩm mới cho hệ thống.
    </p>

</div>

<form
    action="{{ route('admin.categories.store') }}"
    method="POST"
    enctype="multipart/form-data"
>

    @csrf

    @include('admin.categories._form')

    <div class="d-flex justify-content-end gap-2 mt-4">

        <a
            href="{{ route('admin.categories.index') }}"
            class="btn btn-light px-4"
        >
            Hủy
        </a>

        <button
            type="submit"
            class="btn btn-primary-brand px-4"
        >
            Lưu danh mục
        </button>

    </div>

</form>

@endsection