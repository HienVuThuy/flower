@extends('layouts.app')

@section('title', 'Thanh toán')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <h1 class="text-h2 mb-3">Thanh toán</h1>

        <x-cart.steps :current="$step" />

        <div class="row g-4 checkout-layout">

            <div class="col-lg-7">

                <form id="checkout-details-form" method="POST" action="{{ route('shop.checkout.store-details') }}" class="checkout-form">
                    @csrf

                    <section class="checkout-step">
                        <h2 class="checkout-step__title">
                            <span class="checkout-step__num">1</span>
                            Giao đến đâu
                        </h2>

                        <div class="checkout-step__body">
                    @if($addresses->isNotEmpty())
                        <p class="checkout-step__lead">Chọn một địa chỉ đã lưu, hoặc nhập địa chỉ mới bên dưới.</p>

                        <div class="address-picker" data-address-picker>

                            @foreach($addresses as $address)
                                <div class="address-option-row">
                                    <label class="address-option">
                                        <input
                                            type="radio"
                                            name="address_id"
                                            value="{{ $address->id }}"
                                            class="visually-hidden"
                                            @checked((int) old('address_id', $selectedAddressId) === $address->id)
                                        >
                                        <span class="address-option__body">
                                            <span class="address-option__head">
                                                <strong>{{ $address->recipient_name }}</strong>
                                                <span class="text-muted">{{ $address->recipient_phone }}</span>
                                                <span class="address-option__tag">{{ $address->label->label() }}</span>
                                                @if($address->is_default)
                                                    <span class="address-option__tag address-option__tag--default">Mặc định</span>
                                                @endif
                                            </span>
                                            <span class="address-option__line">{{ $address->fullAddress() }}</span>
                                        </span>
                                    </label>

                                    <button
                                        type="submit"
                                        form="xoa-dia-chi-{{ $address->id }}"
                                        class="address-option__remove"
                                        aria-label="Xoá địa chỉ của {{ $address->recipient_name }}"
                                        title="Xoá địa chỉ này khỏi sổ"
                                    >&times;</button>
                                </div>
                            @endforeach

                            <label class="address-option">
                                <input type="radio" name="address_id" value="" class="visually-hidden"
                                       @checked(old('address_id', $selectedAddressId) === '' || old('address_id') === '')>
                                <span class="address-option__body">
                                    <span class="address-option__head"><strong>Dùng địa chỉ khác</strong></span>
                                    <span class="address-option__line">Nhập địa chỉ mới cho đơn này</span>
                                </span>
                            </label>

                        </div>

                        <p class="mb-4">
                            <a href="{{ route('shop.addresses.index') }}" class="btn btn-ghost btn-sm">
                                Quản lý sổ địa chỉ
                            </a>
                        </p>
                    @endif

                    <div data-address-fields>


