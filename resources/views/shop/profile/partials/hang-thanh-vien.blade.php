{{-- ---------- Hạng thành viên ---------- --}}
@php
    $dvHang = app(\App\Services\Loyalty\MemberTierResolver::class);
    $cuaToi = $dvHang->cua($user);
    $cacHang = $dvHang->tatCa();
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
@endphp

<div class="surface-card p-4">

    <h2 class="text-h4 mb-1">Hạng thành viên</h2>
    <p class="text-caption mb-3">
        Hạng tính theo tiền hàng của các đơn <strong>đã giao</strong> — không gồm phí vận chuyển, trừ phần đã hoàn tiền.
        Dùng điểm hay đổi voucher không làm tụt hạng.
    </p>

    <p class="points-balance mb-1" data-hang="{{ $cuaToi['hang']?->code }}">
        <span class="points-balance__so">{{ $cuaToi['hang']?->name ?? '—' }}</span>
    </p>

    <p class="text-caption mb-4" data-chi-tieu="{{ $cuaToi['chi_tieu'] }}">
        Đã mua {{ $tien($cuaToi['chi_tieu']) }}.
        @if($cuaToi['ke_tiep'])
            Còn <strong>{{ $tien($cuaToi['con_thieu']) }}</strong> nữa để lên hạng {{ $cuaToi['ke_tiep']->name }}.
        @else
            Bạn đang ở hạng cao nhất.
        @endif
    </p>

    {{-- Chỉ liệt kê quyền lợi ĐANG CHẠY THẬT — không bày quyền lợi chưa có. --}}
    <h3 class="text-h5 mb-2">Quyền lợi từng hạng</h3>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Hạng</th>
                    <th scope="col">Mua từ</th>
                    <th scope="col">Điểm thưởng thêm khi đơn được giao</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cacHang as $hang)
                    @php($laHangToi = $cuaToi['hang']?->id === $hang->id)
                    <tr @if($laHangToi) class="fw-bold" data-hang-hien-tai @endif>
                        <td>{{ $hang->name }}@if($laHangToi) <span class="badge text-bg-secondary">hạng của bạn</span>@endif</td>
                        <td>{{ $tien($hang->min_spend) }}</td>
                        <td>{{ $hang->bonus_points_percent > 0 ? '+' . $hang->bonus_points_percent . '%' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
