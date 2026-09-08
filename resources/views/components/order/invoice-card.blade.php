@props([
    'invoice' => null,
])

{{--
    DỮ LIỆU HOÁ ĐƠN GTGT CỦA MỘT ĐƠN.
    ============================================================
    MỘT BẢN DUY NHẤT, dùng ở cả trang đơn hàng của khách và trang quản
    trị. Hai bản chép tay sẽ lệch nhau, và lệch ở đây nghĩa là khách và
    nhân viên đọc hai bộ thông tin hoá đơn khác nhau cho cùng một đơn.

    ============================================================
    ⚠️ NÓI THẲNG RA ĐÂY CHƯA PHẢI HOÁ ĐƠN ĐÃ PHÁT HÀNH.

    Hoá đơn điện tử hợp lệ phải được phát hành theo quy trình và định
    dạng của quy định về hoá đơn điện tử, thường qua một nhà cung cấp
    dịch vụ. Cửa hàng chưa tích hợp bước đó.

    Để giao diện trông như đã có hoá đơn là loại nói dối tệ nhất: khách
    yên tâm không đòi nữa, rồi tới kỳ quyết toán mới phát hiện không có
    chứng từ nào. Vì thế trạng thái luôn hiện ra, và câu giải thích của
    nó (InvoiceStatus::hint()) đi kèm chứ không ẩn sau một dấu hỏi.
--}}

@if($invoice)
    <div class="invoice-card">

        <div class="invoice-card__head">
            <h3 class="invoice-card__title">Hoá đơn GTGT</h3>
            <span class="status-pill status-pill--{{ $invoice->status->badge() }}">
                {{ $invoice->status->label() }}
            </span>
        </div>

        <dl class="invoice-card__list">
            <div>
                <dt>Số hiệu</dt>
                <dd>{{ $invoice->invoice_number }}</dd>
            </div>

            <div>
                <dt>Xuất cho</dt>
                <dd>{{ $invoice->buyer_type->label() }}</dd>
            </div>

            <div>
                <dt>Tên</dt>
                <dd>{{ $invoice->buyer_name }}</dd>
            </div>

            {{-- Cá nhân không có mã số thuế: KHÔNG hiện dòng trống. --}}
            @if($invoice->buyer_tax_code)
                <div>
                    <dt>Mã số thuế</dt>
                    <dd>{{ $invoice->buyer_tax_code }}</dd>
                </div>
            @endif

            @if($invoice->buyer_address)
                <div>
                    <dt>Địa chỉ</dt>
                    <dd>{{ $invoice->buyer_address }}</dd>
                </div>
            @endif

            @if($invoice->buyer_email)
                <div>
                    <dt>Email nhận hoá đơn</dt>
                    <dd>{{ $invoice->buyer_email }}</dd>
                </div>
            @endif

            {{--
                BA CON SỐ HOÁ ĐƠN BẮT BUỘC PHẢI GHI: tiền hàng chưa thuế,
                tiền thuế, và tổng tiền thanh toán đã có thuế.

                `subtotal` ở đây là số CHƯA thuế — khác `orders.subtotal`
                (đã gồm thuế, vì giá niêm yết đã gồm thuế). Nhãn phải nói
                rõ điều đó, nếu không người đọc sẽ tưởng hai trang đang
                mâu thuẫn nhau.
            --}}
            <div>
                <dt>Tiền hàng chưa thuế</dt>
                <dd><x-site.money :amount="(float) $invoice->subtotal" /></dd>
            </div>

            @foreach($invoice->rateRows() as $muc)
                <div>
                    <dt>
                        <span class="text-muted small">
                            {{ \App\Services\Tax\TaxCalculator::formatRate($muc['rate']) }}
                            trên <x-site.money :amount="(float) $muc['net']" />
                        </span>
                    </dt>
                    <dd><x-site.money :amount="(float) $muc['tax']" /></dd>
                </div>
            @endforeach

            <div>
                <dt>Tiền thuế GTGT</dt>
                <dd><x-site.money :amount="(float) $invoice->tax_total" /></dd>
            </div>

            <div>
                <dt>Tổng tiền thanh toán</dt>
                <dd><x-site.money :amount="(float) $invoice->grand_total" /></dd>
            </div>
        </dl>

        <p class="invoice-card__note">{{ $invoice->status->hint() }}</p>

    </div>
@endif
