@extends('layouts.admin')

@section('title', 'Phân tích')

@section('content')

@php
    /*
     * Số lớn nhất trong biểu đồ ngày — dùng làm mốc 100% chiều cao cột.
     * Tính ở đây một lần thay vì gọi max() trong vòng lặp.
     */
    $peak = max(1, $daily->max('total'));
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Phân tích</h1>
        <p class="admin-page-subtitle">
            Mọi con số dưới đây đếm trực tiếp từ cơ sở dữ liệu.
            Chưa có dữ liệu thì hiện là chưa có, không ước lượng.
        </p>
    </div>

    {{-- Ô chọn kỳ: liên kết thường, không cần JavaScript. --}}
    <div class="d-flex flex-wrap gap-2">
        @foreach($periods as $value => $label)
            <a href="{{ route('admin.analytics.index', ['ky' => $value]) }}"
               class="btn btn-sm {{ $period === $value ? 'btn-primary-brand' : 'btn-outline-admin' }}">
                {{ $label }}
            </a>
        @endforeach

        {{--
            Xuất theo ĐÚNG kỳ đang xem, không phải kỳ mặc định — tệp tải
            về phải khớp với những gì admin vừa nhìn thấy trên màn hình.
        --}}
        <a href="{{ route('admin.analytics.export-form', ['ky' => $period]) }}"
           class="btn btn-sm btn-outline-admin">
            Xuất dữ liệu…
        </a>
    </div>
</div>

{{--
    ============================================================
    PHẦN A — TIỀN VÀ ĐƠN HÀNG
    ============================================================
    Đặt TRÊN CÙNG, có chủ ý. Đây là câu hỏi người mở trang này hỏi
    trước: cửa hàng bán được bao nhiêu, và đang lên hay đang xuống.
    Phễu chuyển đổi và hành vi là để GIẢI THÍCH con số đó, nên chúng
    đứng sau.
--}}
<h2 class="admin-section-title">A. Tiền và đơn hàng</h2>

<div class="row g-3 mb-4">

    <div class="col-lg-7">
        <div class="admin-panel p-4 h-100">
            {{--
                HAI PHÉP ĐO KHÁC ĐƠN VỊ THÌ VẼ HAI BIỂU ĐỒ, tuyệt đối
                không chồng lên một khung với hai trục dọc.

                Trục kép là cách dễ nhất để nói dối bằng biểu đồ: kéo
                giãn một trục là hai đường cắt nhau ở bất cứ đâu người
                vẽ muốn, và người đọc không có cách nào biết.

                Hai biểu đồ chồng dọc, DÙNG CHUNG trục ngày, thì so sánh
                vẫn dễ mà không có chỗ nào để bóp méo.
            --}}
            <x-admin.chart.line
                :points="$revenueDaily->map(fn ($d) => ['label' => $d['label'], 'value' => $d['revenue']])"
                title="Doanh thu theo ngày"
                note="Chỉ tính đơn đã giao"
                format="tien"
                :slot="1" />

            <hr class="my-3">

            <x-admin.chart.line
                :points="$revenueDaily->map(fn ($d) => ['label' => $d['label'], 'value' => $d['orders']])"
                title="Số đơn đã giao theo ngày"
                :slot="2" />
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-panel p-4 h-100 d-flex flex-column gap-3">
            @php
                /*
                 * THANG THỨ TỰ cho các bước trong quy trình, KHÔNG phải
                 * bộ màu danh mục.
                 *
                 * Chờ xác nhận → Đã xác nhận → Đang chuẩn bị → Đang giao
                 * → Hoàn thành là một dãy CÓ TRƯỚC CÓ SAU. Tô mỗi bước
                 * một màu khác hệ là vứt bỏ thông tin thứ tự đó.
                 *
                 * "Đã huỷ" KHÔNG nằm trong dãy — nó là kết cục xấu, nên
                 * dùng màu trạng thái và luôn đi kèm nhãn chữ.
                 */
                $mauTrangThai = [
                    'pending' => 'var(--viz-step-1)',
                    'confirmed' => 'var(--viz-step-2)',
                    'preparing' => 'var(--viz-step-3)',
                    'shipping' => 'var(--viz-step-4)',
                    'completed' => 'var(--viz-step-5)',
                    'cancelled' => 'var(--viz-huy)',
                ];
            @endphp

            <x-admin.chart.donut
                title="Cơ cấu trạng thái đơn"
                note="Toàn bộ đơn trong kỳ"
                unit="đơn"
                :slices="$statusMix->map(fn ($r) => [
                    'label' => $r['status']->label(),
                    'value' => $r['total'],
                    'color' => $mauTrangThai[$r['status']->value] ?? 'var(--viz-step-3)',
                ])" />

            <x-admin.chart.donut
                title="Hình thức thanh toán"
                note="Đếm theo số đơn"
                unit="đơn"
                :slices="$paymentMix->map(fn ($r, $i) => [
                    'label' => $r['method']->label(),
                    'value' => $r['total'],
                    'color' => $i === 0 ? 'var(--viz-1)' : 'var(--viz-2)',
                ])" />
        </div>
    </div>

