@props(['current' => 1])

@php
    /*
     * HAI BƯỚC, gộp từ ba.
     *
     * "Người nhận" và "Giao hàng & thanh toán" nay là một: phí giao phụ
     * thuộc tỉnh nhận hàng, nên tách hai màn hình thì khách điền xong màn
     * đầu vẫn chưa biết tổng tiền. Xem CheckoutController.
     */
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
                    {{-- Bước đã qua hiện dấu tích thay vì con số: nhìn một
                         cái là biết đã xong, không phải đọc rồi so sánh. --}}
                    <x-site.icon name="check-circle" label="Đã hoàn thành" />
                @else
                    {{ $number }}
                @endif
            </span>
            <span class="checkout-steps__label">{{ $label }}</span>
        </li>
    @endforeach
</ol>
