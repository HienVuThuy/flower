@props(['order'])

@php
    $trangThai = \App\Enums\ShippingStatus::tuGhn($order->shipping_status);
@endphp

{{--
    TÌNH TRẠNG GIAO HÀNG — cho khách, ở trang đơn của họ.

    KHÁC "Trạng thái đơn hàng" ở ngay bên cạnh, và phải khác:

        OrderStatus     cửa hàng đang làm gì với đơn (chờ xác nhận,
                        đang chuẩn bị, đã giao)
        ShippingStatus  KIỆN HÀNG đang ở đâu, theo lời đơn vị vận chuyển

    Trước đây khách chỉ thấy vế thứ nhất. Đơn "Đang giao" nằm im suốt ba
    ngày trông y hệt nhau ở ngày đầu và ngày thứ ba — không có gì cho
    biết hàng đã rời kho chưa, hay hôm nay có ai mang tới không. Người
    đợi hàng thì gọi điện hỏi cửa hàng, và cửa hàng cũng phải đi hỏi GHN.

    CHỈ HIỆN KHI ĐÃ CÓ VẬN ĐƠN. Chưa bàn giao thì không có gì để nói, và
    một khối "chưa có thông tin" chỉ làm khách tưởng đơn bị bỏ quên.
--}}

@if($order->ghn_order_code)
    <div class="shipping-status">

        <div class="shipping-status__head">
            <h3 class="shipping-status__title">Tình trạng giao hàng</h3>

            <span class="status-pill status-pill--{{ $trangThai?->badge() ?? 'warning' }}">
                {{-- Mã lạ (GHN thêm trạng thái mới) lùi về câu chung thay
                     vì để trống hay in nguyên chuỗi tiếng Anh. --}}
                {{ $trangThai?->label() ?? 'Đang vận chuyển' }}
            </span>
        </div>

        @if($trangThai)
            <p class="shipping-status__hint">{{ $trangThai->hint() }}</p>
        @endif

        <dl class="shipping-status__list">
            <div>
                <dt>Mã vận đơn</dt>
                <dd>
                    <span class="shipping-status__code">{{ $order->ghn_order_code }}</span>
                </dd>
            </div>

            <div>
                <dt>Đơn vị vận chuyển</dt>
                <dd>Giao Hàng Nhanh</dd>
            </div>
        </dl>

        {{--
            TRA CỨU TRÊN TRANG CỦA GHN.

            Trang này chỉ hiện trạng thái tại lần đồng bộ gần nhất; trang
            của GHN có đủ mốc thời gian từng chặng. Đưa khách sang đó là
            đưa họ tới nguồn thật, thay vì bắt họ tin một bản sao có thể
            cũ vài giờ.

            rel="noopener": trang mở bằng target="_blank" có thể chạm tới
            window.opener của trang này nếu không chặn.
        --}}
        <a href="https://donhang.ghn.vn/?order_code={{ urlencode($order->ghn_order_code) }}"
           class="btn btn-ghost btn-sm"
           target="_blank"
           rel="noopener noreferrer">
            Tra cứu trên Giao Hàng Nhanh
            <x-site.icon name="box-arrow-up-right" />
        </a>

    </div>
@endif
