@props(['order'])

@php
    $trangThai = \App\Enums\ShippingStatus::tuGhn($order->shipping_status);

    /*
     * ĐỂ TRỐNG Ở MÔI TRƯỜNG THỬ — xem config/services.php.
     *
     * Vận đơn tạo trên cổng thử KHÔNG tra được ở trang tra cứu của môi
     * trường thật: khách nhập đúng 4 số cuối vẫn nhận "Thông tin không
     * chính xác". Một đường dẫn luôn báo sai còn tệ hơn không có đường
     * dẫn — nó làm khách nghi ngờ chính đơn hàng của mình.
     */
    $traCuu = trim((string) config('services.ghn.tracking_url'));

    $tu = $order->ghn_expected_from;
    $den = $order->ghn_expected_to;
@endphp

{{--
    TÌNH TRẠNG GIAO HÀNG — cho khách, ở trang đơn của họ.

    KHÁC "Trạng thái đơn hàng" ở ngay bên cạnh, và phải khác:

        OrderStatus     cửa hàng đang làm gì với đơn (chờ xác nhận,
                        đang chuẩn bị, đã giao)
        ShippingStatus  KIỆN HÀNG đang ở đâu, theo lời đơn vị vận chuyển

    Trước đây khách chỉ thấy vế thứ nhất. Đơn "Đang giao" nằm im suốt ba
    ngày trông y hệt nhau ở ngày đầu và ngày thứ ba — không có gì cho
    biết hàng đã rời kho chưa, hay hôm nay có ai mang tới không.

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

            {{--
                DỰ KIẾN GIAO — con số khách hỏi nhiều nhất.

                Lấy từ `leadtime_order` của GHN, đồng bộ 30 phút một lần.
                In cả KHOẢNG chứ không một ngày: cam kết của bên vận
                chuyển là một khoảng, và rút nó thành một ngày là hứa
                chặt hơn thứ mình nhận được.

                Không có số liệu thì KHÔNG hiện dòng nào — thà không hứa
                còn hơn hứa một ngày tự nghĩ ra.
            --}}
            @if($tu)
                <div>
                    <dt>Dự kiến giao</dt>
                    <dd>
                        @if($den && ! $den->isSameDay($tu))
                            {{ $tu->format('d/m') }} &ndash; {{ $den->format('d/m/Y') }}
                        @else
                            {{ $tu->format('d/m/Y') }}
                        @endif
                    </dd>
                </div>
            @endif

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
            TRA CỨU TRÊN TRANG CỦA GHN — chỉ khi đã cấu hình được.

            Trang này chỉ hiện trạng thái tại lần đồng bộ gần nhất; trang
            của GHN có đủ mốc thời gian từng chặng. Nhưng nó chỉ tra được
            vận đơn của ĐÚNG môi trường mà nó phục vụ, nên ở môi trường
            thử thì không có nút nào.

            rel="noopener": trang mở bằng target="_blank" có thể chạm tới
            window.opener của trang này nếu không chặn.
        --}}
        @if($traCuu !== '')
            <a href="{{ rtrim($traCuu, '/') }}/?order_code={{ urlencode($order->ghn_order_code) }}"
               class="btn btn-ghost btn-sm"
               target="_blank"
               rel="noopener noreferrer">
                Tra cứu trên Giao Hàng Nhanh
                <x-site.icon name="box-arrow-up-right" />
            </a>
        @else
            {{--
                Không có nút thì phải nói rõ khách hỏi ai. Im lặng ở đây
                là để họ tự đi tìm số điện thoại cửa hàng.
            --}}
            <p class="shipping-status__note">
                Trạng thái được cập nhật từ Giao Hàng Nhanh, khoảng 30 phút một lần.
                Cần gấp thì liên hệ cửa hàng theo số {{ \App\Services\Shop\StoreProfile::hotline() }}.
            </p>
        @endif

    </div>
@endif
