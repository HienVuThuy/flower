@extends('layouts.app')

@section('title', 'Đơn hàng ' . $order->order_number)

@section('content')

<section class="section-sm">
    <div class="container-shop">

        @php
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

                <p class="order-success__note mb-0">
                    Đơn hàng vẫn giữ nguyên — trả lại không tạo đơn mới.
                </p>

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

            @if(! $daHuy && $order->recipient_email && app(\App\Services\Order\OrderMailer::class)->deliversForReal())
                <p class="order-success__note mb-0">
                    Khi cửa hàng xác nhận đơn, thư báo sẽ được gửi tới
                    {{ $order->recipient_email }}.
                </p>
            @endif

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
                            @foreach($order->items->where('is_gift', false) as $item)
                                <li class="checkout-items__row">
                                    <span>
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

                    <div class="mb-4">
                        <x-order.timeline :events="$order->statusEvents" />
                    </div>

                    <x-order.shipping-status :order="$order" />

                    @if($order->installmentPlan)
                        @include('shop.orders.partials.tra-gop')
                    @endif

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

                        <x-order.tax-lines :total="$order->tax_amount" :rows="$order->taxByRate()" />

                    </dl>

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
                                            &middot;
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

                    <x-order.invoice-card :invoice="$order->invoice" />

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
