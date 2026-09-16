{{-- TRẢ GÓP CỦA ĐƠN (quản trị) — lịch, điều kiện đã chụp lúc tạo, và nút ghi kỳ khách trả tại cửa hàng. --}}
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

        <ul class="list-unstyled mb-0">
            @foreach($keHoach->payments as $ky)
                <li class="py-2 {{ $loop->last ? '' : 'border-bottom' }}" data-admin-ky="{{ $ky->sequence }}">
                    <div class="d-flex justify-content-between gap-2">
                        <span>
                            <strong>{{ $ky->nhan() }}</strong>
                            <span class="small text-muted">· hạn {{ $ky->due_on->format('d/m/Y') }}</span>
                        </span>
                        <span class="text-nowrap"><x-site.money :amount="(string) $ky->amount" /></span>
                    </div>

                    <div class="small mt-1">
                        @if($ky->daTra())
                            Đã trả <x-site.time :at="$ky->paid_at" format="d/m/Y H:i" />
                            @if($ky->traTre()) <span class="text-danger">(trễ)</span> @endif
                        @elseif($keHoach->dangTra() && $kyToi && $kyToi->id === $ky->id)
                            @if($ky->quaHan()) <span class="text-danger d-block mb-1">Quá hạn</span> @endif
                            @can('tai-chinh')
                                <form method="POST" action="{{ route('admin.orders.installments.record', $order) }}"
                                      onsubmit="return confirm('Ghi nhận đã thu {{ \App\Services\Shop\Money::format((string) $ky->amount) }} tại cửa hàng cho {{ mb_strtolower($ky->nhan()) }}?');">
                                    @csrf
                                    <input type="hidden" name="ky_id" value="{{ $ky->id }}">
                                    <button type="submit" class="btn btn-outline-admin btn-sm" data-ghi-ky>Ghi đã thu tại cửa hàng</button>
                                </form>
                            @else
                                <span class="text-muted">Ghi thu thuộc quyền tài chính</span>
                            @endcan
                        @elseif($ky->quaHan())
                            <span class="text-danger">Quá hạn</span>
                        @else
                            <span class="text-muted">Chưa trả</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
