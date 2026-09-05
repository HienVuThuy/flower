@props([
    'name' => 'password',
    'id' => null,
    'placeholder' => '',
    'autocomplete' => 'current-password',
    'required' => true,
    /*
     * Túi lỗi cần đọc. Xem giải thích ở component form-error: nhiều biểu mẫu
     * trên cùng trang có thể trùng tên ô, và nếu không tách túi thì ô của
     * biểu mẫu này bị tô viền đỏ vì lỗi của biểu mẫu kia.
     */
    'bag' => 'default',
])

@php $inputId = $id ?? $name; @endphp

{{--
    Ô mật khẩu kèm nút con mắt để xem nội dung đang gõ.

    VÌ SAO LÀ MỘT COMPONENT: có ba ô mật khẩu ở hai trang (đăng nhập, đăng
    ký, xác nhận mật khẩu). Chép tay ba lần thì sửa một chỗ quên hai chỗ.

    VÌ SAO NÚT LÀ <button type="button">: nằm trong <form>, nếu để mặc
    định thì nó là nút submit — bấm xem mật khẩu sẽ gửi luôn biểu mẫu.

    JS ở resources/js/password-toggle.js. Không có JS thì ô vẫn là ô mật
    khẩu bình thường và nút bị ẩn — không để lại nút bấm không làm gì.
--}}
<div class="password-field" data-password-field>

    <input
        type="password"
        name="{{ $name }}"
        id="{{ $inputId }}"
        class="form-control {{ $errors->getBag($bag)->has($name) ? 'is-invalid' : '' }}"
        placeholder="{{ $placeholder }}"
        autocomplete="{{ $autocomplete }}"
        @if($required) required @endif
        {{ $attributes }}
    >

    <button
        type="button"
        class="password-field__toggle"
        data-password-toggle
        hidden
        aria-controls="{{ $inputId }}"
        aria-pressed="false"
        aria-label="Hiện mật khẩu"
        title="Hiện mật khẩu"
    >
        <x-site.icon name="eye" class="password-field__icon" data-icon-show />
        <x-site.icon name="eye-slash" class="password-field__icon" data-icon-hide hidden />
    </button>

</div>
