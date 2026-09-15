@extends('layouts.app')

@section('title', 'Thanh toán')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <h1 class="text-h2 mb-3">Thanh toán</h1>

        <x-cart.steps :current="$step" />

        <div class="row g-4 checkout-layout">

            <div class="col-lg-7">

                {{--
                    MỘT BIỂU MẪU CHO CẢ NGƯỜI NHẬN, GIAO HÀNG VÀ THANH TOÁN.

                    Trước đây là hai màn hình. Phí giao phụ thuộc TỈNH — nhập
                    ở màn một — nên tổng tiền chỉ đúng từ màn hai trở đi:
                    khách điền xong màn một vẫn chưa biết mình phải trả bao
                    nhiêu, và đó là lúc nhiều người bỏ giỏ hàng.

                    CHIA THÀNH BA KHỐI CÓ ĐÁNH SỐ, không để một cột dài
                    1184px liền mạch: gộp hai màn hình lại mà không nhóm gì
                    thì khách cuộn qua một biểu mẫu dài không thấy đầu đuôi,
                    và không biết còn bao nhiêu việc nữa mới xong. Ba thẻ có
                    số thứ tự cho biết ngay mình đang ở đâu và còn mấy bước.

                    Mã giảm giá đã chuyển sang cột phải, ngay trên bảng tiền
                    — xem ghi chú ở đó.
                --}}
                <form id="checkout-details-form" method="POST" action="{{ route('shop.checkout.store-details') }}" class="checkout-form">
                    @csrf

                    {{-- ============ 1. NƠI NHẬN ============ --}}
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
                                {{--
                                    NÚT XOÁ NẰM NGOÀI <label>, có chủ ý.

                                    Đặt bên trong thì mỗi cú bấm vào nút
                                    cũng tick luôn cái radio của nhãn —
                                    khách bấm xoá lại vừa chọn đúng địa
                                    chỉ mình đang muốn bỏ.

                                    Nút thuộc về một <form> khác qua thuộc
                                    tính form="": cả khối này đang nằm
                                    trong biểu mẫu thanh toán, mà HTML
                                    không cho lồng form. Các form xoá được
                                    đặt ở cuối trang.
                                --}}
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

                        {{--
                            BA Ô CHỌN NỐI NHAU: Tỉnh → Quận/Huyện → Phường/Xã.
                            ============================================================
                            Danh mục lấy trực tiếp từ Giao Hàng Nhanh, vì CƯỚC ĐƯỢC
                            TÍNH THEO ĐÚNG BỘ MÃ NÀY. Nhập tay như trước thì gõ "Bắc
                            Từ Liêm" hay "Q. Bắc Từ Liêm" đều ra một chuỗi mà GHN
                            không hiểu, và không tính được cước thật.

                            MỖI Ô CÓ HAI PHẦN:
                              - <select> hiện TÊN cho người đọc;
                              - <input hidden> giữ TÊN đó để lưu vào đơn hàng.

                            Vì sao cần cả hai: `<select>` gửi lên MÃ SỐ của GHN, mà
                            đơn hàng là chứng từ phải đọc được sau nhiều năm — kể cả
                            khi GHN đổi mã hoặc cửa hàng đổi sang đơn vị vận chuyển
                            khác. Chỉ lưu mã thì nhân viên mở đơn cũ thấy
                            `to_district_id = 1482` và không biết đó là đâu.

                            KHÔNG CÓ JAVASCRIPT THÌ SAO: ba ô chọn nằm im, không tải
                            được danh mục. Phần dự phòng ngay bên dưới cho nhập tay
                            như cũ, và phí lùi về bảng theo tỉnh. Chậm hơn và kém
                            chính xác hơn, nhưng khách vẫn đặt được hàng.
                        --}}
                        {{--
                            Ba ô chọn ẨN SẴN, JavaScript mới cho hiện.

                            Không có JavaScript thì chúng đứng im ở "-- Đang tải...
                            --" mãi mãi — một hàng ô vô dụng chắn giữa biểu mẫu.
                            Ẩn sẵn thì người tắt JavaScript chỉ thấy khối nhập tay
                            bên dưới, đúng thứ dùng được với họ.
                        --}}
                        <div class="col-md-4 ghn-select" hidden>
                            <label class="form-label" for="province_select">Tỉnh/Thành phố *</label>
                            {{--
                                ĐỊA CHỈ ROUTE ĐI QUA THUỘC TÍNH data, không nhúng
                                Blade vào tệp JavaScript.

                                Nhờ vậy ghn-address.js là một tệp tĩnh thật: Vite
                                đóng gói và băm tên được, trình duyệt lưu đệm được,
                                và nó không phải nằm inline trong mỗi trang. Tài
                                liệu hướng dẫn viết `{{ route(...) }}` thẳng trong
                                <script> — cách đó buộc toàn bộ đoạn mã phải nằm
                                trong Blade và tải lại ở mọi lần mở trang.

                                `__ID__` là chỗ JavaScript thay bằng mã thật.
                            --}}
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

                        <div class="col-md-4 ghn-select" hidden>
                            <label class="form-label" for="district_select">Quận/Huyện *</label>
                            <select id="district_select" class="form-select" data-ghn-district disabled>
                                <option value="">-- Chọn Tỉnh/Thành trước --</option>
                            </select>
                            {{--
                                data-cu giữ tên quận/huyện đã chọn lần trước.

                                Biểu mẫu quay về vì sai một ô bất kỳ thì ba ô địa
                                chỉ phải tự chọn lại đúng chỗ cũ — không thì khách
                                phải làm lại cả ba cấp mỗi lần gõ nhầm số điện
                                thoại, và họ sẽ bỏ giữa chừng.
                            --}}
                            <input type="hidden" id="shipping_district" name="shipping_district"
                                   data-cu="{{ old('shipping_district', $values['shipping_district'] ?? '') }}"
                                   value="{{ old('shipping_district', $values['shipping_district'] ?? '') }}">
                            {{-- Mã GHN: thứ máy chủ dùng để hỏi cước, xem ShippingQuote. --}}
                            <input type="hidden" name="to_district_id" data-ghn-district-id
                                   value="{{ old('to_district_id', $values['to_district_id'] ?? '') }}">
                        </div>

                        <div class="col-md-4 ghn-select" hidden>
                            <label class="form-label" for="ward_select">Phường/Xã *</label>
                            <select id="ward_select" class="form-select" data-ghn-ward disabled>
                                <option value="">-- Chọn Quận/Huyện trước --</option>
                            </select>
                            <input type="hidden" id="shipping_ward" name="shipping_ward"
                                   value="{{ old('shipping_ward', $values['shipping_ward'] ?? '') }}">
                            <input type="hidden" name="to_ward_code" data-ghn-ward-code
                                   value="{{ old('to_ward_code', $values['to_ward_code'] ?? '') }}">
                        </div>

                    </div>

                    {{--
                        PHẦN DỰ PHÒNG cho trường hợp không có JavaScript hoặc GHN
                        không trả lời.

                        Ẩn đi khi JavaScript chạy được (CSS dùng `html.has-js`), nên
                        người dùng bình thường không thấy. Nhưng nó phải TỒN TẠI
                        trong HTML: thiếu nó thì người tắt JavaScript nhìn thấy ba ô
                        chọn rỗng và không có cách nào nhập địa chỉ — tức là không
                        đặt được hàng.
                    --}}
                    <div class="ghn-fallback mt-3" data-ghn-fallback>
                        <p class="text-caption mb-2">
                            Không tải được danh sách địa chỉ. Bạn có thể nhập tay —
                            phí giao sẽ tính theo bảng phí vùng.
                        </p>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="province_manual">Tỉnh/Thành phố *</label>
                                <x-form.province-select
                                    name="shipping_province"
                                    id="province_manual"
                                    :selected="$values['shipping_province'] ?? ''" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="district_manual">Quận/Huyện</label>
                                <input type="text" id="district_manual" name="shipping_district"
                                       value="{{ old('shipping_district', $values['shipping_district'] ?? '') }}"
                                       class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="ward_manual">Phường/Xã</label>
                                <input type="text" id="ward_manual" name="shipping_ward"
                                       value="{{ old('shipping_ward', $values['shipping_ward'] ?? '') }}"
                                       class="form-control">
                            </div>
                        </div>
                    </div>

                    {{--
                        CƯỚC HIỆN NGAY KHI CHỌN XONG PHƯỜNG/XÃ.

                        Con số này CHỈ ĐỂ XEM TRƯỚC. Lúc ghi đơn, máy chủ hỏi lại
                        GHN bằng chính mã quận/phường đã lưu — xem ShippingQuote.
                        Biểu mẫu này KHÔNG gửi lên số tiền nào.
                    --}}
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

                    </div>{{-- /data-address-fields --}}



                        </div>
                    </section>

                    {{-- ============ 2. THỜI GIAN GIAO ============ --}}
                    <section class="checkout-step">
                        <h2 class="checkout-step__title">
                            <span class="checkout-step__num">2</span>
                            Giao khi nào
                            {{-- Nói ngay đây là phần KHÔNG bắt buộc: khách
                                 nhìn thấy "không bắt buộc" thì lướt qua được
                                 mà không thấy áy náy, thay vì dừng lại nghĩ. --}}
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

                    {{-- ============ 3. THANH TOÁN ============ --}}
                    <section class="checkout-step">
                        <h2 class="checkout-step__title">
                            <span class="checkout-step__num">3</span>
                            Thanh toán thế nào
                        </h2>

                        <div class="checkout-step__body">
