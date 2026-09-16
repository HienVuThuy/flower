@extends('layouts.admin')

@section('title', 'Tổng quan')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Tổng quan</h1>
        <p class="admin-page-subtitle mb-0">
            Xin chào {{ Auth::user()->name }}. Việc cần làm trước, rồi tới cửa hàng đang đi lên hay đi xuống.
        </p>
    </div>

    {{-- Ô CHỌN KỲ — liên kết thường, không cần JavaScript. --}}
    <div class="d-flex flex-wrap align-items-baseline gap-2">
        <x-admin.tuoi-so-lieu />

        <x-admin.chon-ky :ky="$ky" :periods="$periods" route="admin.dashboard" />
    </div>
</div>

<h2 class="admin-section-title">1. Việc cần làm</h2>

<div class="admin-panel p-4 mb-4">

    @forelse($todo as $viec)
        <a data-admin-link href="{{ $viec['url'] }}" class="admin-todo admin-todo--{{ $viec['tone'] }}">
            <span class="admin-todo__count">{{ number_format($viec['count'], 0, ',', '.') }}</span>
            <span class="admin-todo__label">
                {{ $viec['label'] }}
                <span class="admin-todo__hint">{{ $viec['hint'] }}</span>
            </span>
            <x-site.icon name="box-arrow-up-right" class="admin-todo__go" />
        </a>
    @empty
        <p class="admin-page-subtitle mb-0">
            Không có việc nào đang chờ. Đơn hàng, tồn kho, phiếu nhập, đánh giá
            và yêu cầu báo giá đều đã được xử lý.
        </p>
    @endforelse

</div>

<h2 class="admin-section-title">2. {{ $ky->nhan() }}</h2>

<div class="row g-3 mb-4">

    <div class="col-12 col-sm-6 col-lg-3">
        <x-admin.kpi
            label="Doanh thu thuần"
            :note="$orderStats['refunded'] > 0
                ? 'Đơn đã giao ' . \App\Services\Shop\Money::format($orderStats['revenue']) . ', đã trừ ' . \App\Services\Shop\Money::format($orderStats['refunded']) . ' hoàn tiền.'
                : 'Chỉ đơn đã giao, đã trừ hoàn tiền. Đơn đang xử lý chưa phải là tiền đã thu.'"
            :now="$orderStats['net_revenue']"
            :before="$previous['net_revenue'] ?? null"
            format="tien">
            <x-site.money :amount="$orderStats['net_revenue']" />
        </x-admin.kpi>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <x-admin.kpi
            label="Đơn đã giao"
            note="Số đơn đi tới cùng trong kỳ."
            :now="$orderStats['completed']"
            :before="$previous['completed'] ?? null">
            {{ number_format($orderStats['completed'], 0, ',', '.') }}
        </x-admin.kpi>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <x-admin.kpi
            label="Giá trị đơn trung bình"
            note="Doanh thu chia số đơn đã giao.">
            @if($orderStats['average'] === null)
                <span class="admin-page-subtitle">chưa tính được</span>
            @else
                <x-site.money :amount="$orderStats['average']" />
            @endif
        </x-admin.kpi>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <x-admin.kpi
            label="Đơn huỷ"
            note="Trên tổng {{ number_format($orderStats['total'], 0, ',', '.') }} đơn đặt trong kỳ."
            :now="$orderStats['cancelled']"
            :before="$previous['cancelled'] ?? null"
            :invert="true">
            {{ number_format($orderStats['cancelled'], 0, ',', '.') }}
        </x-admin.kpi>
    </div>

</div>

@if(isset($nghiepVu['doi_hang']) || isset($nghiepVu['lai']))
    <div class="row g-3 mb-4">
        @isset($nghiepVu['lai'])
            @php $laiHang = $nghiepVu['lai']['hang']; @endphp
            <div class="col-12 col-sm-6 col-lg-3">
                <x-admin.kpi label="Lãi gộp hàng (có giá vốn)"
                             :href="route('admin.analytics.profit', $ky->thamSo())"
                             :note="$laiHang['ti_le_phu'] === null
                                ? 'Chưa có doanh thu hàng trong kỳ.'
                                : 'Trên ' . number_format($laiHang['ti_le_phu'], 1, ',', '.') . '% doanh thu hàng có giá vốn; không gồm hoa.'">
                    @if(bccomp($laiHang['doanh_thu_co_gia_von'], '0', 2) === 0)
                        <span class="admin-page-subtitle">chưa tính được</span>
                    @else
                        <x-site.money :amount="(float) $laiHang['lai_gop']" />
                    @endif
                </x-admin.kpi>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <x-admin.kpi label="Lãi gộp hoa (theo lô)"
                             :href="route('admin.analytics.profit', $ky->thamSo())"
                             note="Doanh thu hoa trừ tiền các lô đã đóng trong kỳ.">
                    @if($nghiepVu['lai']['hoa'] === null)
                        <span class="admin-page-subtitle">chưa có lô đóng</span>
                    @else
                        <x-site.money :amount="(float) $nghiepVu['lai']['hoa']" />
                    @endif
                </x-admin.kpi>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <x-admin.kpi label="Đã hoàn tiền cho khách"
                             :href="route('admin.refunds.index')"
                             note="Khoản hoàn đã xong trong kỳ — đã trừ ở doanh thu thuần.">
                    <x-site.money :amount="$orderStats['refunded']" />
                </x-admin.kpi>
            </div>
        @endisset

        @isset($nghiepVu['doi_hang'])
            <div class="col-12 col-sm-6 col-lg-3">
                <x-admin.kpi label="Phiếu đổi hàng"
                             :href="route('admin.exchanges.index')"
                             :note="$orderStats['bu_doi_hang'] > 0
                                ? 'Khách bù thêm ' . \App\Services\Shop\Money::format($orderStats['bu_doi_hang']) . ' khi đổi.'
                                : 'Lập trong kỳ, không tính phiếu đã huỷ.'">
                    {{ number_format($nghiepVu['doi_hang'], 0, ',', '.') }}
                </x-admin.kpi>
            </div>
        @endisset
    </div>