<div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label" for="recipient_name">Tên người nhận *</label>
                            <input type="text" id="recipient_name" name="recipient_name"
                                   value="{{ old('recipient_name', $values['recipient_name'] ?? '') }}"
                                   class="form-control @error('recipient_name') is-invalid @enderror" required>
                            <x-form-error name="recipient_name" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="recipient_phone">Số điện thoại *</label>
                            <input type="tel" id="recipient_phone" name="recipient_phone"
                                   value="{{ old('recipient_phone', $values['recipient_phone'] ?? '') }}"
                                   class="form-control @error('recipient_phone') is-invalid @enderror"
                                   placeholder="0901234567" required>
                            <x-form-error name="recipient_phone" />
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="recipient_email">Email (không bắt buộc)</label>
                            <input type="email" id="recipient_email" name="recipient_email"
                                   value="{{ old('recipient_email', $values['recipient_email'] ?? '') }}"
                                   class="form-control @error('recipient_email') is-invalid @enderror">
                            <div class="form-text">Dùng để gửi xác nhận đơn hàng.</div>
                            <x-form-error name="recipient_email" />
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="shipping_address">Địa chỉ *</label>
                            <input type="text" id="shipping_address" name="shipping_address"
                                   value="{{ old('shipping_address', $values['shipping_address'] ?? '') }}"
                                   class="form-control @error('shipping_address') is-invalid @enderror"
                                   placeholder="Số nhà, tên đường" required>
                            <x-form-error name="shipping_address" />
                        </div>

                        <div class="col-md-6 col-xl-4 ghn-select" hidden>
                            <label class="form-label" for="province_select">Tỉnh/Thành phố *</label>
                            <select id="province_select" class="form-select"
                                    data-ghn-province
                                    data-url-provinces="{{ route('locations.provinces') }}"
                                    data-url-districts="{{ route('locations.districts', ['provinceId' => '__ID__']) }}"
                                    data-url-wards="{{ route('locations.wards', ['districtId' => '__ID__']) }}"
                                    data-url-fee="{{ route('locations.fee') }}"
                                    data-token="{{ csrf_token() }}">
                                <option value="">-- Đang tải... --</option>
                            </select>
                            <input type="hidden" id="shipping_province" name="shipping_province"
                                   value="{{ old('shipping_province', $values['shipping_province'] ?? '') }}">
                            <x-form-error name="shipping_province" />
                        </div>

                        <div class="col-md-6 col-xl-4 ghn-select" hidden>
                            <label class="form-label" for="district_select">Quận/Huyện *</label>
                            <select id="district_select" class="form-select" data-ghn-district disabled>
                                <option value="">— Chọn tỉnh trước —</option>
                            </select>
                            <input type="hidden" id="shipping_district" name="shipping_district"
                                   data-cu="{{ old('shipping_district', $values['shipping_district'] ?? '') }}"
                                   value="{{ old('shipping_district', $values['shipping_district'] ?? '') }}">
                            <input type="hidden" name="to_district_id" data-ghn-district-id
                                   value="{{ old('to_district_id', $values['to_district_id'] ?? '') }}">
                        </div>

                        <div class="col-md-6 col-xl-4 ghn-select" hidden>
                            <label class="form-label" for="ward_select">Phường/Xã *</label>
                            <select id="ward_select" class="form-select" data-ghn-ward disabled>
                                <option value="">— Chọn quận trước —</option>
                            </select>
                            <input type="hidden" id="shipping_ward" name="shipping_ward"
                                   data-cu="{{ old('shipping_ward', $values['shipping_ward'] ?? '') }}"
                                   value="{{ old('shipping_ward', $values['shipping_ward'] ?? '') }}">
                            <input type="hidden" name="to_ward_code" data-ghn-ward-code
                                   value="{{ old('to_ward_code', $values['to_ward_code'] ?? '') }}">
                        </div>

                    </div>

                    <div class="ghn-fallback mt-3" data-ghn-fallback>
                        <p class="text-caption mb-2">
                            Không tải được danh sách địa chỉ. Bạn có thể nhập tay —
                            phí giao sẽ tính theo bảng phí vùng.
                        </p>

                        <div class="row g-3">
                            <div class="col-md-6 col-xl-4">
                                <label class="form-label" for="province_manual">Tỉnh/Thành phố *</label>
                                <x-form.province-select
                                    name="shipping_province"
                                    id="province_manual"
                                    :selected="$values['shipping_province'] ?? ''" />
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <label class="form-label" for="district_manual">Quận/Huyện</label>
                                <input type="text" id="district_manual" name="shipping_district"
                                       value="{{ old('shipping_district', $values['shipping_district'] ?? '') }}"
                                       class="form-control">
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <label class="form-label" for="ward_manual">Phường/Xã</label>
                                <input type="text" id="ward_manual" name="shipping_ward"
                                       value="{{ old('shipping_ward', $values['shipping_ward'] ?? '') }}"
                                       class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="ghn-fee mt-3" data-ghn-fee-box hidden>
                        <x-site.icon name="geo-alt" />
                        <span>Phí giao hàng dự kiến:</span>
                        <strong data-ghn-fee-text>—</strong>
                        <span class="text-caption" data-ghn-fee-note></span>
                    </div>

                    @auth
                        <div class="form-check mt-3">
                            <input type="checkbox" name="save_address" value="1" id="save_address"
                                   class="form-check-input" @checked(old('save_address'))>
                            <label class="form-check-label" for="save_address">
                                Lưu địa chỉ này vào sổ để lần sau không phải nhập lại
                            </label>
                        </div>
                    @endauth

                    </div>



                        </div>
                    </section>

                    <section class="checkout-step">
                        <h2 class="checkout-step__title">
                            <span class="checkout-step__num">2</span>
                            Giao khi nào
                            <span class="checkout-step__optional">không bắt buộc</span>
                        </h2>

                        <div class="checkout-step__body">
