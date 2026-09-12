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

{{--
    KHỐI LỌC KHÔNG ĐƯỢC CO GIÃN THEO ĐỘ DÀI TIÊU ĐỀ.

    Lỗi đã sửa: tiêu đề, mô tả và khối lọc nằm chung một hàng flex, mà mô
    tả mỗi tab một độ dài. Đo được khối lọc rộng 503 / 513 / 521 / 528 /
    592px ở năm tab — trong khi hai phần bên trong nó luôn 319 và 360px.
    Nghĩa là chỉ riêng phần chữ bên trái đã đủ làm các nút xuống dòng khác
    nhau, và chuyển tab thấy bộ lọc "nhảy".

    `.analytics-header` khoá lại: phần chữ co được, khối lọc thì không.
--}}
<div class="analytics-header mb-3">
    <div class="analytics-header__chu">
        <h1 class="admin-page-title">{{ $tieuDe }}</h1>
        <p class="admin-page-subtitle mb-0">{{ $moTa }}</p>
    </div>

    <div class="analytics-header__loc">
        <x-admin.tuoi-so-lieu />

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
