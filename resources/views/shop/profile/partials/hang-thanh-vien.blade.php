{{-- ---------- Hạng thành viên ---------- --}}
@php
    $dvHang = app(\App\Services\Loyalty\MemberTierResolver::class);
    $cuaToi = $dvHang->cua($user);
    $cacHang = $dvHang->tatCa();
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
    $nguongChung = app(\App\Services\Shipping\ShippingRates::class)->freeFrom();
    $phanTram = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
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

    <h3 class="text-h5 mb-2">Quyền lợi từng hạng</h3>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Hạng</th>
                    <th scope="col">Mua từ</th>
                    <th scope="col">Giảm tiền hàng</th>
                    <th scope="col">Miễn phí giao</th>
                    <th scope="col">Điểm thưởng thêm</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cacHang as $hang)
                    @php
                        $laHangToi = $cuaToi['hang']?->id === $hang->id;
                        $mienShip = $hang->free_shipping_from !== null && bccomp((string) $hang->free_shipping_from, $nguongChung, 2) < 0
                            ? (bccomp((string) $hang->free_shipping_from, '0', 2) === 0 ? 'Mọi đơn' : 'Đơn từ ' . $tien($hang->free_shipping_from))
                            : 'Đơn từ ' . $tien($nguongChung);
                    @endphp
                    <tr @if($laHangToi) class="fw-bold" data-hang-hien-tai @endif data-hang-dong="{{ $hang->code }}">
                        <td>{{ $hang->name }}@if($laHangToi) <span class="badge text-bg-secondary">hạng của bạn</span>@endif</td>
                        <td>{{ $tien($hang->min_spend) }}</td>
                        <td>{{ bccomp((string) $hang->discount_percent, '0', 2) > 0 ? $phanTram($hang->discount_percent) . '%' : '—' }}</td>
                        <td>{{ $mienShip }}</td>
                        <td>{{ $hang->bonus_points_percent > 0 ? '+' . $hang->bonus_points_percent . '%' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-caption mt-3 mb-0">
        Ưu đãi giảm tiền hàng không cộng dồn với mã giảm giá, trừ mã ghi rõ "cộng dồn với ưu đãi hạng".
    </p>

</div>
