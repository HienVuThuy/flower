@props(['current' => 1])

@php
    $steps = [
        1 => 'Thông tin & thanh toán',
        2 => 'Xác nhận',
    ];
@endphp

<ol class="checkout-steps" aria-label="Tiến trình thanh toán">
    @foreach($steps as $number => $label)
        <li
            class="checkout-steps__item
                {{ $number === $current ? 'is-current' : '' }}
                {{ $number < $current ? 'is-done' : '' }}"
            @if($number === $current) aria-current="step" @endif
        >
            <span class="checkout-steps__number">
                @if($number < $current)
                    <x-site.icon name="check-circle" label="Đã hoàn thành" />
                @else
                    {{ $number }}
                @endif
            </span>
            <span class="checkout-steps__label">{{ $label }}</span>
        </li>
    @endforeach
</ol>
