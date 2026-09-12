{{--
    ĐẦU TRANG CHUNG của mọi trang con Phân tích: tiêu đề, ô chọn kỳ, nút xuất,
    và thanh tab.
    ============================================================
    Biến cần có: $tieuDe, $moTa, $ky, $period, $periods.

    MỘT BẢN, không phải năm bản. Năm trang tự dựng ô chọn kỳ thì chỉ cần một
    trang quên `(string) $value` là nút kỳ đang chọn không được tô đậm — lỗi
    đã gặp ở QĐ-219.

    TAB GIỮ NGUYÊN KỲ ĐANG CHỌN: đang xem "7 ngày qua" ở Doanh thu, bấm sang
    Khách hàng mà nhảy về "30 ngày qua" là đặt hai con số của hai khoảng khác
    nhau cạnh nhau trong đầu người đọc.
--}}
@php
    $cacTab = [
        'admin.analytics.index' => 'Tổng hợp',
        'admin.analytics.sales' => 'Doanh thu',
        'admin.analytics.customers' => 'Khách hàng',
        'admin.analytics.reviews' => 'Đánh giá',
        'admin.analytics.profit' => 'Lợi nhuận',
    ];

    $trangNay = request()->route()?->getName();
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
    <div>
        <h1 class="admin-page-title">{{ $tieuDe }}</h1>
        <p class="admin-page-subtitle mb-0">{{ $moTa }}</p>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-admin.chon-ky :ky="$ky" :periods="$periods" :route="$trangNay" />

        {{-- Xuất theo ĐÚNG kỳ đang xem: tệp tải về phải khớp màn hình. --}}
        <a data-admin-link href="{{ route('admin.analytics.export-form', $ky->thamSo()) }}"
           class="btn btn-sm btn-outline-admin">
            Xuất dữ liệu…
        </a>
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
