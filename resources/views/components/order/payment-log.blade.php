@props(['transactions'])

{{--
    NHẬT KÝ TỪNG LƯỢT THANH TOÁN — chỉ ở trang quản trị.

    Đây là chỗ trả lời câu "khách bảo đã bị trừ tiền mà đơn chưa ghi
    nhận": mỗi lần thử là một dòng, kèm mã giao dịch MoMo cấp và câu MoMo
    trả về. Không có bảng này thì chỉ còn cột `payment_status` của đơn —
    một chữ duy nhất, không nói được đã thử mấy lần và hỏng ở đâu.
--}}

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
                            <td>{{ strtoupper($tx->gateway) }}</td>
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
