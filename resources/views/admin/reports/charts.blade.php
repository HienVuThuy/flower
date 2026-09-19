@extends('layouts.admin')

@section('title', 'Biểu đồ doanh thu')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Biểu đồ doanh thu</h1>
    <p class="admin-page-subtitle">
        Doanh thu thuần của đơn đã giao, theo giờ Việt Nam. Biểu đồ vẽ bằng SVG ngay trên máy chủ — không tải thư viện ngoài, tắt mạng vẫn xem được.
    </p>
</div>

<x-admin.nhom-tab ten="bao-cao" />

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.bars
                title="Doanh thu theo danh mục"
                note="Tiền hàng, không gồm phí giao"
                format="tien"
                empty="Chưa có doanh thu."
                :rows="collect($danhMuc['dong'])->map(fn ($d) => ['label' => $d['ten'], 'value' => (float) $d['doanh_thu'], 'meta' => $d['so_luong'] . ' sp'])" />
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.line :points="$ngay" title="Doanh thu theo ngày (30 ngày)" note="Doanh thu thuần" format="tien" :slot="1" />
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.bars title="Doanh thu theo tháng (12 tháng)" note="Doanh thu thuần" format="tien"
                :rows="$thang->map(fn ($d) => ['label' => $d['label'], 'value' => $d['value']])" />
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.bars title="Doanh thu theo năm" note="Doanh thu thuần" format="tien" empty="Chưa có doanh thu."
                :rows="$nam->map(fn ($d) => ['label' => $d['ky'], 'value' => (float) $d['thuan'], 'meta' => $d['so_don'] . ' đơn'])" />
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.donut title="Doanh thu theo phương thức thanh toán" note="Đơn đã giao" unit="đ"
                :slices="$phuongThuc->values()->map(fn ($p, $i) => [
                    'label' => $p['phuong_thuc']->label(),
                    'value' => (float) $p['doanh_thu'],
                    'color' => 'var(--viz-step-' . ($i * 2 + 1) . ')',
                ])" />
        </div>
    </div>
</div>

@endsection
