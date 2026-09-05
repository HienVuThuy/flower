@extends('layouts.app')

@section('title', 'Đăng ký')

@section('content')

<div class="auth-shell">

    <div class="surface-card auth-card">

        <x-site.brand-mark class="auth-card__mark d-block" />

        <h1 class="text-h3 text-center mb-1">Tạo tài khoản</h1>
        <p class="text-body-sm text-muted text-center mb-4">Tham gia {{ \App\Services\Shop\StoreProfile::name() }} chỉ trong vài giây.</p>

        <form action="{{ route('register') }}" method="POST">

            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">Họ và tên</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name') }}"
                    class="form-control @error('name') is-invalid @enderror"
                    placeholder="Nguyễn Văn A"
                    autofocus
                    required
                >
                <x-form-error name="name" />
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    class="form-control @error('email') is-invalid @enderror"
                    placeholder="ban@example.com"
                    required
                >
                <x-form-error name="email" />
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu</label>
                <x-form.password-input
                    name="password"
                    placeholder="Tối thiểu 8 ký tự, có hoa/thường/số"
                    autocomplete="new-password"
                />
                <x-form-error name="password" />
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Xác nhận mật khẩu</label>
                <x-form.password-input
                    name="password_confirmation"
                    placeholder="••••••••"
                    autocomplete="new-password"
                />
            </div>

            <button type="submit" class="btn btn-primary-brand w-100">
                Đăng ký
            </button>

        </form>

        <p class="text-center text-body-sm text-muted mt-4 mb-0">
            Đã có tài khoản?
            <a href="{{ route('login') }}" class="fw-semibold text-accent">Đăng nhập</a>
        </p>

    </div>

</div>

@endsection
