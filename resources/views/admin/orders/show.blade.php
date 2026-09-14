@extends('layouts.admin')

@section('title', 'Đơn ' . $order->order_number)

@section('content')

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="admin-page-title">{{ $order->order_number }}</h1>
            <p class="admin-page-subtitle">
                Đặt lúc <x-site.time :at="$order->created_at" />
                @if($order->user)
                    &middot; tài khoản {{ $order->user->email }}
                @else
                    &middot; khách không đăng nhập
                @endif
            </p>
        </div>

        <div class="d-flex gap-2">
            {{-- Mở tab mới: trang in không có khung quản trị, và người dùng
                 in xong vẫn cần trang đơn còn nguyên để làm tiếp. --}}
            <a href="{{ route('admin.orders.print', $order) }}" target="_blank" rel="noopener"
               class="btn btn-outline-admin">In phiếu</a>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-admin">Về danh sách</a>
        </div>
    </div>

    <div class="row g-3">

        <div class="col-lg-7">

            {{--
                ============ CẢNH BÁO RỦI RO ============

                Đặt Ở ĐẦU TRANG, trên cả danh sách sản phẩm: nhân viên mở
                đơn ra là để bắt đầu làm hàng. Cảnh báo nằm dưới cùng thì
                họ đọc sau khi hoa đã cắt — lúc đó biết cũng không cứu được.

                Liệt kê TỪNG dấu hiệu kèm điểm. Một con số 60 trần trụi
                không cho nhân viên biết phải hỏi gì khi gọi xác nhận.
            --}}
            @if($order->needsRiskReview())
                <div class="risk-panel mb-3">
                    <p class="risk-panel__title">
                        ⚠ Đơn cần xác nhận trước khi làm hàng &middot; {{ $order->risk_score }}/100 điểm rủi ro
                    </p>

                    <ul class="risk-panel__list">
                        @foreach($order->riskFlags() as $flag)
                            <li>{{ $flag['label'] }} <span class="risk-panel__points">+{{ $flag['points'] }}</span></li>
                        @endforeach
                    </ul>

                    <p class="risk-panel__note mb-0">
                        {{--
                            Nói rõ hệ thống KHÔNG tự quyết. Không có câu này thì
                            nhân viên dễ coi con số như một phán quyết và từ chối
                            đơn của khách thật.
                        --}}
                        Đây chỉ là gợi ý dựa trên dữ liệu sẵn có — hệ thống không tự huỷ đơn nào.
                        Gọi điện xác nhận trước khi cắt hoa là đủ để loại phần lớn rủi ro.
                    </p>
                </div>
            @endif

            <div class="admin-panel mb-3">

                <h2 class="h6 fw-bold mb-3">Sản phẩm</h2>

                <div class="table-responsive">
                    <table class="admin-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Đơn giá</th>
                                <th>SL</th>
                                <th class="text-end">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td>
                                        {{-- Tên đọc từ BẢN CHỤP trong đơn, không từ bảng products --}}
                                        <div class="fw-bold">{{ $item->product_name }}</div>

                                        @if($item->variant_name)
                                            <div class="admin-page-subtitle">{{ $item->variant_name }}</div>
                                        @endif

                                        @if($item->product_sku)
                                            <div class="admin-page-subtitle">{{ $item->product_sku }}</div>
                                        @endif

                                        @if($item->promotion_name)
                                            <div class="admin-page-subtitle">KM: {{ $item->promotion_name }}</div>
                                        @endif

                                        @if($item->product_id === null)
                                            <div class="admin-page-subtitle">Sản phẩm đã bị xoá khỏi cửa hàng</div>
                                        @endif
                                    </td>

                                    <td>
                                        @if($item->wasDiscounted())
                                            <s class="admin-page-subtitle">
                                                <x-site.money :amount="(float) $item->unit_base_price" />
                                            </s><br>
                                        @endif
                                        <x-site.money :amount="(float) $item->unit_price" />
                                    </td>

                                    <td>{{ $item->quantity }}</td>

                                    <td class="text-end fw-bold">
                                        <x-site.money :amount="(float) $item->line_total" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>

            <div class="admin-panel">

                <h2 class="h6 fw-bold mb-3">Giao hàng</h2>

                <dl class="admin-detail-list mb-0">
                    <div><dt>Người nhận</dt><dd>{{ $order->recipient_name }}</dd></div>
                    <div><dt>Điện thoại</dt><dd>{{ $order->recipient_phone }}</dd></div>

                    @if($order->recipient_email)
                        <div><dt>Email</dt><dd>{{ $order->recipient_email }}</dd></div>
                    @endif

                    <div>
                        <dt>Địa chỉ</dt>
                        <dd>{{ collect([
                            $order->shipping_address,
                            $order->shipping_ward,
                            $order->shipping_district,
                            $order->shipping_province,
                        ])->filter()->implode(', ') }}</dd>
                    </div>

                    <div>
                        <dt>Ngày nhận</dt>
                        <dd>{{ $order->delivery_date?->format('d/m/Y') ?? 'Sớm nhất có thể' }}</dd>
                    </div>

                    @if($order->delivery_note)
                        <div><dt>Ghi chú</dt><dd>{{ $order->delivery_note }}</dd></div>
                    @endif
                </dl>

                {{-- Sửa khi khách gọi báo nhập nhầm. Luật ở OrderController::suaDuocGiaoHang(). --}}
                @if(\App\Http\Controllers\Admin\OrderController::suaDuocGiaoHang($order))
                    <details class="mt-3" @if($errors->hasAny(['recipient_name', 'recipient_phone', 'shipping_address', 'delivery_date', 'delivery_note'])) open @endif>
                        <summary class="small">Sửa thông tin giao hàng</summary>

                        <form method="POST" action="{{ route('admin.orders.delivery', $order) }}" class="mt-2">
                            @csrf
                            @method('PATCH')

                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <label class="form-label small mb-1" for="gh-ten">Người nhận</label>
                                    <input id="gh-ten" name="recipient_name" required maxlength="150"
                                           class="form-control form-control-sm @error('recipient_name') is-invalid @enderror"
                                           value="{{ old('recipient_name', $order->recipient_name) }}">
                                    <x-form-error name="recipient_name" />
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label small mb-1" for="gh-sdt">Điện thoại</label>
                                    <input id="gh-sdt" name="recipient_phone" required maxlength="20"
                                           class="form-control form-control-sm @error('recipient_phone') is-invalid @enderror"
                                           value="{{ old('recipient_phone', $order->recipient_phone) }}">
                                    <x-form-error name="recipient_phone" />
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="gh-dc">Số nhà, đường</label>
                                    <input id="gh-dc" name="shipping_address" required maxlength="255"
                                           class="form-control form-control-sm @error('shipping_address') is-invalid @enderror"
                                           value="{{ old('shipping_address', $order->shipping_address) }}">
                                    <x-form-error name="shipping_address" />
                                    <div class="form-text">
                                        Tỉnh / quận / phường không sửa ở đây: phí ship và mã GHN tính từ đó.
                                        Đổi khu vực thì huỷ đơn để khách đặt lại.
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label small mb-1" for="gh-ngay">Ngày giao</label>
                                    <input id="gh-ngay" name="delivery_date" type="date"
                                           class="form-control form-control-sm @error('delivery_date') is-invalid @enderror"
                                           value="{{ old('delivery_date', $order->delivery_date?->format('Y-m-d')) }}"
                                           min="{{ \App\Services\Time\Gio::choONgay(now()) }}">
                                    <x-form-error name="delivery_date" />
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="gh-gc">Ghi chú giao hàng</label>
                                    <textarea id="gh-gc" name="delivery_note" rows="2" maxlength="1000"
                                              class="form-control form-control-sm @error('delivery_note') is-invalid @enderror">{{ old('delivery_note', $order->delivery_note) }}</textarea>
                                    <x-form-error name="delivery_note" />
                                </div>
                            </div>

                            <button type="submit" class="btn btn-sm btn-outline-admin mt-2">Lưu thông tin giao hàng</button>
                        </form>
                    </details>
                @endif

            </div>

        </div>

        <div class="col-lg-5">

            <div class="admin-panel mb-3">

                <h2 class="h6 fw-bold mb-3">Thanh toán</h2>

                <dl class="admin-detail-list">
                    <div><dt>Tạm tính</dt><dd><x-site.money :amount="(float) $order->subtotal" /></dd></div>

                    @if((float) $order->discount_total > 0)
                        <div><dt>Giảm giá</dt><dd>&minus;<x-site.money :amount="(float) $order->discount_total" /></dd></div>
                    @endif

                    <div><dt>Phí giao hàng</dt><dd><x-site.money :amount="(float) $order->shipping_fee" /></dd></div>
                    <div><dt>Tổng cộng</dt><dd class="fw-bold"><x-site.money :amount="(float) $order->grand_total" /></dd></div>

                    {{--
                        THUẾ GTGT — CHI TIẾT ĐẦY ĐỦ Ở TRANG QUẢN TRỊ.

                        Giá niêm yết đã bao gồm VAT, nên con số này KHÔNG
                        cộng vào tổng: nó là phần thuế NẰM TRONG tổng ở
                        dòng trên. Đó là lý do nó thụt vào và ghi rõ "đã
                        gồm trong tổng" — không có dòng chú thích đó thì
                        người đọc sẽ cộng nhầm và thấy đơn lệch.

                        Đơn cũ đặt trước khi hệ thống tính thuế có
                        tax_amount = NULL, và khi ấy KHÔNG hiện gì cả.
                        Hiện "0₫" là nói rằng đơn đó miễn thuế — sai hẳn
                        với "không có số liệu".

                        BẢNG TÁCH THEO MỨC là thứ hoá đơn GTGT bắt buộc
                        phải ghi: 5.000.000₫ chịu 8% và 2.000.000₫ chịu
                        10% là hai dòng khác nhau, không phải một dòng
                        "thuế hỗn hợp". Từ khi mỗi sản phẩm có nhóm thuế
                        riêng, một đơn có thể mang nhiều mức cùng lúc.
                    --}}
                    @if($order->tax_amount !== null)
                        <div class="admin-money__tax">
                            <dt>
                                Trong đó thuế VAT
                                <span class="text-muted small">
                                    ({{ \App\Services\Tax\TaxCalculator::formatRate((string) $order->tax_rate) }},
                                    đã gồm trong tổng)
                                </span>
                            </dt>
                            <dd><x-site.money :amount="(float) $order->tax_amount" /></dd>
                        </div>

                        <div class="admin-money__tax">
                            <dt>Tiền hàng chưa thuế</dt>
                            <dd><x-site.money :amount="(float) $order->netTotal()" /></dd>
                        </div>

                        @php($cacMuc = $order->taxByRate())

                        @if(count($cacMuc) > 1)
                            @foreach($cacMuc as $muc)
                                <div class="admin-money__tax">
                                    <dt>
                                        <span class="text-muted small">
                                            {{ \App\Services\Tax\TaxCalculator::formatRate($muc['rate']) }}
                                            trên <x-site.money :amount="(float) $muc['net']" />
                                        </span>
                                    </dt>
                                    <dd><x-site.money :amount="(float) $muc['tax']" /></dd>
                                </div>
                            @endforeach
                        @endif
                    @endif
                    <div><dt>Hình thức</dt><dd>{{ $order->payment_method->label() }}</dd></div>

                    <div>
                        <dt>Tình trạng</dt>
                        <dd>
                            <span class="status-pill status-pill--{{ $order->payment_status->badge() }}">
                                {{ $order->payment_status->label() }}
                            </span>
                        </dd>
                    </div>
                </dl>

                {{--
                    HOÁ ĐƠN GTGT — CÙNG MỘT KHỐI VỚI TRANG CỦA KHÁCH.

                    Nhân viên và khách phải đọc đúng một bộ thông tin:
                    lệch nhau ở đây là hai bên tranh cãi về mã số thuế
                    trong lúc hàng đã giao. Xem x-order.invoice-card.
                --}}
                <x-order.invoice-card :invoice="$order->invoice" />

                {{--
                    ĐƠN ĐÃ HUỶ MÀ KHÁCH ĐÃ TRẢ TIỀN = CỬA HÀNG ĐANG NỢ KHÁCH.

                    Hệ thống KHÔNG tự đặt "đã hoàn tiền": nó không biết ai
                    đó có thật sự chuyển khoản trả lại hay chưa. Việc của
                    phần mềm là NHẮC cho đến khi người thật xác nhận đã
                    chuyển; tự đánh dấu là xoá mất khoản nợ trên giấy tờ
                    trong khi tiền vẫn nằm ở cửa hàng.
                --}}
                @if($owesRefund)
                    <div class="alert alert-warning py-2 px-3 mb-3">
                        <strong>Cần hoàn tiền cho khách.</strong>
                        Đơn đã huỷ nhưng khách đã thanh toán
                        <x-site.money :amount="(float) $order->grand_total" />.
                        Còn phải hoàn <x-site.money :amount="$order->refundableAmount()" />.
                        <a href="#hoan-tien">Ghi hoàn tiền</a>.
                    </div>
                @endif

                {{--
                    Nút dựng từ PaymentStatus::nextStates() chứ không gõ tay.

                    Bản trước chỉ có một nút "Đánh dấu đã thanh toán" và
                    KHÔNG có đường lui: bấm nhầm đơn là cửa hàng giao hàng
                    rồi không bao giờ đòi tiền, mà không sửa được. Cũng
                    không có cách nào ghi nhận đã hoàn tiền, nên trạng thái
                    "Đã hoàn tiền" là mã chết suốt từ lúc khai enum.
                --}}
                @if($paymentTargets && ! auth()->user()?->can('tai-chinh'))
                    {{-- Không bày nút bấm vào ra 403: nói rõ việc này thuộc quyền tài chính. --}}
                    <p class="admin-page-subtitle mb-0">Ghi nhận thanh toán thuộc quyền tài chính.</p>
                @elseif($paymentTargets)
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($paymentTargets as $target)
                            <form method="POST" action="{{ route('admin.orders.payment', $order) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="payment_status" value="{{ $target->value }}">
                                <button type="submit" class="btn btn-outline-admin btn-sm">
                                    @switch($target)
                                        @case(\App\Enums\PaymentStatus::Paid)
                                            Đánh dấu đã thanh toán
                                            @break
                                        @case(\App\Enums\PaymentStatus::Refunded)
                                            Đã hoàn tiền
                                            @break
                                        @case(\App\Enums\PaymentStatus::Unpaid)
                                            Gỡ đánh dấu (bấm nhầm)
                                            @break
                                    @endswitch
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif

            </div>

            <x-order.payment-log :transactions="$order->transactions" />

            @include('admin.orders._refunds')

            @include('admin.orders._doi-hang')

            {{--
                GHI CHÚ NỘI BỘ — chỉ cửa hàng đọc, khách không bao giờ thấy.

                Chỗ ghi những thứ đơn hàng không có ô nào chứa: "đã gọi 2
                lần không nghe", "khách hẹn giao sau 17h", "shipper báo
                nhà khoá cửa".

                Cột `admin_note` có từ lúc dựng bảng `orders` nhưng chưa
                từng có giao diện nào ghi vào — nên mọi ghi chú kiểu này
                trước giờ nằm trong đầu người trực, và mất khi đổi ca.
            --}}
            <div class="admin-panel mb-3">

                <h2 class="h6 fw-bold mb-1">Ghi chú nội bộ</h2>
                <p class="admin-page-subtitle mb-3">Khách không nhìn thấy phần này.</p>

                <form method="POST" action="{{ route('admin.orders.note', $order) }}">
                    @csrf
                    @method('PATCH')

                    <textarea
                        name="admin_note"
                        class="form-control mb-2 @error('admin_note') is-invalid @enderror"
                        rows="3"
                        maxlength="2000"
                        placeholder="Ví dụ: đã gọi 2 lần không nghe, khách hẹn giao sau 17h…"
                    >{{ old('admin_note', $order->admin_note) }}</textarea>

                    <x-form-error name="admin_note" />

                    <button type="submit" class="btn btn-outline-admin btn-sm">Lưu ghi chú</button>
                </form>

            </div>

            <div class="admin-panel">

                <h2 class="h6 fw-bold mb-3">Trạng thái đơn</h2>

                <p>
                    <span class="status-pill status-pill--{{ $order->status->badge() }}">
                        {{ $order->status->label() }}
                    </span>
                </p>

                @if($order->cancel_reason)
                    <p class="admin-page-subtitle">Lý do huỷ: {{ $order->cancel_reason }}</p>
                @endif

                @php($next = $order->status->nextStates())

                @if(empty($next))

                    {{-- Trạng thái kết thúc: không còn bước nào đi tiếp --}}
                    <p class="admin-page-subtitle mb-0">
                        Đơn đã ở trạng thái cuối, không thể chuyển tiếp.
                    </p>

                @else

                    {{--
                        Chỉ hiện những trạng thái HỢP LỆ kế tiếp. Danh sách
                        do OrderStatus quyết định, và OrderService kiểm tra
                        lại lần nữa ở phía máy chủ — sửa HTML cũng không
                        nhảy cóc được.
                    --}}
                    <form method="POST" action="{{ route('admin.orders.update-status', $order) }}">
                        @csrf
                        @method('PATCH')

                        <label class="form-label" for="status">Chuyển sang</label>
                        <select name="status" id="status" class="form-select mb-2" required>
                            @foreach($next as $state)
                                <option value="{{ $state->value }}">{{ $state->label() }}</option>
                            @endforeach
                        </select>

                        <label class="form-label" for="cancel_reason">Lý do (chỉ dùng khi huỷ)</label>
                        <input type="text" name="cancel_reason" id="cancel_reason" class="form-control mb-3"
                               maxlength="255" placeholder="Ví dụ: khách đổi ý">

                        <button type="submit" class="btn btn-primary-brand w-100">Cập nhật</button>
                    </form>

                    <p class="admin-page-subtitle mt-2 mb-0">
                        Huỷ đơn sẽ tự động hoàn lại tồn kho cho các sản phẩm có quản lý kho.
                    </p>

                    @if($order->ghn_order_code && $order->shipping_status !== 'cancel')
                        <p class="text-danger small mt-2 mb-0">
                            Đơn đã bàn giao cho GHN — muốn huỷ đơn thì huỷ vận đơn bên dưới trước.
                        </p>
                    @endif

                @endif

                {{--
                    LỊCH SỬ ĐI KÈM Ô ĐỔI TRẠNG THÁI, không nằm ở panel khác.

                    Trước khi bấm chuyển tiếp, câu hỏi tự nhiên là "đơn
                    này đã đi tới đâu, ai vừa động vào". Đặt lịch sử ở
                    một khối khác thì người dùng phải nhớ nó trong đầu
                    trong lúc thao tác — mà đó chính là lúc dễ bấm nhầm.

                    showActor bật: ở khu quản trị, "ai làm" chính là câu
                    hỏi. Trang của khách thì không, xem component.
                --}}
                {{--
                    VẬN ĐƠN GIAO HÀNG NHANH.

                    Đặt ngay dưới ô đổi trạng thái vì hai việc đi liền
                    nhau trong thực tế: gói xong hàng → chuyển sang "Đang
                    giao" → bàn giao cho GHN.

                    KHÔNG tự tạo vận đơn lúc khách đặt. Tạo vận đơn là
                    cam kết với GHN — họ cử người tới lấy hàng và tính
                    tiền cửa hàng. Làm tự động nghĩa là mọi đơn đặt nhầm,
                    đơn hết hàng, đơn khách huỷ sau ba phút đều thành một
                    chuyến xe có thật.
                --}}
                <hr class="my-3">

                <h3 class="h6 fw-bold mb-3">Vận đơn GHN</h3>

                @if($order->ghn_order_code)

                    <p class="mb-1">
                        Mã vận đơn:
                        <strong data-copy-value="{{ $order->ghn_order_code }}">{{ $order->ghn_order_code }}</strong>
                    </p>

                    <p class="admin-page-subtitle mb-1">
                        Trạng thái GHN: {{ $order->shipping_status }}
                    </p>

                    {{--
                        Hiện CƯỚC GHN THẬT bên cạnh phí thu của khách.

                        Hai con số này khác nhau mỗi khi cửa hàng miễn phí
                        giao cho đơn lớn: `shipping_fee` là tiền THU của
                        khách, `ghn_total_fee` là tiền TRẢ cho GHN. Chỉ
                        hiện một con số thì không ai biết tháng này bù lỗ
                        bao nhiêu tiền ship.
                    --}}
                    <p class="admin-page-subtitle mb-3">
                        @if($order->ghn_total_fee === null)
                            {{-- NULL là "GHN không báo cước", KHÁC 0₫. --}}
                            Cước GHN: chưa có số liệu
                        @else
                            Cước GHN: <x-site.money :amount="$order->ghn_total_fee" />
                        @endif
                        &middot; thu của khách: <x-site.money :amount="(float) $order->shipping_fee" />

                        @if($order->ghn_fee_payer === \App\Enums\GhnFeePayer::Buyer)
                            {{--
                                VẬN ĐƠN NGƯỜI NHẬN TRẢ CƯỚC: không có khoản "cửa
                                hàng bù" nào, vì cửa hàng không trả GHN. Ngược lại,
                                người nhận có thể đã bị thu phí ship hai lần — nói
                                ra để nhân viên biết mà kiểm tra với khách.
                            --}}
                            <span class="d-block text-danger mt-1">
                                Vận đơn này người nhận trả cước cho GHN, trong khi khách đã trả phí ship cho cửa hàng.
                                Kiểm tra xem shipper có thu thêm của khách không.
                            </span>
                        @elseif($order->ghn_total_fee !== null && $order->ghn_total_fee > (float) $order->shipping_fee)
                            <span class="text-danger">
                                (cửa hàng bù <x-site.money :amount="$order->ghn_total_fee - (float) $order->shipping_fee" />)
                            </span>
                        @endif
                    </p>

                    @if($order->shipping_status !== 'cancel')
                        <form action="{{ route('admin.orders.shipment.cancel', $order) }}" method="POST"
                              onsubmit="return confirm('Huỷ vận đơn GHN này?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm">Huỷ vận đơn</button>
                        </form>
                    @endif

                @elseif(! $order->to_district_id)

                    {{--
                        Đơn đặt TRƯỚC khi bật tính cước GHN không có mã
                        quận/phường, nên không tạo vận đơn tự động được.
                        Nói thẳng lý do thay vì hiện một cái nút bấm vào
                        chỉ báo lỗi.
                    --}}
                    <p class="admin-page-subtitle mb-0">
                        Đơn này không có mã địa giới GHN (đặt trước khi bật tính cước GHN),
                        nên phải tạo vận đơn thủ công trên trang của GHN.
                    </p>

                @else

                    @if(! in_array($order->status, [\App\Enums\OrderStatus::Confirmed, \App\Enums\OrderStatus::Preparing], true))
                        {{-- Cùng luật với GHNOrderService::create(): không bày nút sẽ bị từ chối. --}}
                        <p class="admin-page-subtitle mb-0">
                            Chỉ bàn giao cho GHN khi đơn "Đã xác nhận" hoặc "Đang chuẩn bị".
                        </p>
                    @else
                    <form action="{{ route('admin.orders.shipment.create', $order) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-secondary-brand btn-sm w-100">
                            Bàn giao cho GHN
                        </button>
                    </form>

                    <p class="admin-page-subtitle mt-2 mb-0">
                        Chỉ bấm khi hàng đã gói xong. GHN sẽ cử người tới lấy và tính cước cho cửa hàng.
                    </p>
                    @endif

                @endif

                <hr class="my-3">

                <h3 class="h6 fw-bold mb-3">Lịch sử đơn</h3>

                <x-order.timeline :events="$order->statusEvents" :show-actor="true" />

            </div>

        </div>

    </div>

@endsection
