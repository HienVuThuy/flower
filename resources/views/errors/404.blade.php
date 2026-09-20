@extends('layouts.app')

@section('title', 'Không tìm thấy trang')

@section('content')

<div class="auth-shell">

    <div class="surface-card auth-card text-center">

        <div class="auth-card__mark d-flex align-items-center justify-content-center">
            <x-site.icon name="search" style="font-size: 2rem;" />
        </div>

        <h1 class="text-h3 mb-2">404 — Không tìm thấy trang</h1>

        <p class="text-body-sm text-muted mb-4">
            Địa chỉ này không có, hoặc sản phẩm / bài viết đã được gỡ. Thử tìm lại hoặc xem danh mục nhé.
        </p>

        <form method="GET" action="{{ route('shop.products.index') }}" class="d-flex gap-2 mb-4">
            <label class="visually-hidden" for="tim-404">Tìm sản phẩm</label>
            <input type="search" id="tim-404" name="q" class="form-control" placeholder="Tên cây, hoa…">
            <button type="submit" class="btn btn-secondary-brand text-nowrap">Tìm</button>
        </form>

        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="{{ route('welcome') }}" class="btn btn-primary-brand">Về trang chủ</a>
            <a href="{{ route('shop.categories.index') }}" class="btn btn-ghost">Xem danh mục</a>
        </div>

    </div>

</div>

@endsection
