@props(['order'])

@php
    $canTra = $order->payment_method === \App\Enums\PaymentMethod::Momo
        && $order->payment_status === \App\Enums\PaymentStatus::Unpaid
        && ! $order->status->isFinal();
@endphp

{{--
    Chỉ hiện khi khách THẬT SỰ trả lại được. Bày một nút rồi báo lỗi sau
    khi bấm còn tệ hơn không có nút nào.

    Bấm vào KHÔNG tạo đơn mới — vẫn đơn cũ, chỉ thêm một lượt giao dịch.
--}}
@if($canTra)
    <div class="pay-again">
        <p class="pay-again__text">
            Đơn này chưa thanh toán. Bạn có thể trả lại bằng MoMo, đơn hàng vẫn giữ nguyên.
        </p>

        <a href="{{ route('shop.orders.momo.pay', $order) }}" class="btn btn-primary-brand">
            Thanh toán lại với MoMo
        </a>
    </div>
@endif
