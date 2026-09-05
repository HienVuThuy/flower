@extends('layouts.app')

@section('title', 'Đặt lại mật khẩu')

@section('content')

<div class="auth-shell">

    <div class="surface-card auth-card">

        <div class="text-center">
            <div class="auth-card__mark d-flex align-items-center justify-content-center">
                <x-site.brand-mark :size="32" />
            </div>

            <h1 class="text-h3 mb-2">Đặt mật khẩu mới</h1>

            <p class="text-body-sm text-muted mb-4">
                Chọn một mật khẩu mới cho tài khoản của bạn.
            </p>
        </div>

        <form action="{{ route('password.update') }}" method="POST">
            @csrf

            {{--
                token đi kèm biểu mẫu. Đây là ô ẩn nên SỬA ĐƯỢC — không
                sao: Password broker so mã băm của token với bản ghi
                trong cơ sở dữ liệu, token bịa ra không khớp được.
            --}}
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    class="form-control @error('email') is-invalid @enderror"
                    value="{{ old('email', $email) }}"
                    autocomplete="email"
                    required
                >
                <x-form-error name="email" />
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu mới</label>
                {{-- Dùng lại component có nút con mắt — không chép lại markup. --}}
                <x-form.password-input
                    name="password"
                    placeholder="Tối thiểu 8 ký tự, có hoa/thường/số"
                    autocomplete="new-password"
                />
                {{-- x-form.password-input chỉ tô viền đỏ, KHÔNG in lý do.
                     Thiếu dòng này thì mật khẩu quá yếu chỉ khiến trang
                     nạp lại trống trơn — người dùng thử đi thử lại cùng
                     một mật khẩu vì không biết mình sai ở đâu. --}}
                <x-form-error name="password" />
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Xác nhận mật khẩu mới</label>
                <x-form.password-input
                    name="password_confirmation"
                    placeholder="••••••••"
                    autocomplete="new-password"
                />
            </div>

            <button type="submit" class="btn btn-primary-brand w-100">
                Đặt lại mật khẩu
            </button>
        </form>

        <p class="text-caption text-center mt-4 mb-0">
            <a href="{{ route('login') }}">Quay lại đăng nhập</a>
        </p>

    </div>

</div>

@endsection
