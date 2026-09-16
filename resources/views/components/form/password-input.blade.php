@props([
    'name' => 'password',
    'id' => null,
    'placeholder' => '',
    'autocomplete' => 'current-password',
    'required' => true,
    'bag' => 'default',
])

@php $inputId = $id ?? $name; @endphp

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
