@extends('layouts.admin')

@section('title', 'Tồn kho')

@section('content')

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) round($v));
    $so = fn ($v) => number_format($v, 0, ',', '.');

    $conNgay = fn (?float $c) => $c === null
        ? 'chưa bán được cái nào'
        : ($c < 1 ? 'dưới 1 ngày' : round($c) . ' ngày');
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Tồn kho</h1>
        <p class="admin-page-subtitle">
            Còn bao nhiêu, bán nhanh cỡ nào, và còn bán được mấy ngày nữa.
            Chỉ tính những mặt hàng có bật theo dõi tồn kho.
        </p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a data-admin-link href="{{ route('admin.stock-counts.create') }}" class="btn btn-sm btn-outline-admin">Kiểm kê</a>

        @foreach($cacKy as $value => $label)
            <a data-admin-link href="{{ route('admin.inventory.index', ['ky' => $value, 'nguong' => $nguong]) }}"
               class="btn btn-sm {{ $ky === (string) $value ? 'btn-primary-brand' : 'btn-outline-admin' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>

<div class="row g-3 mb-4">

    @php
        $the = [
            ['nhan' => 'Mặt hàng theo dõi tồn', 'gt' => $so($tongQuan['skus']), 'phu' => $so($tongQuan['units']) . ' đơn vị trong kho'],
            ['nhan' => 'Hết hàng, vẫn đang bán', 'gt' => $so($tongQuan['out']), 'phu' => 'đang mất đơn ngay lúc này', 'canh' => $tongQuan['out'] > 0],
            ['nhan' => 'Sắp hết', 'gt' => $so($tongQuan['low']), 'phu' => 'còn dưới ' . round($nguong) . ' ngày bán', 'canh' => $tongQuan['low'] > 0],
            ['nhan' => 'Không bán được cái nào', 'gt' => $so($tongQuan['dead']), 'phu' => 'tiền đang nằm im'],
        ];
    @endphp

    @foreach($the as $t)
        <div class="col-6 col-lg-3">
            <div class="admin-panel p-3 h-100">
                <p class="admin-page-subtitle mb-1">{{ $t['nhan'] }}</p>
                <p class="h4 fw-bold mb-0 {{ ($t['canh'] ?? false) ? 'text-danger' : '' }}">{{ $t['gt'] }}</p>
                <p class="admin-page-subtitle mb-0">{{ $t['phu'] }}</p>
            </div>
        </div>
    @endforeach

</div>

<div class="admin-panel p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
        <div>
            <h2 class="h6 fw-bold mb-1">Giá trị tồn kho</h2>
            <p class="admin-page-subtitle mb-0">
                Tính theo <strong>giá bán</strong>, không phải giá vốn.
                Hệ thống chưa lưu giá vốn nên chưa tính được lãi/lỗ hay biên lợi nhuận.
            </p>
        </div>

        <p class="h4 fw-bold mb-0">{{ $tien($tongQuan['value']) }}</p>
    </div>
</div>

<h2 class="admin-section-title">1. Đang mất đơn — hết hàng nhưng vẫn bày bán</h2>

<div class="admin-panel p-4 mb-4">
    @if($daHet->isEmpty())
        <p class="analytics-empty mb-0">
            Không có mặt hàng nào đang bày bán mà hết kho. Tốt.
        </p>
    @else
        <p class="admin-page-subtitle">
            Khách vẫn bấm vào được nhưng không mua được. Xếp theo bán chạy —
            món trên cùng đang mất nhiều đơn nhất.
        </p>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Mặt hàng</th>
                        <th>Đã bán trong kỳ</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($daHet as $d)
                        <tr>
                            <td>
                                {{ $d['name'] }}
                                @if($d['variant'])
                                    <span class="text-muted">— {{ $d['variant'] }}</span>
                                @endif
                            </td>
                            <td>{{ $so($d['sold']) }}</td>
                            <td class="text-end">
                                <a data-admin-link
                                   href="{{ route('admin.stock-receipts.create', ['mat-hang' => $d['product']->id . ':' . ($d['variant_id'] ?? '')]) }}"
                                   class="btn btn-outline-admin btn-sm">Nhập thêm</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<h2 class="admin-section-title">2. Sắp hết — xếp theo còn bán được mấy ngày</h2>

<div class="admin-panel p-4 mb-4">
    <p class="admin-page-subtitle">
        Số ngày = tồn kho chia cho tốc độ bán trung bình trong {{ $cacKy[$ky] }}.
        Món chưa bán được cái nào không nằm ở đây — xem mục 3.
    </p>

    @if($sapHet->isEmpty())
        <p class="analytics-empty mb-0">
            Không có mặt hàng nào còn dưới {{ round($nguong) }} ngày bán.
        </p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Mặt hàng</th>
                        <th>Còn</th>
                        <th>Bán/ngày</th>
                        <th>Còn bán được</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sapHet as $d)
                        <tr>
                            <td>
                                {{ $d['name'] }}
                                @if($d['variant'])
                                    <span class="text-muted">— {{ $d['variant'] }}</span>
                                @endif
                            </td>
                            <td>{{ $so($d['stock']) }}</td>
                            <td>{{ number_format($d['per_day'], 1, ',', '.') }}</td>
                            <td>
                                <span class="status-pill status-pill--{{ $d['cover'] !== null && $d['cover'] <= 3 ? 'danger' : 'warning' }}">
                                    {{ $conNgay($d['cover']) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a data-admin-link
                                   href="{{ route('admin.stock-receipts.create', ['mat-hang' => $d['product']->id . ':' . ($d['variant_id'] ?? '')]) }}"
                                   class="btn btn-outline-admin btn-sm">Nhập thêm</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<h2 class="admin-section-title">3. Tiền nằm im — còn hàng nhưng không bán được cái nào</h2>

<div class="admin-panel p-4 mb-4">
    <p class="admin-page-subtitle">
        Cả {{ $cacKy[$ky] }} không bán được một cái nào. Xếp theo giá trị giảm dần:
        món đắt nằm im đáng chú ý hơn món rẻ, dù cùng không bán được.
    </p>

    @if($chetVon->isEmpty())
        <p class="analytics-empty mb-0">Mọi mặt hàng còn kho đều bán được ít nhất một cái trong kỳ.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Mặt hàng</th>
                        <th>Còn</th>
                        <th>Giá bán</th>
                        <th>Giá trị nằm kho</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($chetVon as $d)
                        <tr>
                            <td>
                                {{ $d['name'] }}
                                @if($d['variant'])
                                    <span class="text-muted">— {{ $d['variant'] }}</span>
                                @endif
                            </td>
                            <td>{{ $so($d['stock']) }}</td>
                            <td>{{ $tien($d['price']) }}</td>
                            <td>{{ $tien($d['value']) }}</td>
                            <td class="text-end">
                                <a data-admin-link href="{{ route('admin.promotions.index') }}"
                                   class="btn btn-outline-admin btn-sm">Cân nhắc khuyến mại</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
