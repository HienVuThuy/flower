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
                        : 'Sau khuyến mại và mã giảm giá, chưa gồm phí ship. Không gồm hoa tươi — lãi hoa tính theo lô ở phần cuối trang.'">
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
                            <span>
                                {{ $d['ten'] }} <span class="text-muted">× {{ $d['so_luong'] }}</span>
                                @if($d['khong_theo_doi'] ?? false)
                                    <span class="d-block text-muted" data-khong-theo-doi>
                                        Đang tắt theo dõi tồn kho nên chưa lập phiếu nhập được —
                                        <a data-admin-link href="{{ route('admin.products.edit', $d['product_id']) }}">bật ở trang sản phẩm</a>.
                                    </span>
                                @endif
                            </span>
                            <span class="text-nowrap">{{ $tien($d['doanh_thu']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

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
                <div data-dong="chi-phi-qua">
                    <dt>Giá vốn quà tặng</dt>
                    <dd>
                        {{ $tien($loi['chi_phi_qua']['tien']) }}
                        @if($loi['chi_phi_qua']['so_dong_chua_gia'] > 0)
                            <span class="d-block admin-page-subtitle small">
                                {{ $loi['chi_phi_qua']['so_dong_chua_gia'] }} dòng quà chưa có giá nhập (vật phẩm tặng riêng hoặc chưa lập phiếu nhập)
                            </span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Chi phí vận hành đã ghi</dt>
                    <dd>
                        @if($chiPhi['so_khoan'] === 0)
                            <span class="admin-page-subtitle">chưa ghi</span>
                        @else
                            {{ $tien($chiPhi['tong']) }}
                        @endif
                    </dd>
                </div>
            </dl>
            <p class="admin-page-subtitle small mt-2 mb-0">
                Lãi ròng ước tính theo tháng ở
                <a data-admin-link href="{{ route('admin.expenses.index') }}">Sổ thu chi</a>.
            </p>
        </div>
    </div>
</div>

<h2 class="admin-section-title mt-4">Hoa tươi — tính theo lô</h2>

<div class="admin-panel p-4 mb-3">
    <p class="admin-page-subtitle">
        Hoa không đếm theo cành mà theo lô. Giá vốn hoa của kỳ này là
        <strong>tiền các lô đã đóng trong kỳ</strong> — không phải tiền các lô đã mua:
        lô mua cuối tháng mà dùng sang tháng sau thì tiền của nó thuộc tháng sau.
    </p>

    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <x-admin.kpi label="Doanh thu hoa" note="Chỉ đơn đã giao, chưa gồm VAT.">
                <x-site.money :amount="$hoa['doanh_thu']" />
            </x-admin.kpi>
        </div>

        <div class="col-6 col-lg-3">
            <x-admin.kpi label="Giá vốn hoa"
                         :note="$hoa['so_lo_dong'] . ' lô đã đóng trong kỳ'">
                <x-site.money :amount="$hoa['gia_von']" />
            </x-admin.kpi>
        </div>

        <div class="col-6 col-lg-3">
            <x-admin.kpi label="Lãi gộp hoa" note="Doanh thu hoa trừ tiền lô đã đóng.">
                @if($hoa['lai_gop'] === null)
                    <span class="admin-page-subtitle">chưa tính được</span>
                @else
                    <x-site.money :amount="$hoa['lai_gop']" />
                @endif
            </x-admin.kpi>
        </div>

        <div class="col-6 col-lg-3">
            <x-admin.kpi label="Hao hụt trung bình"
                         note="Trên tổng số lượng các lô đã đóng, không phải trung bình các tỉ lệ.">
                @if($hoa['hao_hut_trung_binh'] === null)
                    <span class="admin-page-subtitle">chưa có</span>
                @else
                    {{ number_format($hoa['hao_hut_trung_binh'], 1, ',', '.') }}%
                @endif
            </x-admin.kpi>
        </div>
    </div>

    @if($hoa['lai_gop'] === null)
        <div class="alert alert-warning mt-3 mb-0">
            <strong>Chưa đóng lô nào trong kỳ này.</strong>
            Tiền mua hoa chỉ vào giá vốn khi lô được đóng — đó là lúc người bán nói
            “lô này hết rồi”. Chưa đóng thì hoa vẫn còn trong xô, chưa thành chi phí.
            <a data-admin-link href="{{ route('admin.flower-lots.index') }}" class="alert-link">Xem lô hoa</a>
        </div>
    @endif

    @if($hoa['lo_qua_han'] > 0)
        <div class="alert alert-warning mt-3 mb-0">
            <strong>{{ $hoa['lo_qua_han'] }} lô mở quá lâu chưa đóng.</strong>
            Chừng nào chưa đóng, giá vốn hoa đang <em>thấp hơn</em> sự thật và lãi gộp hoa
            đang <em>cao hơn</em> sự thật.
            <a data-admin-link href="{{ route('admin.flower-lots.index') }}" class="alert-link">Đóng lô</a>
        </div>
    @endif

    @if($hoa['so_lo_con_mo'] > 0)
        <p class="admin-page-subtitle small mt-3 mb-0">
            Đang có {{ $hoa['so_lo_con_mo'] }} lô còn dùng, trị giá
            {{ $tien($hoa['tien_lo_con_mo']) }} — số này <strong>chưa</strong> tính vào giá vốn kỳ nào.
        </p>
    @endif
</div>

@endsection
