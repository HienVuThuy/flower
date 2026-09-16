@props(['order'])

@php
    $trangThai = \App\Enums\ShippingStatus::tuGhn($order->shipping_status);

    $traCuu = trim((string) config('services.ghn.tracking_url'));

    $tu = $order->ghn_expected_from;
    $den = $order->ghn_expected_to;
@endphp

@if($order->ghn_order_code)
    <div class="shipping-status">

        <div class="shipping-status__head">
            <h3 class="shipping-status__title">Tình trạng giao hàng</h3>

            <span class="status-pill status-pill--{{ $trangThai?->badge() ?? 'warning' }}">
                {{ $trangThai?->label() ?? 'Đang vận chuyển' }}
            </span>
        </div>

        @if($trangThai)
            <p class="shipping-status__hint">{{ $trangThai->hint() }}</p>
        @endif

        <dl class="shipping-status__list">

            @if($tu)
                <div>
                    <dt>Dự kiến giao</dt>
                    <dd>
                        @if($den && ! $den->isSameDay($tu))
                            <x-site.time :at="$tu" format="d/m" /> &ndash; <x-site.time :at="$den" format="d/m/Y" />
                        @else
                            <x-site.time :at="$tu" format="d/m/Y" />
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

        @if($traCuu !== '')
            <a href="{{ rtrim($traCuu, '/') }}/?order_code={{ urlencode($order->ghn_order_code) }}"
               class="btn btn-ghost btn-sm"
               target="_blank"
               rel="noopener noreferrer">
                Tra cứu trên Giao Hàng Nhanh
                <x-site.icon name="box-arrow-up-right" />
            </a>
        @else
            <p class="shipping-status__note">
                Trạng thái được cập nhật từ Giao Hàng Nhanh, khoảng 30 phút một lần.
                @if($hotline = \App\Services\Shop\StoreProfile::hotline())
                    Cần gấp thì liên hệ cửa hàng theo số {{ $hotline }}.
                @elseif($emailCuaHang = \App\Services\Shop\StoreProfile::email())
                    Cần gấp thì liên hệ cửa hàng qua {{ $emailCuaHang }}.
                @endif
            </p>
        @endif

    </div>
@endif
