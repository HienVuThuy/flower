@extends('layouts.admin')

@section('title', 'Sửa danh mục')

@section('content')

<div class="mb-4">

    <h1 class="admin-page-title">
        Chỉnh sửa danh mục
    </h1>

    <p class="admin-page-subtitle">
        Cập nhật thông tin danh mục "{{ $category->name }}".
    </p>

</div>

<form
    action="{{ route('admin.categories.update', $category) }}"
    method="POST"
    enctype="multipart/form-data"
>

    @csrf
    @method('PUT')

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
            Cập nhật
        </button>

    </div>

</form>

@endsection