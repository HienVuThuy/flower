@extends('layouts.app')

@section('title', 'Trang đang gặp sự cố')

@section('content')

<div class="auth-shell">

    <div class="surface-card auth-card text-center">

        <div class="auth-card__mark d-flex align-items-center justify-content-center" style="color: var(--semantic-danger);">
            <x-site.icon name="info-circle" style="font-size: 2rem;" />
        </div>

        <h1 class="text-h3 mb-2">500 — Trang đang gặp sự cố</h1>

        <p class="text-body-sm text-muted mb-4">
            Lỗi nằm ở phía cửa hàng, không phải ở bạn. Đơn hàng và giỏ hàng vẫn còn nguyên.
            Thử tải lại sau ít phút; nếu vẫn lỗi, gọi giúp cửa hàng
            @if(\App\Services\Shop\StoreProfile::hotline())
                theo số <strong>{{ \App\Services\Shop\StoreProfile::hotline() }}</strong>.
            @else
                qua trang Liên hệ.
            @endif
        </p>

        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="{{ url()->current() }}" class="btn btn-primary-brand">Tải lại trang</a>
            <a href="{{ route('welcome') }}" class="btn btn-ghost">Về trang chủ</a>
        </div>

    </div>

</div>

@endsection
