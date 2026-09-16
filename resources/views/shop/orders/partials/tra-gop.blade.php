{{-- TRẢ GÓP CỦA ĐƠN — khách thấy lịch, đã trả bao nhiêu, kỳ nào trả tiếp. --}}
@php
    $keHoach = $order->installmentPlan;
    $kyToi = $keHoach->kyKeTiep();
    $hotline = \App\Models\Setting::get('site_hotline');
@endphp

<div class="order-refunds mb-4" data-tra-gop="{{ $keHoach->status->value }}">
    <h3 class="text-h5 mb-2">Trả góp</h3>

    <p class="mb-2">
        <span class="status-pill status-pill--{{ $keHoach->status->badge() }}">{{ $keHoach->status->label() }}</span>
    </p>

    <p class="text-caption mb-2" data-tra-gop-da-tra="{{ $keHoach->daTra() }}">
        Đã trả <strong><x-site.money :amount="$keHoach->daTra()" /></strong>
        trên <x-site.money :amount="(string) $keHoach->total_amount" />.
        @if($keHoach->dangTra())
            Cửa hàng giữ hàng và giao khi bạn trả đủ. Quá hạn một kỳ hơn {{ $keHoach->grace_days }} ngày thì đơn tự huỷ và tiền đã trả được hoàn lại.
        @elseif($keHoach->status === \App\Enums\InstallmentStatus::VoNo)
            Kế hoạch đã huỷ vì quá hạn. Cửa hàng hoàn lại số tiền bạn đã trả.
        @endif
    </p>

    <div class="table-responsive">
        <table class="table table-sm align-middle mb-2">
            <thead>
                <tr>
                    <th scope="col">Kỳ</th>
                    <th scope="col">Hạn</th>
                    <th scope="col" class="text-end">Số tiền</th>
                    <th scope="col">Tình trạng</th>
                </tr>
            </thead>
            <tbody>
                @foreach($keHoach->payments as $ky)
                    <tr data-ky="{{ $ky->sequence }}">
                        <td>{{ $ky->nhan() }}</td>
                        <td>{{ $ky->due_on->format('d/m/Y') }}</td>
                        <td class="text-end"><x-site.money :amount="(string) $ky->amount" /></td>
                        <td>
                            @if($ky->daTra())
                                Đã trả <x-site.time :at="$ky->paid_at" format="d/m" />
                            @elseif($ky->quaHan())
                                <span class="text-danger">Quá hạn</span>
                            @else
                                Chưa trả
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($keHoach->dangTra() && $kyToi)
        <p class="mb-2">
            Trả tiếp: <strong>{{ $kyToi->nhan() }}</strong> —
            <x-site.money :amount="(string) $kyToi->amount" />, hạn {{ $kyToi->due_on->format('d/m/Y') }}.
        </p>

        @if(\App\Enums\PaymentMethod::Momo->isConfigured())
            <div class="momo-retry mb-2">
                @foreach(\App\Enums\MomoFlow::cases() as $cach)
                    <a href="{{ route('shop.orders.tra-gop.momo', [$order, 'cach' => $cach->value]) }}"
                       class="btn btn-sm {{ $loop->first ? 'btn-primary-brand' : 'btn-secondary-brand' }}" data-tra-gop-momo>
                        <x-site.icon :name="$cach->icon()" />
                        {{ $cach->label() }}
                    </a>
                @endforeach
            </div>
        @endif

        <p class="text-caption mb-0">
            Hoặc trả tại cửa hàng{{ $hotline ? ' — gọi ' . $hotline . ' để hẹn' : '' }}.
        </p>
    @endif
</div>
