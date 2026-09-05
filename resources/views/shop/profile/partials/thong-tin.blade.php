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

        {{--
            NÓI ĐIỀU KIỆN MỘT LẦN CHO CẢ HAI Ô.

            Hai ô dưới đây (nhập lại email, mật khẩu hiện tại) cùng phục
            vụ đúng một việc: đổi email. Trước đây mỗi nhãn tự mang theo
            "(chỉ cần khi đổi email)", nên cùng một câu hiện hai lần cách
            nhau vài dòng — đọc lần thứ hai không hiểu là điều kiện khác
            hay chính điều kiện vừa đọc.

            Cả hai vẫn LUÔN HIỆN chứ không ẩn/hiện theo JavaScript: khách
            biết trước sẽ phải nhập gì, thay vì bấm Lưu rồi mới bị chặn.
            Việc có BẮT BUỘC hay không do máy chủ quyết định
            (ProfileRequest), không do giao diện.
        --}}
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

        {{-- Điều kiện đã nói ở dòng ghi chú phía trên hai ô này. --}}
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
            {{--
                Thiếu dòng này thì nhập sai mật khẩu là
                biểu mẫu quay về y nguyên, KHÔNG một chữ
                giải thích — người dùng bấm Lưu mãi mà
                không hiểu vì sao không có gì xảy ra.
                x-form.password-input không tự in lỗi.
            --}}
            <x-form-error name="current_password" bag="profile" />
        </div>

        <button type="submit" class="btn btn-primary-brand">
            Lưu thay đổi
        </button>
    </form>

</div>
