{{--
    TRẢ GÓP CỦA ĐƠN (quản trị) — lịch, điều kiện đã chụp lúc tạo, và nút ghi
    kỳ khách trả tại cửa hàng. Chỉ kỳ chưa trả sớm nhất có nút: dịch vụ cũng
    chặn ghi nhảy kỳ, nút chỉ không bày ra thứ bấm vào báo lỗi.
--}}
@if($order->installmentPlan)
    @php
        $keHoach = $order->installmentPlan;
        $kyToi = $keHoach->kyKeTiep();
    @endphp

    <div class="admin-panel p-4 mb-4" data-admin-tra-gop="{{ $keHoach->status->value }}">
        <h2 class="h6 fw-bold mb-3">Trả góp</h2>

        <p class="mb-2">
            <span class="status-pill status-pill--{{ $keHoach->status->badge() }}">{{ $keHoach->status->label() }}</span>
        </p>

        <p class="admin-page-subtitle small mb-2">
            {{ $keHoach->period_count }} kỳ, mỗi kỳ {{ $keHoach->period_days }} ngày, ân hạn {{ $keHoach->grace_days }} ngày,
            trả trước {{ $keHoach->down_payment_percent }}%. Điểm tín dụng lúc xét: {{ $keHoach->credit_score }}.
        </p>

        <p class="mb-3">
            Đã thu <strong><x-site.money :amount="$keHoach->daTra()" /></strong>
            / <x-site.money :amount="(string) $keHoach->total_amount" />
            — còn <x-site.money :amount="$keHoach->conLai()" />.
        </p>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Kỳ</th>
                        <th>Hạn</th>
                        <th class="text-end">Số tiền</th>
                        <th>Tình trạng</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($keHoach->payments as $ky)
                        <tr data-admin-ky="{{ $ky->sequence }}">
                            <td>{{ $ky->nhan() }}</td>
                            <td>{{ $ky->due_on->format('d/m/Y') }}</td>
                            <td class="text-end"><x-site.money :amount="(string) $ky->amount" /></td>
                            <td>
                                @if($ky->daTra())
                                    Đã trả <x-site.time :at="$ky->paid_at" format="d/m/Y H:i" />
                                    @if($ky->traTre()) <span class="text-danger small">(trễ)</span> @endif
                                @elseif($keHoach->dangTra() && $kyToi && $kyToi->id === $ky->id)
                                    @if($ky->quaHan()) <span class="text-danger small d-block">Quá hạn</span> @endif
                                    @can('tai-chinh')
                                        <form method="POST" action="{{ route('admin.orders.installments.record', $order) }}"
                                              onsubmit="return confirm('Ghi nhận đã thu {{ \App\Services\Shop\Money::format((string) $ky->amount) }} tại cửa hàng cho {{ mb_strtolower($ky->nhan()) }}?');">
                                            @csrf
                                            <input type="hidden" name="ky_id" value="{{ $ky->id }}">
                                            <button type="submit" class="btn btn-outline-admin btn-sm" data-ghi-ky>Ghi đã thu tại cửa hàng</button>
                                        </form>
                                    @else
                                        <span class="small text-muted">Ghi thu thuộc quyền tài chính</span>
                                    @endcan
                                @elseif($ky->quaHan())
                                    <span class="text-danger small">Quá hạn</span>
                                @else
                                    <span class="small text-muted">Chưa trả</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