</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.bars
                title="Khách mua nhiều nhất"
                note="Theo doanh thu, chỉ đơn đã giao"
                format="tien"
                empty="Chưa có đơn đã giao của khách có tài khoản."
                :rows="$topCustomers->map(fn ($r) => [
                    'label' => $r['name'],
                    'value' => $r['revenue'],
                    'meta' => $r['orders'] . ' đơn',
                ])" />
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.bars
                title="Mã giảm giá đã dùng"
                note="Theo tổng tiền đã giảm"
                format="tien"
                empty="Chưa có đơn nào dùng mã trong kỳ."
                :rows="$couponUsage->map(fn ($r) => [
                    'label' => $r['code'],
                    'value' => $r['discount'],
                    'meta' => $r['orders'] . ' đơn',
                ])" />
        </div>
    </div>
</div>

<h2 class="admin-section-title">B. Hành vi khách hàng</h2>

{{-- ============ 1. PHỄU CHUYỂN ĐỔI ============ --}}
<div class="admin-panel p-4 mb-4">

    <h2 class="h6 fw-bold mb-1">Phễu chuyển đổi</h2>
    <p class="admin-page-subtitle mb-4">
        Đếm theo <strong>phiên truy cập</strong>, không theo lượt bấm. Một người xem
        đi xem lại một sản phẩm vẫn chỉ tính là một phiên đã xem.
    </p>

    <div class="funnel">
        @foreach([
            ['Xem sản phẩm', $funnel['views'], null, 'views'],
            ['Thêm vào giỏ', $funnel['carts'], $funnel['view_to_cart'], 'carts'],
            ['Đặt hàng', $funnel['purchases'], $funnel['cart_to_purchase'], 'purchases'],
        ] as [$label, $value, $rate, $key])
            <div class="funnel__step">
                <div class="funnel__value">{{ number_format($value) }}</div>
                <div class="funnel__label">{{ $label }}</div>

                @if($rate !== null)
                    <div class="funnel__rate">
                        {{ number_format($rate, 1) }}% từ bước trước
                    </div>
                @endif

                {{-- $previous là null với kỳ "Toàn bộ" — component tự ẩn. --}}
                <x-admin.trend :now="$value" :before="$previous['funnel'][$key] ?? null" />
            </div>
        @endforeach
    </div>

    @if($funnel['view_to_purchase'] !== null)
        <p class="mt-3 mb-0">
            Tỷ lệ chuyển đổi chung:
            <strong>{{ number_format($funnel['view_to_purchase'], 1) }}%</strong>
            số phiên đã xem sản phẩm dẫn tới đặt hàng.
        </p>
    @endif

</div>

