{{-- ---------- Thông tin cá nhân ---------- --}}
<div class="surface-card p-4 mb-4">

    <h2 class="text-h4 mb-1">Thông tin cá nhân</h2>
    <p class="text-caption mb-4">
        Tên hiển thị trong đơn hàng và đánh giá của bạn.
    </p>

    <form action="{{ route('shop.profile.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="name" class="form-label">Họ tên</label>
            <input type="text"
                   name="name"
                   id="name"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $user->name) }}"
                   maxlength="255"
                   required>
            <x-form-error name="name" bag="profile" />
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email"
                   name="email"
                   id="email"
                   class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email', $user->email) }}"
                   maxlength="255"
                   autocomplete="email"
                   required>
            <div class="form-text">
                Đây cũng là tên đăng nhập của bạn.
            </div>
            <x-form-error name="email" bag="profile" />
        </div>

        <p class="form-text mt-0 mb-2">
            Hai ô dưới chỉ cần điền <strong>khi bạn đổi email</strong>.
        </p>

        <div class="mb-3">
            <label for="email_confirmation" class="form-label">
                Nhập lại email
            </label>
            <input type="email"
                   name="email_confirmation"
                   id="email_confirmation"
                   class="form-control"
                   value="{{ old('email_confirmation') }}"
                   maxlength="255"
                   autocomplete="off">
            <div class="form-text">
                Gõ nhầm email là mất luôn cả lối đăng nhập lẫn lối
                lấy lại mật khẩu, nên cần gõ đúng hai lần.
            </div>
        </div>

        <div class="mb-3">
            <label for="current_password" class="form-label">
                Mật khẩu hiện tại
            </label>
            <x-form.password-input
                name="current_password"
                id="current_password"
                placeholder="••••••••"
                autocomplete="current-password"
                :required="false"
                bag="profile"
            />
            <x-form-error name="current_password" bag="profile" />
        </div>

        <button type="submit" class="btn btn-primary-brand">
            Lưu thay đổi
        </button>
    </form>

</div>
