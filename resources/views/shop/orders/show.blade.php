@extends('layouts.app')

@section('title', 'Đơn hàng ' . $order->order_number)

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <div class="order-success">
            <x-site.icon name="check-circle" class="order-success__icon" />
            <h1 class="text-h2 mb-2">Đã nhận đơn hàng của bạn</h1>
            <p class="mb-0">
                Mã đơn <strong>{{ $order->order_number }}</strong>.
                Cửa hàng sẽ liên hệ số {{ $order->recipient_phone }} để xác nhận.
            </p>

            {{--
                NÓI ĐÚNG THỜI ĐIỂM THƯ SẼ TỚI, không nói "đã gửi".

                Bản trước báo "Xác nhận đơn đã được gửi tới ..." ngay tại
                đây, trong khi đơn còn đang ở trạng thái "Chờ xác nhận" —
                chưa ai ở cửa hàng nhìn thấy nó. Nay thư chỉ đi khi admin
                chuyển đơn sang "Đã xác nhận", nên câu chữ phải đổi theo.

                Nếu không đổi, khách mở hộp thư tìm một lá thư chưa được
                gửi, rồi kết luận là hệ thống hỏng hoặc đơn không vào.

                Vẫn giữ phép kiểm deliversForReal(): dự án có thể đang
                chạy MAIL_MAILER=log, khi đó thư chỉ ghi vào tệp log và
                hứa hẹn gì cũng là nói dối.
            --}}
            @if($order->recipient_email && app(\App\Services\Order\OrderMailer::class)->deliversForReal())
                <p class="order-success__note mb-0">
                    Khi cửa hàng xác nhận đơn, thư báo sẽ được gửi tới
                    {{ $order->recipient_email }}.
                </p>
            @endif

            {{--
                Khách chưa đăng nhập không có trang "Đơn hàng của tôi". Đóng
                trình duyệt là hết phiên, và họ mất luôn đường vào đơn này.
                Chỉ cho họ cách quay lại trước khi điều đó xảy ra.
            --}}
            @guest
                <p class="order-success__note mb-0">
                    Hãy lưu lại mã đơn.
                    <a href="{{ route('shop.orders.lookup') }}">Tra cứu đơn hàng</a>
                    bất cứ lúc nào bằng mã đơn và số điện thoại.
                </p>
            @endguest
        </div>

        <div class="row g-4 mt-1">

            <div class="col-lg-7">

                <div class="checkout-panel">

                    <div class="checkout-review">
                        <h2 class="text-h4 mb-3">Sản phẩm</h2>

                        <ul class="checkout-items">
                            @foreach($order->items as $item)
                                <li class="checkout-items__row">
                                    <span>
                                        {{-- Đọc tên từ BẢN CHỤP trong đơn, không từ bảng products --}}
                                        {{ $item->product_name }}
                                        @if($item->variant_name)
                                            <span class="text-muted">({{ $item->variant_name }})</span>
                                        @endif
                                        <span class="text-muted">&times; {{ $item->quantity }}</span>

                                        @if($item->wasDiscounted() && $item->promotion_name)
                                            <span class="order-item__promo">{{ $item->promotion_name }}</span>
                                        @endif
                                    </span>
                                    <span><x-site.money :amount="(float) $item->line_total" /></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="checkout-review">
                        <h2 class="text-h4 mb-2">Giao tới</h2>
                        <p class="checkout-review__body mb-0">
                            <strong>{{ $order->recipient_name }}</strong> &mdash; {{ $order->recipient_phone }}<br>
                            {{ collect([
                                $order->shipping_address,
                                $order->shipping_ward,
                                $order->shipping_district,
                                $order->shipping_province,
                            ])->filter()->implode(', ') }}<br>
                            Ngày nhận:
                            {{ $order->delivery_date?->format('d/m/Y') ?? 'Sớm nhất có thể' }}<br>
                            Thanh toán: {{ $order->payment_method->label() }}
                            @if($order->delivery_note)
                                <br>Ghi chú: {{ $order->delivery_note }}
                            @endif
                        </p>
                    </div>

                </div>

            </div>

            <div class="col-lg-5">

                <div class="order-summary">

                    <h2 class="text-h4 order-summary__title">Trạng thái</h2>

                    <p class="mb-3">
                        <span class="status-pill status-pill--{{ $order->status->badge() }}">
                            {{ $order->status->label() }}
                        </span>
                        <span class="status-pill status-pill--{{ $order->payment_status->badge() }}">
                            {{ $order->payment_status->label() }}
                        </span>
                    </p>

                    {{--
                        DÒNG THỜI GIAN — đặt ngay dưới nhãn trạng thái,
                        TRÊN phần tiền.

                        "Đơn của tôi đang ở đâu" là câu hỏi khách mở
                        trang này để hỏi. Số tiền họ đã biết từ lúc đặt.
                        Đẩy lịch sử xuống dưới bảng tiền nghĩa là bắt họ
                        cuộn qua thứ đã biết để tới thứ đang cần.

                        Không truyền showActor: khách không cần biết tên
                        nhân viên nào bấm nút, với họ đó là "cửa hàng".
                    --}}
                    <div class="mb-4">
                        <x-order.timeline :events="$order->statusEvents" />
                    </div>

                    <dl class="order-summary__lines">

                        <div class="order-summary__row">
                            <dt>Tạm tính</dt>
                            <dd><x-site.money :amount="(float) $order->subtotal" /></dd>
                        </div>

                        @if((float) $order->discount_total > 0)
                            <div class="order-summary__row">
                                <dt>Giảm giá</dt>
                                <dd>&minus;<x-site.money :amount="(float) $order->discount_total" /></dd>
                            </div>
                        @endif

                        @if($order->coupon_code)
                            <div class="order-summary__row">
                                <dt>Mã {{ $order->coupon_code }}</dt>
                                <dd>&minus;<x-site.money :amount="(float) $order->coupon_discount" /></dd>
                            </div>
                        @endif

                        <div class="order-summary__row">
                            <dt>Phí giao hàng</dt>
                            <dd>
                                @if((float) $order->shipping_fee === 0.0)
                                    <span class="order-summary__free">Miễn phí</span>
                                @else
                                    <x-site.money :amount="(float) $order->shipping_fee" />
                                @endif
                            </dd>
                        </div>

                        <div class="order-summary__row order-summary__row--total">
                            <dt>Tổng cộng</dt>
                            <dd><x-site.money :amount="(float) $order->grand_total" /></dd>
                        </div>

                        {{--
                            THUẾ GTGT ĐÃ NẰM TRONG SỐ TIỀN TRÊN.

                            Trước đây khách không nhìn thấy con số này.
                            Nay hiện ra vì hoá đơn GTGT phải ghi giá chưa
                            thuế, thuế suất và tiền thuế — mà khách thì
                            cần đối chiếu được đơn của mình với hoá đơn
                            họ nhận. Giấu đi làm hai chứng từ có vẻ nói
                            hai chuyện khác nhau.

                            Đọc từ BẢN CHỤP trong đơn, không tính lại từ
                            cấu hình hiện tại: mức thuế có thể đã đổi từ
                            lúc đặt.
                        --}}
                        <x-order.tax-lines :total="$order->tax_amount" :rows="$order->taxByRate()" />

                    </dl>

                    {{--
                        HOÁ ĐƠN GTGT — chỉ hiện khi khách đã yêu cầu.

                        Không yêu cầu thì không hiện gì: một khối trống
                        ghi "chưa có hoá đơn" chỉ làm khách tưởng mình
                        thiếu một bước nào đó.
                    --}}
                    <x-order.invoice-card :invoice="$order->invoice" />

                    {{--
                        HUỶ ĐƠN.

                        Chỉ hiện khi khách thật sự huỷ được — không bày ra
                        một nút rồi báo lỗi sau khi bấm. Ở các trạng thái
                        muộn hơn thì hiện số điện thoại cửa hàng, vì lúc đó
                        chuyện phải thương lượng với người thật.
                    --}}
                    @if($order->isCancellableByCustomer())
                        <form method="POST"
                              action="{{ route('shop.orders.cancel', $order) }}"
                              class="order-cancel"
                              onsubmit="return confirm('Huỷ đơn {{ $order->order_number }}? Thao tác này không hoàn tác được.');">
                            @csrf

                            <label class="text-label d-block mb-2" for="reason">
                                Lý do huỷ <span class="text-muted text-lowercase">(không bắt buộc)</span>
                            </label>

                            <input type="text"
                                   id="reason"
                                   name="reason"
                                   maxlength="255"
                                   class="form-control mb-2 @error('reason') is-invalid @enderror"
                                   value="{{ old('reason') }}"
                                   placeholder="Ví dụ: đặt nhầm sản phẩm">
                            <x-form-error name="reason"/>

                            <button type="submit" class="btn btn-outline-danger w-100">
                                Huỷ đơn hàng
                            </button>
                        </form>
                    @elseif(! $order->status->isFinal())
                        <p class="text-caption mb-3">
                            Đơn đã qua bước tự huỷ.
                            @php $hotline = \App\Models\Setting::get('site_hotline'); @endphp
                            @if($hotline)
                                Gọi <strong>{{ $hotline }}</strong> nếu bạn cần thay đổi.
                            @else
                                Vui lòng liên hệ cửa hàng nếu bạn cần thay đổi.
                            @endif
                        </p>
                    @endif

                    <a href="{{ route('shop.products.index') }}" class="btn btn-ghost w-100">
                        Tiếp tục mua sắm
                    </a>

                    @auth
                        <a href="{{ route('shop.orders.index') }}" class="btn btn-ghost w-100 mt-2">
                            Xem tất cả đơn của tôi
                        </a>
                    @endauth

                </div>

            </div>

        </div>

    </div>
</section>

@endsection
