@extends('layouts.admin')

@section('title', 'Phân tích khách hàng')

@section('content')

@include('admin.analytics._header', [
    'tieuDe' => 'Khách hàng',
    'moTa' => 'Doanh thu từ khách mới hay khách quay lại, và những giỏ hàng bị bỏ dở.',
])

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
@endphp

<h2 class="admin-section-title">1. Khách mới và khách quay lại</h2>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <x-admin.kpi label="Đơn đầu tiên của khách"
                     :note="$khach['moi']['khach'] . ' khách mua lần đầu trong kỳ · ' . $khach['moi']['don'] . ' đơn'">
            {{ $tien($khach['moi']['doanh_thu']) }}
        </x-admin.kpi>
    </div>
    <div class="col-12 col-md-4">
        <x-admin.kpi label="Đơn của khách quay lại"
                     :note="$khach['quay_lai']['khach'] . ' khách đã từng mua trước đó · ' . $khach['quay_lai']['don'] . ' đơn'">
            {{ $tien($khach['quay_lai']['doanh_thu']) }}
        </x-admin.kpi>
    </div>
    <div class="col-12 col-md-4">
        <x-admin.kpi label="Tỉ lệ mua lại (toàn bộ lịch sử)"
                     :note="$khach['khach_mua_lai'] . ' trên ' . $khach['khach_co_don'] . ' khách có tài khoản đã mua từ 2 đơn trở lên'">
            @if($khach['ti_le_mua_lai'] === null)
                <span class="admin-page-subtitle">chưa tính được</span>
            @else
                {{ number_format($khach['ti_le_mua_lai'], 1, ',', '.') }}%
            @endif
        </x-admin.kpi>
    </div>
</div>

<div class="admin-panel p-3 mb-4">
    <p class="admin-page-subtitle small mb-0">
        "Đơn đầu tiên" là đơn đã giao đầu tiên của tài khoản đó <strong>trên toàn bộ lịch sử</strong>, không phải trong kỳ —
        khách mua từ năm ngoái mà tháng này mới quay lại vẫn là khách quay lại.
        Tỉ lệ mua lại tính trên toàn bộ lịch sử vì trong một kỳ ngắn gần như không ai kịp mua lần hai.
        @if($khach['vang_lai']['don'] > 0)
            <br>{{ $khach['vang_lai']['don'] }} đơn ({{ $tien($khach['vang_lai']['doanh_thu']) }}) của
            <strong>khách vãng lai</strong> không xếp được vào nhóm nào: không có tài khoản thì không có gì nối hai đơn của cùng một người.
        @endif
    </p>
</div>

<h2 class="admin-section-title">2. Giỏ hàng bỏ dở</h2>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Giỏ bỏ dở" :note="'Còn hàng, không động tới quá ' . \App\Services\Analytics\AbandonedCarts::BO_SAU_GIO . ' giờ'">
            {{ $gioBoDo['so_gio'] }}
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Số món trong các giỏ đó">
            {{ $gioBoDo['so_mon'] }}
        </x-admin.kpi>
    </div>
    <div class="col-12 col-lg-3">
        <x-admin.kpi label="Giá trị nếu mua theo giá hôm nay" note="Giỏ không lưu giá lúc bỏ hàng vào.">
            {{ $tien($gioBoDo['tong_gia_tri']) }}
        </x-admin.kpi>
    </div>
    <div class="col-12 col-lg-3">
        <x-admin.kpi label="Theo độ lâu"
                     :note="collect($gioBoDo['theo_do_lau'])->map(fn ($n, $k) => $k . ': ' . $n)->implode(' · ')">
            {{ $gioBoDo['vang_lai'] }} <span class="admin-page-subtitle">giỏ vãng lai</span>
        </x-admin.kpi>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="admin-panel p-4 h-100">
            <h3 class="h6 fw-bold mb-2">Từng giỏ</h3>

            @if($gioBoDo['gio']->isEmpty())
                <p class="analytics-empty mb-0">Không có giỏ nào đang bị bỏ dở.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Khách</th>
                                <th>Hàng trong giỏ</th>
                                <th class="text-end text-nowrap">Giá trị</th>
                                <th class="text-end text-nowrap">Bỏ từ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($gioBoDo['gio'] as $g)
                                <tr>
                                    <td>
                                        {{ $g['khach'] }}
                                        @if($g['email'])
                                            <div class="admin-page-subtitle small">{{ $g['email'] }}</div>
                                        @endif
                                    </td>
                                    <td class="small">{{ implode(', ', $g['mat_hang']) }}</td>
                                    <td class="text-end text-nowrap">
                                        {{ $tien($g['gia_tri']) }}
                                        @if($g['khong_dinh_gia'] > 0)
                                            <div class="admin-page-subtitle small">+{{ $g['khong_dinh_gia'] }} món giá liên hệ</div>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <x-site.time :at="$g['lan_cuoi']" format="d/m H:i" />
                                        <div class="admin-page-subtitle small">{{ $g['so_ngay'] }} ngày</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.bars
                title="Hay bị bỏ lại nhất"
                note="Số giỏ bỏ dở có món này."
                empty="Không có dữ liệu."
                :rows="$gioBoDo['theo_san_pham']->map(fn ($d) => ['label' => $d['ten'], 'value' => $d['so_gio']])" />
        </div>
    </div>
</div>

<div class="admin-panel p-3 mb-4">
    <p class="admin-page-subtitle small mb-0">
        Giỏ của khách có tài khoản không tính nếu họ đã đặt một đơn sau lần sửa giỏ cuối cùng — phần còn lại là món họ chọn không mua.
        Giỏ của khách vãng lai không loại được theo cách đó, vì đơn hàng không lưu phiên duyệt web.
        Không có nút gửi email nhắc: hệ thống chưa ghi nhận khách nào đồng ý nhận thư tiếp thị.
    </p>
</div>

@endsection
