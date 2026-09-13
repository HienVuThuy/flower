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
    /*
     * MỖI TAB MANG QUYỀN CỦA ĐƯỜNG DẪN NÓ TRỎ TỚI.
     *
     * Lỗi đã sửa: thanh tab từng in cả năm tab cho mọi người, nên nhân
     * viên không có quyền tài chính vẫn thấy "Lợi nhuận", bấm vào và nhận
     * trang 403. Nay tab nào không mở được thì không in — cùng nguyên tắc
     * với thanh điều hướng bên trái. Khoá thật vẫn là middleware `quyen:`.
     */
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

    {{--
        HAI HÀNG CỐ ĐỊNH, không phải một hàng tự xuống dòng.

        Lỗi đã sửa: tất cả nằm chung một hàng flex-wrap, nên "Xuất dữ liệu…"
        đứng cuối hàng và là thứ rớt xuống đầu tiên khi hàng hết chỗ. Mà
        hàng tự dài ra theo thời gian: sau 60 giây chữ "· cách đây 2 phút"
        hiện thêm, và nút Xuất "tự nhảy xuống" trong lúc người ta đang xem.
        Ô ngày cũng rộng hẹp khác nhau theo ngôn ngữ trình duyệt.

        Hàng trên: giờ số liệu và nút Xuất (đẩy sang phải). Hàng dưới: chọn
        kỳ. Chữ thời gian có dài ra thì chỉ ăn vào khoảng trống giữa hàng trên.
    --}}
    <div class="analytics-header__loc">
        <div class="analytics-header__hang">
            <x-admin.tuoi-so-lieu />

            {{-- Xuất theo ĐÚNG kỳ đang xem: tệp tải về phải khớp màn hình.
                 Nút cần quyền báo cáo — trang Thu mua mở được bằng quyền kho,
                 và in nút cho người không bấm được là dựng một trang 403. --}}
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
