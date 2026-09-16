{{-- ĐẦU TRANG CHUNG của mọi trang con Phân tích: tiêu đề, ô chọn kỳ, nút xuất, và thanh tab. --}}
@php
    $cacTab = collect([
        'admin.analytics.index' => ['Tổng hợp', 'bao-cao'],
        'admin.analytics.sales' => ['Doanh thu', 'bao-cao'],
        'admin.analytics.customers' => ['Khách hàng', 'bao-cao'],
        'admin.analytics.reviews' => ['Đánh giá', 'bao-cao'],
        'admin.analytics.profit' => ['Lợi nhuận', 'tai-chinh'],
        'admin.analytics.purchasing' => ['Thu mua', 'kho'],
    ])
        ->filter(fn ($tab) => auth()->user()?->can($tab[1]))
        ->map(fn ($tab) => $tab[0]);

    $trangNay = request()->route()?->getName();
@endphp

<div class="analytics-header mb-3">
    <div class="analytics-header__chu">
        <h1 class="admin-page-title">{{ $tieuDe }}</h1>
        <p class="admin-page-subtitle mb-0">{{ $moTa }}</p>
    </div>

    <div class="analytics-header__loc">
        <div class="analytics-header__hang">
            <x-admin.tuoi-so-lieu />

            @can('bao-cao')
            <a data-admin-link href="{{ route('admin.analytics.export-form', $ky->thamSo()) }}"
               class="btn btn-sm btn-outline-admin analytics-header__xuat">
                Xuất dữ liệu…
            </a>
            @endcan
        </div>

        <x-admin.chon-ky :ky="$ky" :periods="$periods" :route="$trangNay" />
    </div>
</div>

<nav class="analytics-tabs mb-4" aria-label="Các trang phân tích">
    @foreach($cacTab as $ten => $nhan)
        <a data-admin-link href="{{ route($ten, $ky->thamSo()) }}"
           class="analytics-tabs__tab {{ $trangNay === $ten ? 'is-active' : '' }}"
           @if($trangNay === $ten) aria-current="page" @endif>
            {{ $nhan }}
        </a>
    @endforeach
</nav>
