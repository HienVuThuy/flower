@extends('layouts.app')

@section('title', 'Đơn hàng ' . $order->order_number)

@section('content')

<section class="section-sm">
    <div class="container-shop">

        @php
            /*
             * TIÊU ĐỀ PHẢI NÓI ĐÚNG TÌNH TRẠNG THẬT CỦA ĐƠN.
             *
             * LỖI ĐÃ SỬA: khối này trước đây luôn là một dấu tích xanh kèm câu
             * "Đã nhận đơn hàng của bạn", cho MỌI đơn ở MỌI trạng thái.
             *
             * Đo được: khách bấm "Quay lại" ở trang MoMo để huỷ giao dịch, quay
             * về đây và thấy dấu tích xanh báo mọi thứ ổn. Dòng đỏ giải thích có
             * hiện, nhưng nó TỰ TẮT sau vài giây (xem resources/js/flash.js), nên
             * thứ còn lại trên màn hình là một lời báo thành công cho một lần
             * thanh toán vừa thất bại.
             *
             * Đơn đã huỷ cũng vậy: mở lại đơn của tháng trước vẫn thấy "Đã nhận
             * đơn hàng của bạn".
             */
            $daHuy = $order->status === \App\Enums\OrderStatus::Cancelled;

            $choTra = ! $daHuy
                && $order->payment_method->isOnline()
                && $order->payment_status === \App\Enums\PaymentStatus::Unpaid
                && ! $order->status->isFinal();
        @endphp

        <div class="order-success {{ $choTra ? 'order-success--cho-tra' : '' }} {{ $daHuy ? 'order-success--da-huy' : '' }}">

            @if($daHuy)
                <x-site.icon name="x-circle" class="order-success__icon" />
                <h1 class="text-h2 mb-2">Đơn hàng đã huỷ</h1>
                <p class="mb-0">
                    Mã đơn <strong>{{ $order->order_number }}</strong>.
                    Cửa hàng sẽ không giao đơn này nữa.
                </p>

            @elseif($choTra)
                <x-site.icon name="clock-history" class="order-success__icon" />
                <h1 class="text-h2 mb-2">Đơn hàng chưa thanh toán</h1>
                <p class="mb-0">
                    Mã đơn <strong>{{ $order->order_number }}</strong> đã được ghi nhận,
                    nhưng lần thanh toán {{ $order->payment_method->label() }} vừa rồi chưa hoàn tất.
                </p>

                {{--
                    NÚT ĐẶT NGAY ĐÂY, không để tận cuối trang.

                    Đây là chỗ mắt khách rơi vào đầu tiên khi quay lại từ cổng
                    thanh toán. Bắt họ cuộn xuống tìm nút là bắt họ đoán rằng có
                    một nút để tìm.
                --}}
                <p class="order-success__note mb-0">
                    Đơn hàng vẫn giữ nguyên — trả lại không tạo đơn mới.
                </p>

                {{--
                    MỘT NÚT CHO MỖI CÁCH TRẢ TIỀN.

                    Trước đây chỉ có một nút, và nó luôn mở dịch vụ mặc
                    định. Ai đang ngồi trước máy tính mà mặc định là
                    "quét QR" thì phải với lấy điện thoại; ai đang cầm
                    điện thoại mà mặc định là "nhập thẻ" thì phải đi tìm
                    cái thẻ. Lần trả lại là lúc lần trước đã hỏng — không
                    được bắt họ đoán tiếp.
                --}}
                <div class="momo-retry mt-3">
                    @foreach(\App\Enums\MomoFlow::cases() as $cach)
                        <a href="{{ route('shop.orders.momo.pay', [$order, 'cach' => $cach->value]) }}"
                           class="btn {{ $loop->first ? 'btn-primary-brand' : 'btn-secondary-brand' }}">
                            <x-site.icon :name="$cach->icon()" />
                            {{ $cach->label() }}
                        </a>
                    @endforeach
                </div>

            @else
                <x-site.icon name="check-circle" class="order-success__icon" />
                <h1 class="text-h2 mb-2">Đã nhận đơn hàng của bạn</h1>
                <p class="mb-0">
                    Mã đơn <strong>{{ $order->order_number }}</strong>.
                    Cửa hàng sẽ liên hệ số {{ $order->recipient_phone }} để xác nhận.
                </p>
            @endif

            {{--
                NÓI ĐÚNG THỜI ĐIỂM THƯ SẼ TỚI, không nói "đã gửi".

                Thư chỉ đi khi admin chuyển đơn sang "Đã xác nhận", nên câu chữ
                phải nói đúng vậy — nếu không, khách mở hộp thư tìm một lá thư
                chưa được gửi rồi kết luận hệ thống hỏng.

                Vẫn giữ deliversForReal(): dự án có thể đang chạy MAIL_MAILER=log,
                khi đó thư chỉ ghi vào tệp và hứa hẹn gì cũng là nói dối.

                Đơn đã huỷ thì không hứa thư nào cả.
            --}}
            @if(! $daHuy && $order->recipient_email && app(\App\Services\Order\OrderMailer::class)->deliversForReal())
                <p class="order-success__note mb-0">
                    Khi cửa hàng xác nhận đơn, thư báo sẽ được gửi tới
                    {{ $order->recipient_email }}.
                </p>
            @endif

            {{--
                Khách chưa đăng nhập không có trang "Đơn hàng của tôi". Đóng
                trình duyệt là hết phiên, và họ mất luôn đường vào đơn này.
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
                            {{--
                                HÀNG BÁN trước, QUÀ KÈM nằm ngay dưới món đã sinh ra nó
                                (parent_item_id), quà theo chương trình xuống cuối.
                            --}}
                            @foreach($order->items->where('is_gift', false) as $item)
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

                                @foreach($order->items->where('is_gift', true)->where('parent_item_id', $item->id) as $qua)
                                    @include('shop.orders.partials.dong-qua', ['qua' => $qua])
                                @endforeach
                            @endforeach

                            @foreach($order->items->where('is_gift', true)->whereNull('parent_item_id') as $qua)
                                @include('shop.orders.partials.dong-qua', ['qua' => $qua])
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

                    {{--
                        TÌNH TRẠNG GIAO HÀNG — đặt NGAY DƯỚI dòng thời gian.

                        Dòng thời gian nói cửa hàng đã làm gì; khối này nói
                        kiện hàng đang ở đâu. Hai câu hỏi khác nhau, và câu
                        thứ hai là câu người đang đợi hàng thật sự hỏi.
                    --}}
                    <x-order.shipping-status :order="$order" />

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

                        @if((float) $order->member_discount > 0)
                            <div class="order-summary__row" data-uu-dai-hang-don>
                                <dt>Ưu đãi hạng thành viên</dt>
                                <dd>&minus;<x-site.money :amount="(float) $order->member_discount" /></dd>
                            </div>
                        @endif

                        @if($order->points_used > 0)
                            <div class="order-summary__row" data-diem-don>
                                <dt>Điểm thưởng ({{ number_format($order->points_used, 0, ',', '.') }} điểm)</dt>
                                <dd>&minus;<x-site.money :amount="(float) $order->points_discount" /></dd>
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
                        TIỀN CỬA HÀNG ĐÃ TRẢ LẠI — khách phải thấy được.

                        Không có khối này thì một khách được hoàn một phần
                        vẫn chỉ thấy "Đã thanh toán", và không có cách nào
                        biết cửa hàng đã chuyển gì, lúc nào, qua đâu — nên
                        họ gọi điện hỏi.

                        CHỈ những lần ĐÃ HOÀN XONG và những lần đang chờ
                        MoMo trả lời. Lần không thành công là chuyện nội bộ:
                        báo cho khách "một lần hoàn thất bại" chỉ làm họ lo
                        về một khoản tiền chưa từng rời cửa hàng.
                    --}}
                    @php
                        $hoanHienThi = $order->refunds->filter(fn ($r) => $r->status !== \App\Enums\RefundStatus::Failed);
                    @endphp

                    @if($hoanHienThi->isNotEmpty())
                        <div class="order-refunds mb-3">
                            <h3 class="text-h5 mb-2">Tiền đã hoàn lại</h3>
                            <ul class="list-unstyled mb-0">
                                @foreach($hoanHienThi as $r)
                                    <li class="mb-1">
                                        <strong><x-site.money :amount="(string) $r->amount" /></strong>
                                        @if($r->status === \App\Enums\RefundStatus::Completed)
                                            &middot; {{-- "qua MoMo", không phải "về ví MoMo": khách trả bằng thẻ thì tiền về thẻ. --}}
                                            {{ $r->method === \App\Enums\RefundMethod::Momo ? 'qua MoMo' : ($r->method === \App\Enums\RefundMethod::Cash ? 'tiền mặt' : 'chuyển khoản') }}
                                            &middot; <x-site.time :at="$r->completed_at" format="d/m/Y" />
                                        @else
                                            &middot; đang xử lý qua MoMo
                                        @endif
                                        <span class="d-block small text-muted">Mã {{ $r->code }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

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