@endif

@isset($nghiepVu['kho'])
    @php $kho = $nghiepVu['kho']; @endphp
    <h2 class="admin-section-title">3. Kho và thu mua</h2>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-lg-3">
            <x-admin.kpi label="Tiền lấy hàng trong kỳ"
                         :href="route('admin.analytics.purchasing', $ky->thamSo())"
                         :note="$kho['thu_mua']['so_phieu'] . ' phiếu nhập · ' . $kho['thu_mua']['so_lo'] . ' lô hoa'">
                <x-site.money :amount="(float) $kho['thu_mua']['tong']" />
            </x-admin.kpi>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <x-admin.kpi label="Giá trị tồn kho"
                         :href="route('admin.inventory.index')"
                         :note="number_format($kho['ton']['skus'], 0, ',', '.') . ' mặt hàng — tính theo giá bán, không phải vốn.'">
                <x-site.money :amount="$kho['ton']['value']" />
            </x-admin.kpi>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <x-admin.kpi label="Lô hoa đang dùng"
                         :href="route('admin.flower-lots.index', ['trang_thai' => 'dang_dung'])"
                         :note="\App\Services\Shop\Money::format($kho['tien_lo_mo']) . ' chưa vào giá vốn'
                            . ($kho['lo_qua_han'] > 0 ? ' · ' . $kho['lo_qua_han'] . ' lô mở quá lâu' : '')">
                {{ $kho['lo_mo'] }}
            </x-admin.kpi>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <x-admin.kpi label="Lần trả nhà cung cấp"
                         :href="route('admin.supplier-returns.index')"
                         note="Phiếu trả đã ghi sổ và lô hoa trả lại, trong kỳ.">
                {{ $kho['tra_ncc'] }}
            </x-admin.kpi>
        </div>
    </div>
@endisset

<div class="row g-3 mb-4">

    <div class="col-lg-8 col-xl-6">
        <div class="admin-panel p-4 h-100">

            <div class="d-flex justify-content-between align-items-baseline mb-3">
                <h3 class="h6 fw-bold mb-0">Đơn gần đây</h3>
                <a data-admin-link href="{{ route('admin.orders.index') }}"
                   class="btn btn-outline-admin btn-sm">Xem tất cả</a>
            </div>

            @if($recentOrders->isEmpty())
                <p class="analytics-empty mb-0">Chưa có đơn hàng nào.</p>
            @else
                <div class="admin-list">
                    @foreach($recentOrders as $order)
                        <a data-admin-link href="{{ route('admin.orders.show', $order) }}" class="admin-list__row">
                            <span class="admin-list__main">
                                <span class="fw-bold">{{ $order->order_number }}</span>
                                <span class="admin-page-subtitle">{{ $order->recipient_name }}</span>
                            </span>
                            <span class="admin-list__side">
                                <span class="fw-bold"><x-site.money :amount="(float) $order->grand_total" /></span>
                                <span class="status-pill status-pill--{{ $order->status->badge() }}">
                                    {{ $order->status->label() }}
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

        </div>
    </div>

</div>

<div class="admin-panel p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <p class="admin-page-subtitle mb-0">
        Biểu đồ doanh thu theo ngày, cơ cấu trạng thái đơn, bán chạy, phễu chuyển đổi,
        từ khoá khách tìm, hiệu quả mã giảm giá và xuất dữ liệu nằm ở trang Phân tích.
    </p>

    <a data-admin-link href="{{ route('admin.analytics.index', ['ky' => $period]) }}"
       class="btn btn-secondary-brand">Xem phân tích đầy đủ</a>
</div>

@endsection
