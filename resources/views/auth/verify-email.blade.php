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

        {{--
            BÁO SAI MÃ Ở NGAY ĐÂY, không chỉ bằng dòng chữ nhỏ dưới ô nhập.

            Gõ sai là việc xảy ra thường xuyên nhất trên trang này, và thứ
            khách nhìn đầu tiên sau khi trang tải lại là phần trên cùng của
            thẻ. Một dòng chữ nhỏ nằm dưới ô nhập rất dễ bị bỏ qua — khách
            tưởng nút bấm không ăn và bấm lại, đốt thêm một lượt thử.

            KHÔNG data-auto-dismiss: đây là lỗi của biểu mẫu, phải nằm đó
            cho tới khi khách sửa xong. Thông báo tự biến mất là dành cho
            việc ĐÃ XONG, không phải việc ĐANG DANG DỞ.
        --}}
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

                {{--
                    inputmode="numeric" — điện thoại bật thẳng bàn phím số.
                    autocomplete="one-time-code" — iOS và Android tự điền mã
                    vừa nhận, khách không phải chuyển qua lại giữa hai ứng
                    dụng rồi gõ tay.
                    autofocus — con trỏ nằm sẵn ở đây, vì đây là việc DUY
                    NHẤT trang này yêu cầu.
                --}}
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

                {{-- Không lặp lại lỗi ở đây: đã báo bằng khối đỏ phía trên,
                     nói hai lần cùng một chuyện làm trang trông rối. Ô nhập
                     vẫn giữ viền đỏ (.is-invalid) để chỉ đúng chỗ cần sửa. --}}

                @if($expiresAt)
                    <p class="text-caption mt-2 mb-0 text-center">
                        Mã còn hiệu lực tới <x-site.time :at="$expiresAt" format="H:i" />.
                    </p>
                @endif
            </div>

            <button type="submit" class="btn btn-primary-brand w-100">Xác thực</button>

        </form>

        {{--
            GỬI LẠI MÃ.

            Nút bị khoá trong thời gian chờ và NÓI RÕ còn bao nhiêu giây.
            Để nút bấm được rồi mới báo lỗi là bắt khách thử mới biết —
            thà nói trước.
        --}}
        <form action="{{ route('verification.send') }}" method="POST">
            @csrf
            {{--
                data-resend-countdown — số giây còn lại, để JavaScript đếm
                ngược thay vì để con số đứng im tới lần tải trang sau.
                Xem resources/js/otp-resend.js.
            --}}
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

        {{--
            NHẮC KIỂM TRA MỤC SPAM.

            Thư tự động từ một tên miền chưa có SPF/DKIM riêng rất hay rơi
            vào Spam. Không nói ra thì khách ngồi đợi một lá thư đang nằm
            sẵn trong hộp của họ.
        --}}
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

        {{-- Đăng xuất phải là POST: GET đổi trạng thái thì trình duyệt và
             trình quét được phép tự gọi bất cứ lúc nào. --}}
        <form id="verify-logout" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>

    </div>

</div>

@endsection
