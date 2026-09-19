@extends('layouts.admin')

@section('title', 'Báo cáo doanh thu')

@section('content')

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
    $bangKy = [
        ['Doanh thu theo năm', 'Năm', $theoNam, fn ($k) => $k],
        ['Doanh thu theo tháng', 'Tháng', $theoThang, fn ($k) => \Carbon\Carbon::createFromFormat('!Y-m', $k)->format('m/Y')],
        ['Doanh thu theo ngày', 'Ngày', $theoNgay, fn ($k) => \Carbon\Carbon::createFromFormat('!Y-m-d', $k)->format('d/m/Y')],
    ];
@endphp

<div class="mb-4">
    <h1 class="admin-page-title">Báo cáo doanh thu</h1>
    <p class="admin-page-subtitle">
        Toàn thời gian. Doanh thu chỉ tính đơn đã giao, theo ngày đặt (giờ Việt Nam); doanh thu thuần = doanh thu − tiền đã hoàn + tiền khách bù khi đổi hàng.
        Cùng định nghĩa với trang Phân tích.
    </p>
</div>

<x-admin.nhom-tab ten="bao-cao" />

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Tổng số đơn hàng" :note="$tongQuan['don_da_giao'] . ' đơn đã giao'">
            <span data-tong-don>{{ number_format($tongQuan['tong_don'], 0, ',', '.') }}</span>
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Tổng số khách hàng" note="Tài khoản vai trò khách hàng.">
            <span data-tong-khach>{{ number_format($tongQuan['tong_khach'], 0, ',', '.') }}</span>
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Doanh thu" note="Gồm phí giao hàng của đơn đã giao.">
            <span data-doanh-thu>{{ $tien($tongQuan['doanh_thu']) }}</span>
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Doanh thu thuần" :note="'Trừ ' . $tien($tongQuan['da_hoan']) . ' hoàn tiền, cộng ' . $tien($tongQuan['bu_doi_hang']) . ' khách bù đổi hàng.'">
            <span class="text-success" data-thuan>{{ $tien($tongQuan['thuan']) }}</span>
        </x-admin.kpi>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="admin-panel h-100">
            <div class="p-3 border-bottom">
                <h2 class="h6 fw-bold mb-0">Doanh thu theo danh mục</h2>
                <small class="text-muted">Tiền hàng sau giảm giá từng dòng, không gồm phí giao; chỉ đơn đã giao.</small>
            </div>
            <div class="table-responsive">
                <table class="admin-table align-middle mb-0">
                    <thead><tr><th>Danh mục</th><th class="text-end">Số lượng bán</th><th class="text-end">Số đơn</th><th class="text-end">Doanh thu</th><th class="text-end">Tỉ lệ</th></tr></thead>
                    <tbody>
                        @forelse($danhMuc['dong'] as $d)
                            <tr>
                                <td>{{ $d['ten'] }}</td>
                                <td class="text-end">{{ number_format($d['so_luong'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($d['so_don'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ $tien($d['doanh_thu']) }}</td>
                                <td class="text-end">{{ $d['ti_le'] !== null ? $d['ti_le'] . '%' : '—' }}</td>
                            </tr>
                        @empty
                            <x-admin.empty-row :colspan="5" empty="Chưa có doanh thu." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-panel h-100">
            <div class="p-3 border-bottom">
                <h2 class="h6 fw-bold mb-0">Doanh thu theo phương thức thanh toán</h2>
                <small class="text-muted">Chỉ đơn đã giao.</small>
            </div>
            <div class="table-responsive">
                <table class="admin-table align-middle mb-0">
                    <thead><tr><th>Phương thức</th><th class="text-end">Số đơn</th><th class="text-end">Doanh thu</th></tr></thead>
                    <tbody>
                        @foreach($phuongThuc as $p)
                            <tr>
                                <td>{{ $p['phuong_thuc']->label() }}</td>
                                <td class="text-end">{{ number_format($p['so_don'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ $tien($p['doanh_thu']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@foreach($bangKy as [$tieuDe, $nhan, $dong, $hien])
    <div class="admin-panel mb-4">
        <div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-0">{{ $tieuDe }}</h2></div>
        <div class="table-responsive" @if($nhan === 'Ngày') style="max-height: 480px; overflow-y: auto" @endif>
            <table class="admin-table align-middle mb-0">
                <thead><tr><th>{{ $nhan }}</th><th class="text-end">Số đơn đã giao</th><th class="text-end">Doanh thu</th><th class="text-end">Đã hoàn</th><th class="text-end">Thuần</th></tr></thead>
                <tbody>
                    @forelse($dong as $d)
                        <tr>
                            <td>{{ $hien($d['ky']) }}</td>
                            <td class="text-end">{{ number_format($d['so_don'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ $tien($d['doanh_thu']) }}</td>
                            <td class="text-end">{{ $tien($d['hoan']) }}</td>
                            <td class="text-end fw-semibold">{{ $tien($d['thuan']) }}</td>
                        </tr>
                    @empty
                        <x-admin.empty-row :colspan="5" empty="Chưa có doanh thu." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endforeach

@endsection
