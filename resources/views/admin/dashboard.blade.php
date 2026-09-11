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

    {{--
        Ô CHỌN KỲ — liên kết thường, không cần JavaScript.

        `data-admin-link` để thanh điều hướng đổi nội dung tại chỗ thay vì
        tải lại cả trang: bản trước mọi liên kết trên trang này đều là
        `href` trần, nên đây là màn hình quản trị DUY NHẤT còn nạp lại
        toàn trang mỗi lần bấm.
    --}}
    <div class="d-flex flex-wrap gap-2">
        @foreach($periods as $value => $label)
            <a data-admin-link href="{{ route('admin.dashboard', ['ky' => $value]) }}"
               class="btn btn-sm {{ $period === (string) $value ? 'btn-primary-brand' : 'btn-outline-admin' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>

{{--
    ============================================================
    1. VIỆC CẦN LÀM — khối đầu tiên, có chủ đích.
    ============================================================
    Người mở trang quản trị buổi sáng hỏi "hôm nay phải làm gì", không
    hỏi "cửa hàng có bao nhiêu danh mục". Số liệu kinh doanh quan trọng,
    nhưng nó là câu hỏi thứ hai và đứng ở khối thứ hai.

    Mỗi dòng dẫn thẳng tới đúng danh sách ĐÃ LỌC SẴN. Hiện con số rồi
    bắt admin tự đi lọc lại là bỏ dở việc giữa chừng.
--}}
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
        {{-- Hết việc thì NÓI hết việc. Một danh sách toàn số 0 là danh
             sách không ai đọc, và đọc mãi thành quen bỏ qua — kể cả hôm
             con số khác 0. --}}
        <p class="admin-page-subtitle mb-0">
            Không có việc nào đang chờ. Đơn hàng, tồn kho, phiếu nhập, đánh giá
            và yêu cầu báo giá đều đã được xử lý.
        </p>
    @endforelse

</div>

{{--
    ============================================================
    2. CỬA HÀNG ĐANG THẾ NÀO
    ============================================================
    Mọi con số dưới đây đọc từ AnalyticsService — CÙNG MỘT HÀM mà trang
    Phân tích gọi. Trang này không tự tính lại gì cả, nên hai màn hình
    không thể nói hai con số khác nhau cho cùng một câu hỏi.
--}}
<h2 class="admin-section-title">2. {{ $periods[$period] }}</h2>

<div class="row g-3 mb-4">

    <div class="col-12 col-sm-6 col-lg-3">
        {{--
            DOANH THU THUẦN: tiền đơn đã giao TRỪ phần đã hoàn lại khách.
            Không trừ thì một tháng hoàn nhiều vì hoa héo vẫn trông như
            tháng bán tốt.
        --}}
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
                {{--
                    null KHÁC 0.

                    Chưa có đơn đã giao nào thì không có giá trị trung
                    bình — mẫu số bằng 0. In "0₫" ở đây đọc ra như "khách
                    mua mà không trả đồng nào", một câu hoàn toàn khác.
                --}}
                <span class="admin-page-subtitle">chưa tính được</span>
            @else
                <x-site.money :amount="$orderStats['average']" />
            @endif
        </x-admin.kpi>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        {{-- invert: đơn huỷ TĂNG là tin xấu. Không có nó thì con số huỷ
             tăng vọt hiện màu xanh kèm mũi tên lên, đọc như tin vui. --}}
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

