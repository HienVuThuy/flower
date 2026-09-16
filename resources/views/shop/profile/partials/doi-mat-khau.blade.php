{{-- ---------- Đổi mật khẩu ---------- --}}
<div class="surface-card p-4">

    <h2 class="text-h4 mb-1">Đổi mật khẩu</h2>
    <p class="text-caption mb-4">
        Sau khi đổi, các thiết bị khác đang đăng nhập sẽ bị đăng xuất.
    </p>

    <form action="{{ route('shop.profile.password') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="pw_current" class="form-label">Mật khẩu hiện tại</label>
            <x-form.password-input
                name="current_password"
                id="pw_current"
                placeholder="••••••••"
                autocomplete="current-password"
                bag="password"
            />
            <x-form-error name="current_password" bag="password" />
        </div>

        <div class="mb-3">
            <label for="pw_new" class="form-label">Mật khẩu mới</label>
            <x-form.password-input
                name="password"
                id="pw_new"
                placeholder="Tối thiểu 8 ký tự, có hoa/thường/số"
                autocomplete="new-password"
                bag="password"
            />
            <x-form-error name="password" bag="password" />
        </div>

        <div class="mb-4">
            <label for="pw_confirm" class="form-label">Xác nhận mật khẩu mới</label>
            <x-form.password-input
                name="password_confirmation"
                id="pw_confirm"
                placeholder="••••••••"
                autocomplete="new-password"
                bag="password"
            />
        </div>

        <button type="submit" class="btn btn-primary-brand">
            Đổi mật khẩu
        </button>
    </form>

    <hr class="my-4">

    <p class="text-caption mb-2">
        Không nhớ mật khẩu hiện tại?
    </p>

    <form action="{{ route('shop.profile.password-link') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-ghost btn-sm">
            <x-site.icon name="envelope" />
            Gửi liên kết đặt lại về {{ $user->email }}
        </button>
    </form>

</div>
