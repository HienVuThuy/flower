@extends('layouts.app')

@section('title', 'Xác thực email')

@section('content')

<div class="auth-shell">

    <div class="surface-card auth-card">

        <x-site.brand-mark class="auth-card__mark d-block" />

        <h1 class="text-h3 text-center mb-1">Xác thực email</h1>

        <p class="text-body-sm text-muted text-center mb-4">
            Cửa hàng vừa gửi một mã gồm <strong>6 chữ số</strong> tới
            <strong>{{ $email }}</strong>.
        </p>

        @error('code')
            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                <x-site.icon name="x-circle" class="flex-shrink-0" />
                <span>{{ $message }}</span>
            </div>
        @enderror

        <form action="{{ route('verification.confirm') }}" method="POST" class="mb-3">

            @csrf

            <div class="mb-3">
                <label for="code" class="form-label">Mã xác thực</label>

                <input
                    type="text"
                    name="code"
                    id="code"
                    value="{{ old('code') }}"
                    class="form-control otp-input @error('code') is-invalid @enderror"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]*"
                    maxlength="6"
                    placeholder="000000"
                    autofocus
                    required
                >

                @if($expiresAt)
                    <p class="text-caption mt-2 mb-0 text-center">
                        Mã còn hiệu lực tới <x-site.time :at="$expiresAt" format="H:i" />.
                    </p>
                @endif
            </div>

            <button type="submit" class="btn btn-primary-brand w-100">Xác thực</button>

        </form>

        <form action="{{ route('verification.send') }}" method="POST">
            @csrf
            <button type="submit"
                    class="btn btn-ghost w-100"
                    data-resend-countdown="{{ $secondsUntilResend }}"
                    data-resend-label="Gửi lại mã"
                    @disabled($secondsUntilResend > 0)>
                @if($secondsUntilResend > 0)
                    Gửi lại mã sau {{ $secondsUntilResend }} giây
                @else
                    Gửi lại mã
                @endif
            </button>
        </form>

        <p class="text-caption text-center mt-4 mb-2">
            Chưa thấy thư? Kiểm tra thêm mục <strong>Spam</strong> hoặc
            <strong>Quảng cáo</strong>.
        </p>

        <p class="text-caption text-center mb-0">
            Gõ nhầm email lúc đăng ký?
            <a href="{{ route('shop.profile.edit') }}">Sửa trong hồ sơ</a>
            hoặc
            <button type="submit" form="verify-logout" class="btn-link-inline">đăng xuất</button>
            để đăng ký lại.
        </p>

        <form id="verify-logout" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>

    </div>

</div>

@endsection
