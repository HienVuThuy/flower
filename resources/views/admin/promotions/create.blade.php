@extends('layouts.admin')

@section('title', 'Tạo chương trình khuyến mại')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Tạo chương trình khuyến mại</h1>
    <p class="admin-page-subtitle">
        Lưu chương trình xong sẽ tới bước chọn sản phẩm áp dụng.
    </p>
</div>

<form action="{{ route('admin.promotions.store') }}" method="POST" enctype="multipart/form-data">

    @csrf

    @include('admin.promotions._form')

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="{{ route('admin.promotions.index') }}" class="btn btn-light px-4">Hủy</a>
        <button type="submit" class="btn btn-primary-brand px-4">Tạo &amp; chọn sản phẩm</button>
    </div>

</form>

@endsection