<div class="row g-4 mb-4">

    {{-- ============ 2. ĐƠN HÀNG & DOANH THU ============ --}}
    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">

            <h2 class="h6 fw-bold mb-1">Đơn hàng &amp; doanh thu</h2>
            <p class="admin-page-subtitle mb-4">
                Đọc từ bảng đơn hàng. Doanh thu chỉ tính đơn <strong>đã giao</strong> —
                đơn đang xử lý chưa phải là tiền đã thu.
            </p>

            @if($orderStats['total'] === 0)
                {{--
                    KHÔNG bịa số khi chưa có đơn. Nói thẳng, và giải thích
                    vì sao phễu bên trên vẫn có số ở bước "Đặt hàng":
                    hai khối đọc hai nguồn khác nhau.
                --}}
                <div class="analytics-empty">
                    <p class="mb-2"><strong>Chưa có đơn hàng nào trong kỳ này.</strong></p>
                    <p class="mb-0 admin-page-subtitle">
                        Phễu bên trên đếm từ nhật ký hành vi, còn khối này đọc từ bảng
                        đơn hàng. Nhật ký giữ lại cả những lần đặt hàng mà đơn sau đó
                        đã bị xoá, nên hai con số có thể không khớp.
                    </p>
                </div>
            @else
                <dl class="stat-list">
                    <div class="stat-list__row">
                        <dt>
                            Tổng đơn
                            <x-admin.trend :now="$orderStats['total']" :before="$previous['orders']['total'] ?? null" />
                        </dt>
                        <dd>{{ number_format($orderStats['total']) }}</dd>
                    </div>
                    <div class="stat-list__row">
                        <dt>Đã giao</dt>
                        <dd>{{ number_format($orderStats['completed']) }}</dd>
                    </div>
                    <div class="stat-list__row">
                        <dt>
                            Đã huỷ
                            {{-- invert: đơn huỷ TĂNG là tin xấu, phải hiện màu cảnh báo
                                 chứ không phải màu xanh như mọi chỉ số khác. --}}
                            <x-admin.trend :now="$orderStats['cancelled']" :before="$previous['orders']['cancelled'] ?? null" :invert="true" />
                        </dt>
                        <dd>{{ number_format($orderStats['cancelled']) }}</dd>
                    </div>
                    <div class="stat-list__row stat-list__row--total">
                        <dt>
                            Doanh thu (đơn đã giao)
                            <x-admin.trend :now="$orderStats['revenue']" :before="$previous['orders']['revenue'] ?? null" />
                        </dt>
                        <dd><x-site.money :amount="$orderStats['revenue']" /></dd>
                    </div>
                    <div class="stat-list__row">
                        <dt>Giá trị đơn trung bình</dt>
                        <dd>
                            @if($orderStats['average'] === null)
                                {{-- null khác 0: chưa có đơn đã giao nào để tính. --}}
                                <span class="admin-page-subtitle">chưa tính được</span>
                            @else
                                <x-site.money :amount="$orderStats['average']" />
                            @endif
                        </dd>
                    </div>
                </dl>
            @endif

        </div>
    </div>

    {{-- ============ 3. TỔNG LƯỢT THEO LOẠI SỰ KIỆN ============ --}}
    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">

            <h2 class="h6 fw-bold mb-1">Hành vi đã ghi nhận</h2>
            <p class="admin-page-subtitle mb-4">
                Tổng số lượt, theo sáu loại sự kiện hệ thống đang theo dõi.
            </p>

            @if($totals->isEmpty())
                <div class="analytics-empty">
                    <p class="mb-0">Chưa ghi nhận hành vi nào trong kỳ này.</p>
                </div>
            @else
                <dl class="stat-list">
                    @foreach(\App\Enums\UserEventType::cases() as $type)
                        <div class="stat-list__row">
                            <dt>{{ $type->label() }}</dt>
                            <dd>{{ number_format($totals[$type->value] ?? 0) }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

        </div>
    </div>

</div>

{{-- ============ 4. HOẠT ĐỘNG 14 NGÀY ============ --}}
<div class="admin-panel p-4 mb-4">

    <h2 class="h6 fw-bold mb-1">Hoạt động 14 ngày gần nhất</h2>
    <p class="admin-page-subtitle mb-4">
        Tổng số sự kiện mỗi ngày. Ngày không có hoạt động vẫn được vẽ, để không
        nhìn nhầm thành chuỗi ngày liền mạch.
    </p>

    {{--
        Biểu đồ vẽ bằng CSS thuần, không nạp thư viện đồ thị nào: dữ liệu
        chỉ có 14 cột, thêm một thư viện vài trăm KB cho việc này là không
        đáng, và mỗi thư viện là một thứ phải bảo trì về sau.
    --}}
    <div class="bar-chart" role="img"
         aria-label="Biểu đồ số sự kiện mỗi ngày trong 14 ngày gần nhất">
        @foreach($daily as $day)
            <div class="bar-chart__col" title="{{ $day['label'] }}: {{ $day['total'] }} sự kiện">
                <div class="bar-chart__bar"
                     style="height: {{ $day['total'] > 0 ? max(4, round($day['total'] / $peak * 100)) : 0 }}%"></div>
                <div class="bar-chart__value">{{ $day['total'] ?: '' }}</div>
                <div class="bar-chart__label">{{ $day['label'] }}</div>
            </div>
        @endforeach
    </div>

</div>

{{-- ============ 5. SẢN PHẨM ĐƯỢC QUAN TÂM ============ --}}
<div class="row g-4 mb-4">

    @foreach([
        ['Xem nhiều nhất', $topViewed, 'lượt xem'],
        ['Thêm vào giỏ nhiều nhất', $topCarted, 'lượt thêm'],
        ['Được yêu thích nhiều nhất', $topWished, 'lượt thích'],
    ] as [$title, $rows, $unit])
        <div class="col-lg-4">
            <div class="admin-panel p-4 h-100">

                <h2 class="h6 fw-bold mb-3">{{ $title }}</h2>

                @if($rows->isEmpty())
                    <div class="analytics-empty">
                        <p class="mb-0">Chưa có dữ liệu.</p>
                    </div>
                @else
                    <ol class="rank-list">
                        @foreach($rows as $row)
                            <li class="rank-list__item">
                                <span class="rank-list__name">
                                    @if($row['product'])
                                        <a href="{{ route('admin.products.show', $row['product']) }}">
                                            {{ $row['product']->name }}
                                        </a>
                                    @else
                                        {{-- Nhật ký còn, sản phẩm đã bị xoá. --}}
                                        <span class="admin-page-subtitle">(sản phẩm đã xoá)</span>
                                    @endif
                                </span>
                                <span class="rank-list__value">
                                    {{ number_format($row['total']) }}
                                    <span class="admin-page-subtitle">{{ $unit }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif

            </div>
        </div>
    @endforeach

</div>

{{-- ============ 6. DANH MỤC, TỪ KHOÁ, BÁN CHẠY ============ --}}
<div class="row g-4">

    <div class="col-lg-4">
        <div class="admin-panel p-4 h-100">
            <h2 class="h6 fw-bold mb-3">Danh mục được xem nhiều</h2>

            @if($topCategories->isEmpty())
                <div class="analytics-empty"><p class="mb-0">Chưa có dữ liệu.</p></div>
            @else
                <ol class="rank-list">
                    @foreach($topCategories as $row)
                        <li class="rank-list__item">
                            <span class="rank-list__name">
                                {{ $row['category']->name ?? '(danh mục đã xoá)' }}
                            </span>
                            <span class="rank-list__value">{{ number_format($row['total']) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-panel p-4 h-100">
            <h2 class="h6 fw-bold mb-1">Từ khoá khách tìm</h2>
            <p class="admin-page-subtitle mb-3">Đã gộp chữ hoa với chữ thường.</p>

            @if($topSearches->isEmpty())
                <div class="analytics-empty"><p class="mb-0">Chưa có lượt tìm kiếm nào.</p></div>
            @else
                <ol class="rank-list">
                    @foreach($topSearches as $row)
                        <li class="rank-list__item">
                            <span class="rank-list__name">{{ $row['term'] }}</span>
                            <span class="rank-list__value">{{ number_format($row['total']) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-panel p-4 h-100">
            <h2 class="h6 fw-bold mb-1">Bán chạy nhất</h2>
            <p class="admin-page-subtitle mb-3">
                Tên lấy từ bản chụp trong đơn, không phải tên hiện tại của sản phẩm.
            </p>

            @if($bestSellers->isEmpty())
                <div class="analytics-empty">
                    <p class="mb-0">Chưa có đơn nào đã giao trong kỳ này.</p>
                </div>
            @else
                <ol class="rank-list">
                    @foreach($bestSellers as $row)
                        <li class="rank-list__item">
                            <span class="rank-list__name">{{ $row['name'] }}</span>
                            <span class="rank-list__value">
                                {{ number_format($row['quantity']) }}
                                <span class="admin-page-subtitle">sp</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>

</div>

@endsection
