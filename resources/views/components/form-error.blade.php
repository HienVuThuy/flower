@props(['name', 'bag' => 'default', 'array' => false])

{{-- Thông báo lỗi của MỘT ô nhập. --}}
@php
    $errorBag = $errors->getBag($bag);

    $key = $array
        ? collect($errorBag->keys())->first(
            fn (string $k) => $k === $name || str_starts_with($k, $name.'.')
        )
        : ($errorBag->has($name) ? $name : null);
@endphp

@if($key !== null)
    <div class="text-danger small mt-1">
        {{ $errorBag->first($key) }}
    </div>
@endif