<div class="row g-3 mb-4">

                        <div class="col-md-6">
                            <label class="form-label" for="delivery_date">Ngày muốn nhận</label>
                            <input type="date" id="delivery_date" name="delivery_date"
                                   value="{{ old('delivery_date', $values['delivery_date'] ?? '') }}"
                                   min="{{ now()->toDateString() }}"
                                   class="form-control @error('delivery_date') is-invalid @enderror">
                            <div class="form-text">Để trống nếu bạn muốn nhận sớm nhất có thể.</div>
                            <x-form-error name="delivery_date" />
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="delivery_note">Ghi chú cho cửa hàng</label>
                            <textarea id="delivery_note" name="delivery_note" rows="3"
                                      class="form-control @error('delivery_note') is-invalid @enderror"
                                      placeholder="Ví dụ: gọi trước khi giao, ghi thiệp chúc mừng sinh nhật...">{{ old('delivery_note', $values['delivery_note'] ?? '') }}</textarea>
                            <x-form-error name="delivery_note" />
                        </div>

                    </div>


                        </div>
                    </section>

                    <section class="checkout-step">
                        <h2 class="checkout-step__title">
                            <span class="checkout-step__num">3</span>
                            Thanh toán thế nào
                        </h2>

                        <div class="checkout-step__body">
