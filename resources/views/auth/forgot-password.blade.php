@extends('layouts.app')

@section('title', 'Quên mật khẩu')

@section('content')

<div class="auth-shell">

    <div class="surface-card auth-card">

        <div class="text-center">
            <div class="auth-card__mark d-flex align-items-center justify-content-center">
                <x-site.brand-mark :size="32" />
            </div>

            <h1 class="text-h3 mb-2">Quên mật khẩu</h1>

            <p class="text-body-sm text-muted mb-4">
                Nhập email bạn dùng để đăng ký. Chúng tôi sẽ gửi một liên kết
                để bạn chọn mật khẩu mới.
            </p>
        </div>

        @unless($mailWorks)
            <div class="alert alert-warning" role="alert">
                <strong>Máy chủ chưa cấu hình gửi thư.</strong>
                Liên kết đặt lại được ghi vào <code>storage/logs/laravel.log</code>
                thay vì gửi đi. Xem <code>.env.example</code> để bật SMTP.
            </div>
        @endunless

        <form action="{{ route('password.email') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    class="form-control @error('email') is-invalid @enderror"
                    value="{{ old('email') }}"
                    placeholder="ban@example.com"
                    autocomplete="email"
                    required
                    autofocus
                >
                <x-form-error name="email" />
            </div>

            <button type="submit" class="btn btn-primary-brand w-100">
                Gửi liên kết đặt lại
            </button>
        </form>

        <p class="text-caption text-center mt-4 mb-0">
            Nhớ ra mật khẩu rồi? <a href="{{ route('login') }}">Quay lại đăng nhập</a>
        </p>

    </div>

</div>

@endsection
