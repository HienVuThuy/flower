@props(['transactions'])

{{-- NHẬT KÝ TỪNG LƯỢT THANH TOÁN — chỉ ở trang quản trị. --}}

@if($transactions->isNotEmpty())
    <div class="admin-panel p-4 mb-4">
        <h2 class="h6 fw-bold mb-3">Lượt thanh toán</h2>

        <div class="table-responsive">
            <table class="payment-log table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Thời điểm</th>
                        <th>Cổng</th>
                        <th>Trạng thái</th>
                        <th class="payment-log__amount">Số tiền</th>
                        <th>Mã giao dịch</th>
                        <th>MoMo trả về</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $tx)
                        <tr>
                            <td><x-site.time :at="$tx->created_at" format="d/m/Y H:i" /></td>
                            <td>
                                {{ $tx->gateway === \App\Services\Installment\InstallmentService::TAI_CUA_HANG ? 'Tại cửa hàng' : strtoupper($tx->gateway) }}
                                @if($tx->installmentPayment)
                                    <span class="d-block small text-muted">{{ $tx->installmentPayment->nhan() }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="status-pill status-pill--{{ $tx->status->badge() }}">
                                    {{ $tx->status->label() }}
                                </span>
                            </td>
                            <td class="payment-log__amount">
                                <x-site.money :amount="(float) $tx->amount" />
                            </td>
                            <td class="payment-log__code">
                                {{ $tx->transaction_id ?: '—' }}
                            </td>
                            <td class="text-muted small">
                                @if($tx->result_code !== null)
                                    [{{ $tx->result_code }}]
                                @endif
                                {{ $tx->message ?: '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