<div class="payment-options">
                        @foreach($paymentMethods as $method)
                            <label class="payment-option {{ $method === \App\Enums\PaymentMethod::TraGop && ! $traGop['duoc'] ? 'payment-option--disabled' : '' }}"
                                   @if($method === \App\Enums\PaymentMethod::TraGop) data-tra-gop="{{ $traGop['duoc'] ? 'duoc' : 'khong' }}" @endif>
                                <input type="radio" name="payment_method" value="{{ $method->value }}"
                                       @checked(old('payment_method', $values['payment_method'] ?? 'cod') === $method->value)
                                       @disabled($method === \App\Enums\PaymentMethod::TraGop && ! $traGop['duoc'])>
                                <span class="payment-option__body">
                                    <span class="payment-option__name">{{ $method->label() }}</span>
                                    <span class="payment-option__hint">{{ $method->hint() }}</span>

                                    @if($method === \App\Enums\PaymentMethod::TraGop)
                                        @if($traGop['duoc'])
                                            <span class="tra-gop-chi-tiet d-block" data-tra-gop-chi-tiet>
                                                <span class="payment-option__hint d-block mt-1" data-tra-gop-muc>
                                                    Điểm tín dụng {{ $traGop['diem'] }}: trả trước {{ $traGop['tra_truoc'] }}%,
                                                    tối đa {{ $traGop['ky_toi_da'] }} kỳ, mỗi kỳ {{ \App\Services\Installment\InstallmentSettings::soNguyen('tra_gop.so_ngay_moi_ky') }} ngày.
                                                </span>
                                                <span class="d-flex align-items-center gap-2 mt-2">
                                                    <span class="text-caption">Số kỳ</span>
                                                    <select name="so_ky" aria-label="Số kỳ trả góp"
                                                            class="form-select form-select-sm w-auto @error('so_ky') is-invalid @enderror">
                                                        @for($k = 1; $k <= $traGop['ky_toi_da']; $k++)
                                                            <option value="{{ $k }}" @selected((int) old('so_ky', $values['so_ky'] ?? $traGop['ky_toi_da']) === $k)>{{ $k }} kỳ</option>
                                                        @endfor
                                                    </select>
                                                </span>
                                            </span>
                                        @else
                                            <span class="payment-option__hint d-block mt-1" data-tra-gop-ly-do>{{ $traGop['ly_do'] }}</span>
                                        @endif
                                    @endif
                                </span>
                            </label>
                        @endforeach
                        <x-form-error name="so_ky" />
                    </div>

                    <x-form-error name="payment_method" />

                    @if(in_array('momo', \App\Enums\PaymentMethod::values(), true))
                        <div class="momo-flow">
                            <span class="form-label d-block">Trả bằng cách nào</span>

                            @foreach(\App\Enums\MomoFlow::cases() as $cach)
                                <label class="payment-option payment-option--sub">
                                    <input type="radio" name="momo_flow" value="{{ $cach->value }}"
                                           @checked(old('momo_flow', $values['momo_flow'] ?? \App\Enums\MomoFlow::macDinh()->value) === $cach->value)>
                                    <span class="payment-option__body">
                                        <span class="payment-option__name">{{ $cach->label() }}</span>
                                        <span class="payment-option__hint">{{ $cach->hint() }}</span>
                                    </span>
                                </label>
                            @endforeach

                            <x-form-error name="momo_flow" />
                        </div>
                    @endif


                        </div>
                    </section>

                    <section class="checkout-step">
                        <h2 class="checkout-step__title">
                            <span class="checkout-step__num">4</span>
                            Hoá đơn GTGT
                        </h2>

                        <div class="checkout-step__body">

                            <label class="invoice-toggle">
                                <input type="checkbox" name="want_invoice" value="1"
                                       @checked(old('want_invoice', $values['want_invoice'] ?? false))>
                                <span class="invoice-toggle__body">
                                    <span class="invoice-toggle__name">Tôi cần xuất hoá đơn GTGT</span>
                                    <span class="invoice-toggle__hint">
                                        Giá đã bao gồm VAT nên tổng tiền không đổi.
                                        Hoá đơn được gửi tới email bạn điền bên dưới.
                                    </span>
                                </span>
                            </label>

                            <div class="invoice-fields">

                                <div class="mb-3">
                                    <span class="form-label d-block">Xuất cho</span>

                                    @foreach(\App\Enums\InvoiceBuyerType::cases() as $loai)
                                        <label class="invoice-buyer">
                                            <input type="radio" name="invoice_buyer_type" value="{{ $loai->value }}"
                                                   @checked(old('invoice_buyer_type', $values['invoice_buyer_type'] ?? 'personal') === $loai->value)>
                                            <span class="invoice-buyer__body">
                                                <span class="invoice-buyer__name">{{ $loai->label() }}</span>
                                                <span class="invoice-buyer__hint">{{ $loai->hint() }}</span>
                                            </span>
                                        </label>
                                    @endforeach

                                    <x-form-error name="invoice_buyer_type" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="invoice_buyer_name">Tên trên hoá đơn</label>
                                    <input type="text" id="invoice_buyer_name" name="invoice_buyer_name" maxlength="200"
                                           class="form-control @error('invoice_buyer_name') is-invalid @enderror"
                                           value="{{ old('invoice_buyer_name', $values['invoice_buyer_name'] ?? '') }}"
                                           placeholder="Họ tên của bạn, hoặc tên công ty theo đăng ký">
                                    <x-form-error name="invoice_buyer_name" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="invoice_tax_code">
                                        Mã số thuế
                                        <span class="text-muted small">— bắt buộc với công ty / tổ chức</span>
                                    </label>
                                    <input type="text" id="invoice_tax_code" name="invoice_tax_code" maxlength="20"
                                           inputmode="numeric"
                                           class="form-control @error('invoice_tax_code') is-invalid @enderror"
                                           value="{{ old('invoice_tax_code', $values['invoice_tax_code'] ?? '') }}"
                                           placeholder="0101234567 hoặc 0101234567-001">
                                    <x-form-error name="invoice_tax_code" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="invoice_address">Địa chỉ trên hoá đơn</label>
                                    <input type="text" id="invoice_address" name="invoice_address" maxlength="300"
                                           class="form-control @error('invoice_address') is-invalid @enderror"
                                           value="{{ old('invoice_address', $values['invoice_address'] ?? '') }}"
                                           placeholder="Địa chỉ đăng ký kinh doanh">
                                    <x-form-error name="invoice_address" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="invoice_email">Email nhận hoá đơn</label>
                                    <input type="email" id="invoice_email" name="invoice_email" maxlength="255"
                                           class="form-control @error('invoice_email') is-invalid @enderror"
                                           value="{{ old('invoice_email', $values['invoice_email'] ?? '') }}"
                                           placeholder="ketoan@congty.vn">
                                    <div class="form-text">Hoá đơn điện tử sẽ được gửi tới địa chỉ này.</div>
                                    <x-form-error name="invoice_email" />
                                </div>

                                <p class="invoice-fields__note">
                                    Cửa hàng ghi nhận yêu cầu ngay khi bạn đặt hàng.
                                    Hoá đơn điện tử được phát hành sau đó và gửi tới email trên.
                                </p>

                            </div>

                        </div>
                    </section>

                    <div class="checkout-panel__actions">
                        <a href="{{ route('shop.cart.index') }}" class="btn btn-ghost">Về giỏ hàng</a>
                        <button type="submit" class="btn btn-primary-brand">Xem lại đơn hàng</button>
                    </div>
                </form>

                @foreach($addresses as $address)
                    <form
                        id="xoa-dia-chi-{{ $address->id }}"
                        method="POST"
                        action="{{ route('shop.addresses.destroy', $address) }}"
                        onsubmit="return confirm('Xoá địa chỉ này khỏi sổ? Đơn đã đặt không bị ảnh hưởng.');"
                        hidden
                    >
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach

            </div>

            <div class="col-lg-5">

                <div class="checkout-aside">

                    <div class="checkout-coupon">
                        <h2 class="checkout-coupon__title">
                            <x-site.icon name="tags" class="checkout-coupon__icon" />
                            Mã giảm giá
                        </h2>

                        @if($basket->coupon)
                            <div class="coupon-box__applied">
                                <span class="coupon-box__applied-code">
                                    <strong>{{ $basket->coupon->code }}</strong>
                                    <span class="text-muted">{{ $basket->coupon->name }}</span>
                                </span>

                                <button type="submit"
                                        form="checkout-details-form"
                                        formaction="{{ route('shop.checkout.remove-coupon') }}"
                                        formnovalidate
                                        name="_method" value="DELETE"
                                        class="btn btn-ghost btn-sm">Bỏ mã</button>
                            </div>
                        @endif

                        <div class="coupon-box__input">
                            <label class="visually-hidden" for="coupon_code">Nhập mã giảm giá</label>
                            <input type="text" id="coupon_code" name="coupon_code"
                                   form="checkout-details-form"
                                   class="form-control @error('coupon_code') is-invalid @enderror"
                                   placeholder="{{ $basket->coupon ? 'Đổi sang mã khác' : 'Nhập mã giảm giá' }}"
                                   maxlength="32" autocomplete="off"
                                   value="{{ old('coupon_code') }}">

                            <button type="submit"
                                    form="checkout-details-form"
                                    formaction="{{ route('shop.checkout.apply-coupon') }}"
                                    formnovalidate
                                    class="btn btn-secondary-brand">Áp dụng</button>
                        </div>

                        <x-form-error name="coupon_code" />

                        @auth
                            @if($couponChoices->isNotEmpty())
                                @php($usableCount = $couponChoices->whereNull('reason')->count())

                                <details class="coupon-picker">
                                    <summary class="coupon-picker__toggle">
                                        <span>Chọn mã trong ví</span>
                                        <span class="coupon-picker__count">{{ $usableCount }}/{{ $couponChoices->count() }} dùng được</span>
                                    </summary>

                                    <ul class="coupon-picker__list">
                                        @foreach($couponChoices as $row)
                                            @php($choice = $row['coupon'])
                                            @php($isCurrent = $basket->coupon?->code === $choice->code)

                                            <li class="coupon-picker__item @if($row['reason']) coupon-picker__item--off @endif">
                                                <button type="submit"
                                                        form="checkout-details-form"
                                                        formaction="{{ route('shop.checkout.apply-coupon') }}"
                                                        formnovalidate
                                                        name="wallet_code" value="{{ $choice->code }}"
                                                        class="coupon-picker__pick"
                                                        @disabled($row['reason'] !== null || $isCurrent)>
                                                    <span class="coupon-picker__code">{{ $choice->code }}</span>
                                                    <span class="coupon-picker__name">{{ $choice->name }}</span>
                                                    <span class="coupon-picker__terms">{{ $choice->conditionText() }}</span>

                                                    @if($row['reason'])
                                                        <span class="coupon-picker__why">{{ $row['reason'] }}</span>
                                                    @elseif($isCurrent)
                                                        <span class="coupon-picker__why">Đang áp dụng cho đơn này</span>
                                                    @endif
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>

                                    <p class="coupon-picker__more">
                                        <a href="{{ route('shop.vouchers.index') }}">Lưu thêm mã</a>
                                    </p>
                                </details>
                            @else
                                <p class="text-caption mt-2 mb-0">
                                    Ví voucher đang trống.
                                    <a href="{{ route('shop.vouchers.index') }}">Xem mã đang mở</a>.
                                </p>
                            @endif
                        @else
                            <p class="text-caption mt-2 mb-0">
                                <a href="{{ route('login') }}">Đăng nhập</a> để lưu mã về ví và được chọn mã tự động.
                            </p>
                        @endauth

                        @if($autoDeclined)
                            <p class="coupon-box__auto">
                                Cửa hàng đang không tự chọn mã cho đơn này.
                                <button type="submit"
                                        form="checkout-details-form"
                                        formaction="{{ route('shop.checkout.auto-coupon') }}"
                                        formnovalidate
                                        class="btn-link-inline">Chọn giúp tôi</button>
                            </p>
                        @endif
                    </div>

                    @auth
                        @php($soDuDiem = app(\App\Services\Points\PointLedger::class)->soDu(auth()->user()))
                        @php($diemToiDa = \App\Services\Points\PointRedemption::dungDuoc(PHP_INT_MAX, $basket->itemsAfterCoupon(), $soDuDiem))
                        <div class="checkout-coupon" data-khoi-diem>
                            <h2 class="checkout-coupon__title">
                                <x-site.icon name="star" class="checkout-coupon__icon" />
                                Điểm thưởng
                            </h2>

                            <p class="text-caption mb-2">
                                Bạn có <strong>{{ number_format($soDuDiem, 0, ',', '.') }}</strong> điểm
                                (1 điểm = {{ \App\Services\Points\PointRedemption::dongMoiDiem() }}đ).
                                @if($diemToiDa > 0)
                                    Đơn này dùng được tối đa <strong>{{ number_format($diemToiDa, 0, ',', '.') }}</strong> điểm.
                                @else
                                    Cần dùng từ {{ \App\Services\Points\PointRedemption::toiThieu() }} điểm, tối đa {{ \App\Services\Points\PointRedemption::phanTramToiDa() }}% tiền hàng.
                                @endif
                            </p>

                            @if($basket->pointsUsed() > 0)
                                <div class="coupon-box__applied">
                                    <span class="coupon-box__applied-code">
                                        <strong>{{ number_format($basket->pointsUsed(), 0, ',', '.') }} điểm</strong>
                                        <span class="text-muted">giảm {{ \App\Services\Shop\Money::format($basket->pointsDiscount()) }}</span>
                                    </span>
                                    <button type="submit"
                                            form="checkout-details-form"
                                            formaction="{{ route('shop.checkout.remove-points') }}"
                                            formnovalidate
                                            name="_method" value="DELETE"
                                            class="btn btn-ghost btn-sm">Bỏ dùng điểm</button>
                                </div>
                            @endif

                            @if($diemToiDa > 0)
                                <div class="coupon-box__input">
                                    <label class="visually-hidden" for="points">Số điểm muốn dùng</label>
                                    <input type="number" id="points" name="points" min="0" max="{{ $diemToiDa }}" step="1"
                                           form="checkout-details-form"
                                           class="form-control @error('points') is-invalid @enderror"
                                           value="{{ old('points', $basket->pointsUsed() ?: $diemToiDa) }}">
                                    <button type="submit"
                                            form="checkout-details-form"
                                            formaction="{{ route('shop.checkout.apply-points') }}"
                                            formnovalidate
                                            class="btn btn-secondary-brand">Dùng điểm</button>
                                </div>
                                <x-form-error name="points" />
                            @endif
                        </div>
                    @endauth

                    <x-cart.summary :basket="$basket" :itemized="true" :coupon-is-auto="$couponIsAuto" />

                </div>

            </div>

        </div>

    </div>
</section>

<div class="checkout-bar">
    <div class="checkout-bar__total">
        <span class="checkout-bar__label">Tổng thanh toán</span>
        <strong class="checkout-bar__amount"><x-site.money :amount="(float) $basket->grandTotal()" /></strong>
    </div>

    <button type="submit" form="checkout-details-form" class="btn btn-primary-brand">
        Xem lại đơn hàng
    </button>
</div>





@endsection
