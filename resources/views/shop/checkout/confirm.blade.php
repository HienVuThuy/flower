@extends('layouts.app')

@section('title', 'Thanh toán — Xác nhận')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <h1 class="text-h2 mb-3">Thanh toán</h1>

        <x-cart.steps :current="$step" />

        {{-- Cho khách biết đang mua ngay một món, giỏ hàng vẫn còn nguyên --}}
        @if($basket->source === 'direct')
            <p class="checkout-panel__note mb-3">
                <x-site.icon name="megaphone" />
                Bạn đang mua ngay sản phẩm này. Các món trong giỏ hàng vẫn được giữ nguyên.
            </p>
        @endif

        <div class="row g-4">

            <div class="col-lg-7">

                <div class="checkout-panel">

                    <div class="checkout-review">

                        <div class="checkout-review__head">
                            <h2 class="text-h4 mb-0">Người nhận</h2>
                            <a href="{{ route('shop.checkout.details') }}" class="btn btn-ghost btn-sm">Sửa</a>
                        </div>

                        <p class="checkout-review__body mb-0">
                            <strong>{{ $values['recipient_name'] }}</strong> — {{ $values['recipient_phone'] }}<br>
                            {{ collect([
                                $values['shipping_address'] ?? null,
                                $values['shipping_ward'] ?? null,
                                $values['shipping_district'] ?? null,
                                $values['shipping_province'] ?? null,
                            ])->filter()->implode(', ') }}
                            @if(! empty($values['recipient_email']))
                                <br>{{ $values['recipient_email'] }}
                            @endif
                        </p>

                    </div>

                    <div class="checkout-review">

                        <div class="checkout-review__head">
                            <h2 class="text-h4 mb-0">Giao hàng &amp; thanh toán</h2>
                            <a href="{{ route('shop.checkout.details') }}" class="btn btn-ghost btn-sm">Sửa</a>
                        </div>

                        <p class="checkout-review__body mb-0">
                            Ngày nhận:
                            {{ ! empty($values['delivery_date'])
                                ? \Carbon\Carbon::parse($values['delivery_date'])->format('d/m/Y')
                                : 'Sớm nhất có thể' }}<br>
                            Thanh toán:
                            {{ \App\Enums\PaymentMethod::from($values['payment_method'])->label() }}
                            @if(! empty($values['delivery_note']))
                                <br>Ghi chú: {{ $values['delivery_note'] }}
                            @endif
                        </p>

                        @if(! empty($lichTraGop))
                            <div class="table-responsive mt-3" data-lich-tra-gop>
                                <table class="table table-sm align-middle mb-1">
                                    <thead>
                                        <tr><th scope="col">Kỳ</th><th scope="col">Hạn trả</th><th scope="col" class="text-end">Số tiền</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($lichTraGop as $dong)
                                            <tr>
                                                <td>{{ $dong['sequence'] === 0 ? 'Trả trước' : 'Kỳ ' . $dong['sequence'] }}</td>
                                                <td>{{ \Carbon\Carbon::parse($dong['due_on'])->format('d/m/Y') }}</td>
                                                <td class="text-end"><x-site.money :amount="$dong['amount']" /></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-caption mb-0">
                                Cửa hàng giữ hàng và giao khi bạn trả đủ. Quá hạn một kỳ hơn
                                {{ \App\Services\Installment\InstallmentSettings::soNguyen('tra_gop.ngay_an_han') }} ngày thì đơn tự huỷ và tiền đã trả được hoàn lại.
                            </p>
                        @elseif(! empty($traGopLoi))
                            <p class="text-danger mt-2 mb-0" data-tra-gop-loi>{{ $traGopLoi }}</p>
                        @endif

                    </div>

                    <div class="checkout-review">

                        <h2 class="text-h4 mb-3">Sản phẩm</h2>

                        <ul class="checkout-items checkout-items--detailed">
                            @foreach($basket->lines as $line)
                                <li class="checkout-items__row">

                                    <span class="checkout-items__thumb">
                                        @if($line->product->main_image)
                                            <img src="{{ asset('storage/' . $line->product->main_image) }}"
                                                 alt="{{ $line->product->name }}" loading="lazy">
                                        @else
                                            <x-site.leaf-placeholder />
                                        @endif
                                    </span>

                                    <span class="checkout-items__info">
                                        <span class="checkout-items__name">{{ $line->product->name }}</span>

                                        @if($line->variant)
                                            <span class="checkout-items__meta">{{ $line->variant->name }}</span>
                                        @endif

                                        <span class="checkout-items__meta">
                                            <x-site.money :amount="(float) $line->unitPrice()" /> × {{ $line->quantity }}
                                        </span>

                                        @if($line->promotionName())
                                            <span class="checkout-items__promo">{{ $line->promotionName() }}</span>
                                        @endif
                                    </span>

                                    <span class="checkout-items__amount">
                                        <x-site.money :amount="(float) $line->lineTotal()" />
                                    </span>

                                </li>
                            @endforeach
                        </ul>

                    </div>

                    <form method="POST" action="{{ route('shop.checkout.place') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary-brand btn-lg w-100">
                            Đặt hàng
                        </button>
                    </form>

                </div>

            </div>

            <div class="col-lg-5">
                <x-cart.summary :basket="$basket" />
            </div>

        </div>

    </div>
</section>

@endsection
