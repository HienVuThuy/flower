@extends('layouts.admin')

@section('title', 'Phân tích doanh thu')

@section('content')

@include('admin.analytics._header', [
    'tieuDe' => 'Doanh thu theo chiều',
    'moTa' => 'Tiền đến từ danh mục nào, tỉnh nào, ngày / tháng / năm nào, và khách đặt vào giờ nào. Chỉ đơn đã giao, trừ bản đồ khung giờ.',
])

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
@endphp

<h2 class="admin-section-title">1. Theo danh mục</h2>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.bars
                title="Doanh thu hàng theo danh mục"
                note="Sau khuyến mại và mã giảm giá, đã gồm VAT."
                format="tien"
                empty="Chưa có đơn nào giao xong trong kỳ này."
                :rows="$danhMuc['dong']->map(fn ($d) => [
                    'label' => $d['ten'],
                    'value' => (float) $d['doanh_thu'],
                    'meta' => $d['ti_le'] === null ? null : number_format($d['ti_le'], 1, ',', '.') . '%',
                ])" />
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">
            <h3 class="h6 fw-bold mb-2">Bảng số</h3>

            @if($danhMuc['dong']->isEmpty())
                <p class="analytics-empty mb-0">Chưa có dữ liệu trong kỳ này.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead>
                            <tr>
                                <th>Danh mục</th>
                                <th class="text-end text-nowrap">Doanh thu</th>
                                <th class="text-end text-nowrap">Tỉ lệ</th>
                                <th class="text-end text-nowrap">SL</th>
                                <th class="text-end text-nowrap">Đơn</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($danhMuc['dong'] as $d)
                                <tr>
                                    <td>{{ $d['ten'] }}</td>
                                    <td class="text-end text-nowrap">{{ $tien($d['doanh_thu']) }}</td>
                                    <td class="text-end text-nowrap">{{ $d['ti_le'] === null ? '—' : number_format($d['ti_le'], 1, ',', '.') . '%' }}</td>
                                    <td class="text-end text-nowrap">{{ $d['so_luong'] }}</td>
                                    <td class="text-end text-nowrap">{{ $d['so_don'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td>Tổng tiền hàng</td>
                                <td class="text-end text-nowrap">{{ $tien($danhMuc['tong']) }}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

            <p class="admin-page-subtitle small mb-0">
                Tổng tiền hàng <strong>không bằng</strong> doanh thu ở trang Tổng hợp: ở đây chưa gồm phí ship
                (không thuộc danh mục nào) và chưa trừ hoàn tiền (hoàn tiền ghi theo đơn, không theo từng món).
                Doanh thu tính theo danh mục <strong>hiện tại</strong> của sản phẩm — đơn hàng không lưu lại danh mục lúc bán.
                @if(bccomp($danhMuc['ma_giam_chua_chia'], '0', 2) > 0)
                    Có {{ $tien($danhMuc['ma_giam_chua_chia']) }} mã giảm giá của đơn cũ chưa chia được về từng món,
                    nên doanh thu danh mục cao hơn thực tế đúng bằng số đó.
                @endif
            </p>
        </div>
    </div>
</div>

<h2 class="admin-section-title">2. Theo tỉnh/thành nhận hàng</h2>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.bars
                title="Doanh thu thuần theo tỉnh"
                note="Đã trừ hoàn tiền."
                format="tien"
                empty="Chưa có đơn nào giao xong trong kỳ này."
                :rows="$tinh->map(fn ($d) => [
                    'label' => $d['ten'],
                    'value' => (float) $d['thuan'],
                    'meta' => $d['so_don'] . ' đơn',
                ])" />
        </div>
    </div>

    <div class="col-lg-8">
        <div class="admin-panel p-4 h-100">
            <h3 class="h6 fw-bold mb-2">Bảng số</h3>

            @if($tinh->isEmpty())
                <p class="analytics-empty mb-0">Chưa có dữ liệu trong kỳ này.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead>
                            <tr>
                                <th>Tỉnh/thành</th>
                                <th class="text-end text-nowrap">Đơn</th>
                                <th class="text-end text-nowrap">Doanh thu</th>
                                <th class="text-end text-nowrap">Hoàn tiền</th>
                                <th class="text-end text-nowrap">Thuần</th>
                                <th class="text-end text-nowrap">TB/đơn</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tinh as $d)
                                <tr>
                                    <td>{{ $d['ten'] }}</td>
                                    <td class="text-end text-nowrap">{{ $d['so_don'] }}</td>
                                    <td class="text-end text-nowrap">{{ $tien($d['doanh_thu']) }}</td>
                                    <td class="text-end text-nowrap">{{ bccomp($d['hoan_tien'], '0', 2) > 0 ? '−' . $tien($d['hoan_tien']) : '—' }}</td>
                                    <td class="text-end fw-bold">{{ $tien($d['thuan']) }}</td>
                                    <td class="text-end text-nowrap">{{ $tien($d['trung_binh']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <p class="admin-page-subtitle small mb-0">
                Các cách viết khác nhau của cùng một tỉnh ("Hà Nội" và "Thành phố Hà Nội") được gộp làm một dòng.
                Tên tỉnh là tên khách chọn lúc đặt hàng; hệ thống <strong>không</strong> tự gộp theo sắp xếp đơn vị hành chính mới.
            </p>
        </div>
    </div>
</div>

<h2 class="admin-section-title">3. Khách đặt hàng vào lúc nào</h2>

<div class="admin-panel p-4 mb-4">
    @php
        $thu = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
        $gio = array_map(fn ($h) => $h . 'h', range(0, 23));

        $gioDong = $khungGio['tong'] > 0 ? array_keys($khungGio['theo_gio'], max($khungGio['theo_gio'])) : [];
        $thuDong = $khungGio['tong'] > 0 ? array_keys($khungGio['theo_thu'], max($khungGio['theo_thu'])) : [];
    @endphp

    <x-admin.chart.heatmap
        title="Số đơn đặt theo thứ và giờ"
        note="Giờ Việt Nam. Đếm mọi đơn đã đặt, kể cả đơn sau đó bị huỷ — câu hỏi là khách vào mua lúc nào."
        :o="$khungGio['o']"
        :hang="$thu"
        :cot="$gio"
        unit="đơn" />

    @if($khungGio['tong'] > 0)
        <p class="admin-page-subtitle small mt-2 mb-0">
            {{ $khungGio['tong'] }} đơn.
            Giờ đông nhất: <strong>{{ implode(', ', array_map(fn ($h) => $h . 'h–' . ($h + 1) . 'h', $gioDong)) }}</strong>
            ({{ max($khungGio['theo_gio']) }} đơn).
            Ngày đông nhất: <strong>{{ implode(', ', array_map(fn ($t) => $thu[$t], $thuDong)) }}</strong>
            ({{ max($khungGio['theo_thu']) }} đơn).
            @if($khungGio['tong'] < 100)
                Dưới 100 đơn thì một vài đơn đã đủ làm lệch bản đồ — đọc như gợi ý, chưa phải quy luật.
            @endif
        </p>
    @endif
</div>

<h2 class="admin-section-title">4. Theo ngày, tháng, năm</h2>

@php
    $bangKy = [
        ['Theo năm', 'Năm', $thoiGian['nam'], fn ($k) => $k],
        ['Theo tháng', 'Tháng', $thoiGian['thang'], fn ($k) => \Carbon\Carbon::createFromFormat('!Y-m', $k)->format('m/Y')],
        ['Theo ngày', 'Ngày', $thoiGian['ngay']->reverse(), fn ($k) => \Carbon\Carbon::createFromFormat('!Y-m-d', $k)->format('d/m/Y')],
    ];
@endphp

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.bars
                title="Doanh thu thuần theo tháng"
                note="Đã trừ hoàn tiền, cộng tiền khách bù khi đổi hàng."
                format="tien"
                empty="Chưa có đơn nào giao xong trong kỳ này."
                :rows="$thoiGian['thang']->map(fn ($d) => [
                    'label' => \Carbon\Carbon::createFromFormat('!Y-m', $d['ky'])->format('m/Y'),
                    'value' => (float) $d['thuan'],
                    'meta' => $d['so_don'] . ' đơn',
                ])" />
        </div>
    </div>

    <div class="col-lg-7">
        <div class="admin-panel p-4 h-100">
            @foreach($bangKy as [$tieuDe, $nhan, $dong, $hien])
                <h3 class="h6 fw-bold mb-2 {{ $loop->first ? '' : 'mt-3' }}">{{ $tieuDe }}</h3>

                @if($dong->isEmpty())
                    <p class="analytics-empty mb-0">Chưa có dữ liệu trong kỳ này.</p>
                @else
                    <div class="table-responsive" @if($nhan === 'Ngày') style="max-height: 320px; overflow-y: auto" @endif>
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ $nhan }}</th>
                                    <th class="text-end text-nowrap">Đơn</th>
                                    <th class="text-end text-nowrap">Doanh thu</th>
                                    <th class="text-end text-nowrap">Hoàn tiền</th>
                                    <th class="text-end text-nowrap">Thuần</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dong as $d)
                                    <tr>
                                        <td class="text-nowrap">{{ $hien($d['ky']) }}</td>
                                        <td class="text-end">{{ $d['so_don'] }}</td>
                                        <td class="text-end text-nowrap">{{ $tien($d['doanh_thu']) }}</td>
                                        <td class="text-end text-nowrap">{{ bccomp($d['hoan'], '0', 2) > 0 ? '−' . $tien($d['hoan']) : '—' }}</td>
                                        <td class="text-end text-nowrap fw-bold">{{ $tien($d['thuan']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>

@endsection