<div class="payment-options">
                        @foreach($paymentMethods as $method)
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="{{ $method->value }}"
                                       @checked(old('payment_method', $values['payment_method'] ?? 'cod') === $method->value)>
                                <span class="payment-option__body">
                                    <span class="payment-option__name">{{ $method->label() }}</span>
                                    <span class="payment-option__hint">{{ $method->hint() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <x-form-error name="payment_method" />

                    {{--
                        CÁCH TRẢ TIỀN TRÊN TRANG MOMO — chỉ hiện khi chọn MoMo.

                        MoMo có nhiều dịch vụ khác nhau và mỗi cái mở ra
                        một trang khác hẳn: mã QR để quét bằng ứng dụng,
                        hay ô nhập thẻ quốc tế. Chọn nhầm thì KHÔNG có lỗi
                        nào báo — MoMo vẫn nhận yêu cầu, chỉ là trang mở
                        ra không có ô nhập nào khớp với thứ khách đang
                        cầm. Vì thế phải để khách tự chọn.

                        Ẩn/hiện bằng CSS thuần (:has), không bằng
                        JavaScript — cùng cách đã dùng cho khối hoá đơn.
                        Máy chủ mới là nơi quyết định ô nào bắt buộc.
                    --}}
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

                    {{-- ============ 4. HOÁ ĐƠN GTGT ============ --}}
                    <section class="checkout-step">
                        <h2 class="checkout-step__title">
                            <span class="checkout-step__num">4</span>
                            Hoá đơn GTGT
                        </h2>

                        <div class="checkout-step__body">

                            {{--
                                MẶC ĐỊNH KHÔNG TÍCH, VÀ CÁC Ô ẨN ĐI.

                                Phần lớn khách mua một bó hoa không lấy hoá
                                đơn. Bày sẵn năm ô mã số thuế trước mặt họ là
                                dựng một bức tường ngay trước nút thanh toán để
                                phục vụ thiểu số — và bước cuối là chỗ đắt nhất
                                để làm khách chùn tay.

                                Ẩn/hiện bằng CSS thuần (:has), KHÔNG bằng
                                JavaScript: khách tắt JS vẫn phải lấy được hoá
                                đơn. Máy chủ mới là nơi quyết định trường nào
                                bắt buộc — xem CheckoutDetailsRequest.
                            --}}
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
                                    {{--
                                        Ô RIÊNG, KHÔNG DÙNG LẠI EMAIL ĐẶT HÀNG.

                                        Đơn thường do thư ký hoặc trợ lý đặt,
                                        còn hoá đơn phải về kế toán. Dùng chung
                                        một ô là gửi hoá đơn nhầm chỗ cho gần
                                        như mọi đơn của công ty.
                                    --}}
                                    <div class="form-text">Hoá đơn điện tử sẽ được gửi tới địa chỉ này.</div>
                                    <x-form-error name="invoice_email" />
                                </div>

                                {{--
                                    NÓI THẲNG RA CỬA HÀNG LÀM ĐƯỢC ĐẾN ĐÂU.

                                    Website ghi nhận yêu cầu và dữ liệu; việc
                                    phát hành hoá đơn điện tử hợp lệ đi qua nhà
                                    cung cấp dịch vụ hoá đơn. Để khách tưởng
                                    hoá đơn có ngay sau khi bấm đặt hàng là hứa
                                    một điều hệ thống chưa làm được.
                                --}}
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

                {{--
                    BIỂU MẪU XOÁ ĐỊA CHỈ — đặt NGOÀI biểu mẫu thanh toán.

                    HTML không cho lồng form, nên chúng đứng riêng ở đây
                    và các nút "×" trong danh sách nối vào bằng thuộc tính
                    form="" — đúng cách đã dùng cho ô chọn món ở giỏ hàng.

                    Mỗi địa chỉ một biểu mẫu vì đường dẫn khác nhau. Ẩn đi
                    bằng `hidden`, không phải CSS: các form này không có
                    gì để nhìn, và `hidden` thì trình đọc màn hình cũng bỏ
                    qua luôn.
                --}}
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

                    {{--
                        MÃ GIẢM GIÁ Ở CỘT PHẢI, ngay trên bảng tiền.

                        Trước đây nó nằm giữa cột trái, kẹp giữa "Thời gian
                        giao" và "Hình thức thanh toán" — cách xa con số mà nó
                        thay đổi hơn nửa màn hình. Áp mã xong phải đi tìm xem
                        tổng tiền có đổi không.

                        MỌI NÚT Ở ĐÂY ĐỀU THUỘC BIỂU MẪU BÊN TRÁI
                        (form="checkout-details-form"), chỉ đổi đích bằng
                        formaction. Trước đây chúng là biểu mẫu riêng, và hậu
                        quả là khách điền xong tên, số điện thoại, địa chỉ rồi
                        bấm "Bỏ mã" thì trang tải lại TRẮNG TRƠN — đúng cảm
                        giác đơn hàng vừa bị huỷ. Nay thứ đang gõ dở đi kèm
                        theo và được trả lại qua old().

                        formnovalidate: mấy nút này không phải "gửi đơn", nên
                        không được đòi điền đủ mới cho bấm.
                    --}}
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

                                {{--
                                    name="_method" value="DELETE" đặt TRÊN NÚT
                                    chứ không phải một ô ẩn: ô ẩn nằm trong
                                    biểu mẫu thì nút "Xem lại đơn hàng" cũng
                                    gửi kèm, biến việc đặt hàng thành một
                                    request DELETE. Giá trị của nút chỉ được
                                    gửi khi chính nó được bấm.
                                --}}
                                <button type="submit"
                                        form="checkout-details-form"
                                        formaction="{{ route('shop.checkout.remove-coupon') }}"
                                        formnovalidate
                                        name="_method" value="DELETE"
                                        class="btn btn-ghost btn-sm">Bỏ mã</button>
                            </div>
                        @endif

                        {{--
                            Ô NHẬP TAY luôn hiện, kể cả khi đang có mã.

                            Bản trước giấu nó sau khi áp mã, nên muốn đổi sang
                            mã khác phải bỏ mã cũ rồi mới gõ được mã mới — hai
                            lần tải trang cho một việc.
                        --}}
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

                        {{--
                            DANH SÁCH MÃ TRONG VÍ, mở bằng <details> nên
                            không cần JavaScript.

                            CHỈ MÃ ĐÃ LƯU. Trang Voucher hứa với khách rằng
                            "lưu mã về ví, tới bước thanh toán chọn lại là
                            xong" — liệt kê ở đây cả mã họ chưa lưu là nói
                            khác lời hứa đó, và làm nút "Lưu mã" thành vô
                            nghĩa.

                            HIỆN CẢ MÃ CHƯA DÙNG ĐƯỢC, kèm lý do: "cần đơn
                            từ 300.000đ" cho khách biết mua thêm chút nữa là
                            được giảm — có ích hơn hẳn việc giấu đi.

                            MỖI MÃ LÀ MỘT NÚT GỬI mang theo wallet_code — đi
                            qua ĐÚNG endpoint mà ô nhập tay dùng, nên mọi
                            phép kiểm tra chạy y hệt. Bấm nút không phải
                            đường tắt bỏ qua kiểm tra.
                        --}}
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
                                {{-- Cùng một câu với trang Voucher, để hai nơi
                                     không kể hai câu chuyện khác nhau. --}}
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

                        {{--
                            ĐỐI TRỌNG CỦA NÚT "BỎ MÃ".

                            Bỏ mã xong là cửa hàng ngừng tự chọn mã cho đơn
                            này — đúng ý khách, nhưng phải có đường quay lại,
                            nếu không họ kẹt với lựa chọn của chính mình cho
                            tới hết phiên.
                        --}}
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

                    {{--
                        DÙNG ĐIỂM THƯỞNG — cùng kiểu nút với khối mã giảm giá: thuộc
                        biểu mẫu bên trái, đổi đích bằng formaction, nên thứ đang gõ dở
                        không mất. Số tiền giảm và mức tối đa do CheckoutBasket tính.
                    --}}
                    @auth
                        {{--
                            Dạng một dòng, KHÔNG dùng khối có thẻ đóng: Blade gom khối PHP
                            TRƯỚC khi bỏ chú thích, nên thẻ đóng ở đây ghép với dạng một dòng
                            phía trên (usableCount) thành một khối và nuốt mất cả đoạn giữa.
                            Vì cùng lý do đó, chú thích này không được viết tên hai thẻ ấy.
                        --}}
                        @php($soDuDiem = app(\App\Services\Points\PointLedger::class)->soDu(auth()->user()))
                        @php($diemToiDa = \App\Services\Points\PointRedemption::dungDuoc(PHP_INT_MAX, $basket->itemsAfterCoupon(), $soDuDiem))
                        <div class="checkout-coupon" data-khoi-diem>
                            <h2 class="checkout-coupon__title">
                                <x-site.icon name="star" class="checkout-coupon__icon" />
                                Điểm thưởng
                            </h2>

                            <p class="text-caption mb-2">
                                Bạn có <strong>{{ number_format($soDuDiem, 0, ',', '.') }}</strong> điểm
                                (1 điểm = {{ \App\Services\Points\PointRedemption::DONG_MOI_DIEM }}đ).
                                @if($diemToiDa > 0)
                                    Đơn này dùng được tối đa <strong>{{ number_format($diemToiDa, 0, ',', '.') }}</strong> điểm.
                                @else
                                    Cần dùng từ {{ \App\Services\Points\PointRedemption::TOI_THIEU }} điểm, tối đa {{ \App\Services\Points\PointRedemption::PHAN_TRAM_TOI_DA }}% tiền hàng.
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

{{--
    THANH TỔNG TIỀN DÍNH ĐÁY — CHỈ TRÊN ĐIỆN THOẠI.

    Trên màn hình rộng, bảng tóm tắt nằm cột phải và luôn nhìn thấy được.
    Trên điện thoại thì hai cột xếp chồng: biểu mẫu trước, tóm tắt sau —
    nghĩa là nút gửi nằm TRÊN bảng tiền, và khách bấm "Xem lại đơn hàng"
    trước khi kịp nhìn thấy mình phải trả bao nhiêu.

    Thanh này lặp lại tổng tiền và nút gửi ở đáy màn hình, luôn trong tầm
    mắt. Nút bên trong dùng form="checkout-details-form" nên bấm ở đây
    hay bấm nút trong biểu mẫu đều như nhau.
--}}
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