<div class="row g-3 mb-4">

    <div class="col-lg-7">
        <div class="admin-panel p-4 h-100">
            {{--
                MỘT ĐƯỜNG, MỘT ĐƠN VỊ.

                Doanh thu và số đơn khác đơn vị nên KHÔNG chồng lên một
                khung với hai trục dọc: kéo giãn một trục là hai đường
                cắt nhau ở bất cứ đâu người vẽ muốn, và người đọc không
                có cách nào biết. Trang này chỉ vẽ tiền; ai cần cả hai
                thì sang trang Phân tích, nơi hai đường xếp chồng dọc và
                dùng chung trục ngày.
            --}}
            <x-admin.chart.line
                :points="$revenueDaily->map(fn ($d) => ['label' => $d['label'], 'value' => $d['revenue']])"
                title="Doanh thu theo ngày"
                note="Chỉ tính đơn đã giao. Ngày không có đơn là số 0 nhìn thấy được, không phải khoảng trống."
                format="tien"
                :slot="1" />
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.donut
                title="Đơn trong kỳ đang ở đâu"
                note="Đếm mọi đơn đặt trong kỳ, kể cả đơn đã huỷ."
                unit="đơn"
                :slices="$statusMix->map(fn ($r) => [
                    'label' => $r['status']->label(),
                    'value' => $r['total'],
                    'color' => $r['status']->vizColor(),
                ])" />
        </div>
    </div>

</div>

<div class="row g-3 mb-4">

    <div class="col-lg-7">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.bars
                title="Bán chạy trong kỳ"
                note="Theo số lượng đã bán của đơn đã giao. Gom theo mã sản phẩm, không theo tên."
                :rows="$bestSellers->map(fn ($r) => [
                    'label' => $r['name'],
                    'value' => $r['quantity'],
                    'meta' => \App\Services\Shop\Money::format((string) round($r['revenue'])),
                ])"
                empty="Chưa có đơn nào giao xong trong kỳ này." />
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-panel p-4 h-100">

            <div class="d-flex justify-content-between align-items-baseline mb-3">
                <h3 class="h6 fw-bold mb-0">Đơn gần đây</h3>
                <a data-admin-link href="{{ route('admin.orders.index') }}"
                   class="btn btn-outline-admin btn-sm">Xem tất cả</a>
            </div>

            {{--
                KHỐI NÀY KHÔNG THEO KỲ ĐANG CHỌN.

                Nó trả lời "vừa có gì xảy ra", câu hỏi khác hẳn với "kỳ
                này bán được bao nhiêu". Cắt theo kỳ thì chọn "7 ngày
                qua" ở một tuần vắng khách cho ra khối rỗng, trong khi
                câu trả lời đúng vẫn tồn tại.
            --}}
            @if($recentOrders->isEmpty())
                <p class="analytics-empty mb-0">Chưa có đơn hàng nào.</p>
            @else
                <div class="admin-list">
                    @foreach($recentOrders as $order)
                        {{--
                            HAI CỘT, MỖI CỘT HAI TẦNG: bên trái "đơn nào, của
                            ai", bên phải "bao nhiêu, tới đâu rồi".

                            Bốn thứ trên một hàng không vừa cột 5/12: đo ở
                            màn 1280px mỗi dòng cao 74px vì xuống dòng lộn
                            xộn, tên người nhận bị cắt còn vài chữ.
                        --}}
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

{{--
    DẪN SANG TRANG PHÂN TÍCH thay vì nhồi hết vào đây.

    Trang này là bản tóm tắt để đọc trong một phút. Phễu chuyển đổi, từ
    khoá tìm kiếm, khách mua nhiều nhất, hiệu quả mã giảm giá và phần
    xuất dữ liệu đều nằm ở trang Phân tích — chép chúng sang đây là hai
    màn hình cùng làm một việc, và cái nào cũng làm dở.
--}}
<div class="admin-panel p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <p class="admin-page-subtitle mb-0">
        Phễu chuyển đổi, từ khoá khách tìm, khách mua nhiều nhất, hiệu quả mã giảm giá
        và xuất dữ liệu nằm ở trang Phân tích.
    </p>

    <a data-admin-link href="{{ route('admin.analytics.index', ['ky' => $period]) }}"
       class="btn btn-secondary-brand">Xem phân tích đầy đủ</a>
</div>

@endsection
