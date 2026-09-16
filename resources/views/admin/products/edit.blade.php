@extends('layouts.admin')

@section('title', 'Sửa sản phẩm')

@section('content')

<div class="mb-4">

    <h1 class="admin-page-title">
        Chỉnh sửa sản phẩm
    </h1>

    <p class="admin-page-subtitle">
        Cập nhật "{{ $product->name }}".
        @can('khuyen-mai')
            <a data-admin-link href="{{ route('admin.product-gifts.edit', $product) }}" data-lien-ket-qua>Quà tặng kèm của sản phẩm này</a>
        @endcan
    </p>

</div>

<form
    action="{{ route('admin.products.update', $product) }}"
    method="POST"
    enctype="multipart/form-data"
>

    @csrf
    @method('PUT')

    @include('admin.products._form')

    <div class="d-flex justify-content-end gap-2 mt-4">

        <a
            href="{{ route('admin.products.show', $product) }}"
            class="btn btn-light px-4"
        >
            Hủy
        </a>

        <button
            type="submit"
            class="btn btn-primary-brand px-4"
        >
            Cập nhật sản phẩm
        </button>

    </div>

</form>

@endsection