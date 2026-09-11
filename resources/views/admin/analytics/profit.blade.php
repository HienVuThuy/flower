@extends('layouts.admin')

@section('title', 'Phân tích lợi nhuận')

@section('content')

@include('admin.analytics._header', [
    'tieuDe' => 'Lãi gộp',
    'moTa' => 'Chỉ tính trên phần doanh thu có giá vốn thật từ phiếu nhập kho. Phần còn lại được đếm và nói ra, không ước lượng.',
])

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
@endphp

@unless($loi['co_phieu_nhap_co_gia'])
    {{--
        CHƯA CÓ GIÁ VỐN NÀO THÌ NÓI THẲNG, VÀ NÓI CÁCH CÓ.

        In "lãi gộp 0₫, biên —" mà không giải thích thì người đọc tưởng cửa
        hàng bán hoà vốn.
    --}}
    <div class="alert alert-warning">
        <strong>Chưa tính được lãi.</strong>
        Chưa có phiếu nhập kho nào đã ghi sổ mà có điền giá vốn, nên hệ thống không biết cửa hàng đã mua hàng với giá bao nhiêu.
        Lập phiếu nhập có ghi giá (chưa gồm VAT đầu vào) và ghi sổ — lãi của những món đó sẽ hiện ở đây từ ngày nhập.
        <a data-admin-link href="{{ route('admin.stock-receipts.create') }}" class="alert-link">Lập phiếu nhập</a>
    </div>
@endunless

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Doanh thu hàng (đã trừ VAT nếu có số liệu)"
                     :note="$loi['dong_chua_tach_vat'] > 0
                        ? $loi['dong_chua_tach_vat'] . ' dòng thuộc đơn chưa có số liệu thuế — phần đó vẫn gồm VAT.'
                        : 'Sau khuyến mại và mã giảm giá, chưa gồm phí ship.'">
            {{ $tien($loi['doanh_thu']) }}
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Phần có giá vốn"
                     :note="$tien($loi['doanh_thu_co_gia_von']) . ' doanh thu tính được lãi'">
            @if($loi['ti_le_phu'] === null)
                <span class="admin-page-subtitle">chưa có doanh thu</span>
            @else
                <span class="{{ $loi['ti_le_phu'] < 80 ? 'text-danger' : '' }}">{{ number_format($loi['ti_le_phu'], 1, ',', '.') }}%</span>
            @endif
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Lãi gộp" :note="'Doanh thu có giá vốn trừ giá vốn ' . $tien($loi['gia_von'])">
            @if(bccomp($loi['doanh_thu_co_gia_von'], '0', 2) === 0)
                <span class="admin-page-subtitle">chưa tính được</span>
            @else
                {{ $tien($loi['lai_gop']) }}
            @endif
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Biên lãi gộp" note="Trên phần doanh thu có giá vốn, không phải toàn bộ.">
            @if($loi['bien'] === null)
                <span class="admin-page-subtitle">chưa tính được</span>
            @else
                {{ number_format($loi['bien'], 1, ',', '.') }}%
            @endif
        </x-admin.kpi>
    </div>
</div>

@if($loi['ti_le_phu'] !== null && $loi['ti_le_phu'] < 100)
    <div class="admin-panel p-3 mb-4">
        <p class="admin-page-subtitle small mb-0">
            <strong>{{ $loi['dong_khong_gia_von'] }} dòng hàng</strong> ({{ $tien($loi['doanh_thu_khong_gia_von']) }} doanh thu)
            không có giá vốn và <strong>không</strong> được tính vào lãi: món đó bán trước lần nhập có giá đầu tiên, hoặc chưa từng có phiếu nhập có giá.
            Lãi và biên ở trên chỉ nói về {{ number_format($loi['ti_le_phu'], 1, ',', '.') }}% doanh thu — không phải lãi của cả cửa hàng.
        </p>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="admin-panel p-4 h-100">
            <h3 class="h6 fw-bold mb-2">Lãi gộp theo sản phẩm</h3>

            @if($loi['theo_san_pham']->isEmpty())
                <p class="analytics-empty mb-0">Chưa có dòng hàng nào có giá vốn trong kỳ.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Mặt hàng</th>
                                <th class="text-end text-nowrap">SL</th>
                                <th class="text-end text-nowrap">Doanh thu</th>
                                <th class="text-end text-nowrap">Giá vốn</th>
                                <th class="text-end text-nowrap">Lãi gộp</th>
                                <th class="text-end text-nowrap">Biên</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loi['theo_san_pham'] as $d)
                                <tr>
                                    <td>{{ $d['ten'] }}</td>
                                    <td class="text-end text-nowrap">{{ $d['so_luong'] }}</td>
                                    <td class="text-end text-nowrap">{{ $tien($d['doanh_thu']) }}</td>
                                    <td class="text-end text-nowrap">{{ $tien($d['gia_von']) }}</td>
                                    <td class="text-end fw-bold {{ bccomp($d['lai_gop'], '0', 2) < 0 ? 'text-danger' : '' }}">{{ $tien($d['lai_gop']) }}</td>
                                    <td class="text-end text-nowrap">{{ $d['bien'] === null ? '—' : number_format($d['bien'], 1, ',', '.') . '%' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-panel p-4 mb-3">
            <h3 class="h6 fw-bold mb-1">Bán chạy mà chưa có giá vốn</h3>
            <p class="admin-page-subtitle small">Nhập giá vốn cho những món này trước — chúng che nhiều doanh thu nhất.</p>

            @if($loi['can_nhap_gia_von']->isEmpty())
                <p class="analytics-empty mb-0">Mọi món bán trong kỳ đều có giá vốn.</p>
            @else
                <ul class="list-unstyled small mb-0">
                    @foreach($loi['can_nhap_gia_von'] as $d)
                        <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
                            <span>{{ $d['ten'] }} <span class="text-muted">× {{ $d['so_luong'] }}</span></span>
                            <span class="text-nowrap">{{ $tien($d['doanh_thu']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{--
            CHI PHÍ CÓ SỐ LIỆU — ĐỨNG CẠNH, KHÔNG TRỪ VÀO.

            Trừ hai khoản này rồi gọi kết quả là "lãi ròng" là bỏ qua mặt bằng,
            nhân công, phí cổng thanh toán — những khoản hệ thống không có số.
            Con số đó trông như lãi ròng mà không phải.
        --}}
        <div class="admin-panel p-4">
            <h3 class="h6 fw-bold mb-1">Chi phí khác có số liệu</h3>
            <p class="admin-page-subtitle small">Hiện ra để đối chiếu, <strong>không</strong> trừ vào lãi gộp.</p>

            <dl class="admin-detail-list mb-0">
                <div>
                    <dt>Đã hoàn tiền cho đơn đã giao</dt>
                    <dd>{{ $tien($loi['hoan_tien']) }}</dd>
                </div>
                <div>
                    <dt>Cửa hàng bù ship</dt>
                    <dd>
                        @if($buShip['tinh_duoc'] === 0)
                            <span class="admin-page-subtitle">chưa tính được</span>
                        @else
                            {{ $tien($buShip['chenh']) }}
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</div>

@endsection
