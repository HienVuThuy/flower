{{-- Bảng tiền phiếu chăm hộ — mọi con số do máy chủ tính (BoardingPricing, BoardingBooking::tongTien). --}}
@php use App\Services\Shop\Money; @endphp
<dl class="boarding-money">
    <dt>{{ $phieu->daChotGia() ? 'Giá chốt cho cây này' : 'Giá tham khảo (chờ cửa hàng chốt)' }}</dt>
    <dd>{{ Money::format($phieu->monthly_price) }}/tháng · {{ Money::format($phieu->yearly_price) }}/năm</dd>

    <dt>Tiền chăm{{ $phieu->returned_on ? '' : ' (tạm tính)' }}</dt>
    <dd>{{ Money::format($phieu->care_amount) }}</dd>

    @if(bccomp($phieu->tienLamThem(), '0', 2) > 0)
        <dt>Việc làm thêm đã đồng ý</dt>
        <dd>{{ Money::format($phieu->tienLamThem()) }}</dd>
    @endif

    @if((float) $phieu->handover_fee > 0)
        <dt>Phí đến lấy / trả cây</dt>
        <dd>{{ Money::format($phieu->handover_fee) }}</dd>
    @endif

    @if((float) $phieu->rush_fee > 0)
        <dt>Phí nhận gấp</dt>
        <dd>{{ Money::format($phieu->rush_fee) }}</dd>
    @endif

    @if((float) $phieu->adjustment != 0)
        <dt>Điều chỉnh{{ $phieu->adjustment_reason ? ' — ' . $phieu->adjustment_reason : '' }}</dt>
        <dd>{{ (float) $phieu->adjustment > 0 ? '+' : '−' }}{{ Money::format(ltrim((string) $phieu->adjustment, '-')) }}</dd>
    @endif

    <dt class="boarding-money__tong">Tổng</dt>
    <dd class="boarding-money__tong">{{ Money::format($phieu->tongTien()) }}</dd>

    <dt>Đã thanh toán</dt>
    <dd>{{ Money::format($phieu->paid_amount) }}</dd>

    @foreach($phieu->payments as $tra)
        <dt class="boarding-money__phu">{{ $tra->paid_at->format('d/m/Y') }} · {{ $tra->method->label() }}</dt>
        <dd class="boarding-money__phu">{{ Money::format((string) $tra->amount) }}</dd>
    @endforeach

    @php $con = $phieu->conLai(); @endphp
    @if(bccomp($con, '0', 2) !== 0)
        <dt>{{ bccomp($con, '0', 2) > 0 ? 'Còn phải trả' : 'Cửa hàng trả lại bạn' }}</dt>
        <dd><strong>{{ Money::format(ltrim($con, '-')) }}</strong></dd>
    @endif
</dl>
