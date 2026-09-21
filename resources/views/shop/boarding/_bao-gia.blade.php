{{-- CÁC LẦN BÁO GIÁ của phiếu — bản mới nhất mở sẵn, bản cũ thu gọn để xem lại quá trình thương lượng. --}}
@php use App\Enums\BoardingQuoteStatus; use App\Services\Shop\Money; @endphp

@foreach($phieu->quotes as $bg)
    <details class="boarding-quote-card" data-bao-gia="{{ $bg->version }}" @if($loop->first) open @endif>
        <summary>
            <strong>Báo giá lần {{ $bg->version }}</strong>
            · {{ Money::format((string) $bg->total) }}
            <span class="badge text-bg-{{ $bg->status->tone() }}">{{ $bg->status->label() }}</span>
            @if($bg->status === BoardingQuoteStatus::DangCho && $bg->hetHan())
                <span class="badge text-bg-danger">Hết hạn</span>
            @endif
        </summary>

        <table class="boarding-quote-card__bang">
            @foreach($bg->lines as $dong)
                <tr>
                    <td>{{ $dong['nhan'] }}</td>
                    <td class="text-end text-nowrap">{{ Money::format($dong['tien']) }}</td>
                </tr>
            @endforeach
            <tr class="boarding-quote-card__tong">
                <td>Tổng</td>
                <td class="text-end text-nowrap">{{ Money::format((string) $bg->total) }}</td>
            </tr>
        </table>

        <p class="text-caption mb-0">
            Gửi <x-site.time :at="$bg->created_at" format="d/m/Y H:i" />
            @if($bg->valid_until) · hiệu lực đến {{ $bg->valid_until->format('d/m/Y') }} @endif
            @if($bg->sender && ($laAdmin ?? false)) · {{ $bg->sender->name }} @endif
        </p>
        @if($bg->note)<p class="small mb-0 mt-1">Cửa hàng ghi: {{ $bg->note }}</p>@endif
        @if($bg->response_note)<p class="small mb-0 mt-1">Khách trả lời: {{ $bg->response_note }}</p>@endif
    </details>
@endforeach
