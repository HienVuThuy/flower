{{-- ---------- Trả góp & điểm tín dụng ---------- --}}
@php
    $tinDung = app(\App\Services\Credit\CreditScore::class)->cua($user);
    $xet = app(\App\Services\Installment\InstallmentPolicy::class)->xet($user, \App\Services\Installment\InstallmentSettings::donToiThieu());
    $cacKeHoach = \App\Models\InstallmentPlan::query()->where('user_id', $user->id)->with(['order:id,order_number', 'payments'])->latest('id')->limit(10)->get();
@endphp

<div class="surface-card p-4">

    <h2 class="text-h4 mb-1">Điểm tín dụng</h2>
    <p class="text-caption mb-3">
        Điểm tín dụng đo mức tin cậy thanh toán của bạn — không phải tiền, không đổi quà, không ảnh hưởng hạng thành viên hay điểm thưởng.
        Nó quyết định bạn trả góp được mấy kỳ và trả trước bao nhiêu.
    </p>

    <p class="points-balance mb-1" data-diem-tin-dung="{{ $tinDung['diem'] }}">
        <span class="points-balance__so">{{ $tinDung['diem'] }}</span> <span class="text-caption">/ 100</span>
    </p>

    <p class="text-caption mb-3" data-tra-gop-xet>
        @if($xet['duoc'])
            Với điểm này, đơn từ {{ \App\Services\Shop\Money::format(\App\Services\Installment\InstallmentSettings::donToiThieu()) }}
            trả góp được tối đa <strong>{{ $xet['ky_toi_da'] }} kỳ</strong>, trả trước <strong>{{ $xet['tra_truoc'] }}%</strong>.
        @else
            {{ $xet['ly_do'] }}
        @endif
    </p>

    <h3 class="text-h5 mb-2">Điểm được tính từ</h3>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr><th scope="col">Yếu tố</th><th scope="col" class="text-end">Số lần</th><th scope="col" class="text-end">Điểm</th></tr>
            </thead>
            <tbody>
                <tr><td>Điểm khởi đầu</td><td class="text-end">—</td><td class="text-end">{{ \App\Services\Credit\CreditScore::CO_BAN }}</td></tr>
                @foreach($tinDung['yeu_to'] as $yt)
                    <tr data-yeu-to="{{ $yt['ma'] }}">
                        <td>{{ $yt['nhan'] }}</td>
                        <td class="text-end">{{ $yt['so_lan'] }}</td>
                        <td class="text-end">{{ $yt['diem'] > 0 ? '+' . $yt['diem'] : $yt['diem'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h3 class="text-h5 mt-4 mb-2">Kế hoạch trả góp của bạn</h3>
    @if($cacKeHoach->isEmpty())
        <p class="text-caption mb-0">Bạn chưa có kế hoạch trả góp nào. Chọn "Trả góp trước khi giao" ở bước thanh toán.</p>
    @else
        <ul class="list-unstyled mb-0">
            @foreach($cacKeHoach as $kh)
                <li class="mb-2">
                    @if($kh->order)
                        <a href="{{ route('shop.orders.show', $kh->order) }}">Đơn {{ $kh->order->order_number }}</a>
                    @endif
                    — <span class="status-pill status-pill--{{ $kh->status->badge() }}">{{ $kh->status->label() }}</span>
                    <span class="text-caption d-block">
                        Đã trả <x-site.money :amount="$kh->daTra()" /> / <x-site.money :amount="(string) $kh->total_amount" />
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

</div>
