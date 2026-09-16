@extends('layouts.app')

@section('title', 'Đăng nhập')

@section('content')

<div class="auth-shell">

    <div class="surface-card auth-card">

        <x-site.brand-mark class="auth-card__mark d-block" />

        <h1 class="text-h3 text-center mb-1">Đăng nhập</h1>
        <p class="text-body-sm text-muted text-center mb-4">Chào mừng bạn quay lại {{ \App\Services\Shop\StoreProfile::name() }}.</p>

        <form action="{{ route('login') }}" method="POST">

            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    class="form-control @error('email') is-invalid @enderror"
                    placeholder="ban@example.com"
                    autofocus
                    required
                >
                <x-form-error name="email" />
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu</label>
                <x-form.password-input
                    name="password"
                    placeholder="••••••••"
                    autocomplete="current-password"
                />
                <x-form-error name="password" />
            </div>

            <p class="text-caption text-end mb-3">
                <a href="{{ route('password.request') }}">Quên mật khẩu?</a>
            </p>

            <div class="form-check mb-4">
                <input type="checkbox" name="remember" id="remember" class="form-check-input" value="1">
                <label for="remember" class="form-check-label">Ghi nhớ đăng nhập</label>
            </div>

            <button type="submit" class="btn btn-primary-brand w-100">
                Đăng nhập
            </button>

        </form>

        <p class="text-center text-body-sm text-muted mt-4 mb-0">
            Chưa có tài khoản?
            <a href="{{ route('register') }}" class="fw-semibold text-accent">Đăng ký ngay</a>
        </p>

    </div>

</div>

@endsection
