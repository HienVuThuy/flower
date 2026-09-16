@props([
    'invoice' => null,
])

{{-- DỮ LIỆU HOÁ ĐƠN GTGT CỦA MỘT ĐƠN. --}}
{{-- ⚠️ NÓI THẲNG RA ĐÂY CHƯA PHẢI HOÁ ĐƠN ĐÃ PHÁT HÀNH. --}}

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
