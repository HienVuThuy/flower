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

@include('admin.analytics._header', [
    'tieuDe' => 'Phân tích',
    'moTa' => 'Mọi con số dưới đây đếm trực tiếp từ cơ sở dữ liệu. Chưa có dữ liệu thì hiện là chưa có, không ước lượng.',
])

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
                 * Bảng màu trạng thái nằm ở OrderStatus::vizColor().
                 *
                 * Trang Tổng quan vẽ đúng biểu đồ này. Chép bảng màu
                 * sang đó là hai màn hình lệch màu ngay lần đầu có người
                 * sửa một bên — và không ai phát hiện, vì cả hai đều
                 * trông bình thường khi nhìn riêng.
                 */
            @endphp

            <x-admin.chart.donut
                title="Cơ cấu trạng thái đơn"
                note="Toàn bộ đơn trong kỳ"
                unit="đơn"
                :slices="$statusMix->map(fn ($r) => [
                    'label' => $r['status']->label(),
                    'value' => $r['total'],
                    'color' => $r['status']->vizColor(),
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

{{--
    ============================================================
    PHẦN B — VẬN CHUYỂN: THU CỦA KHÁCH SO VỚI TRẢ GHN
    ============================================================
    Đặt ngay sau phần tiền vì nó LÀ tiền: mỗi đồng cửa hàng bù ship là
    một đồng trừ thẳng vào doanh thu ở trên, mà doanh thu không cho thấy.
--}}
<h2 class="admin-section-title">B. Vận chuyển: phí thu của khách so với cước trả GHN</h2>

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format($v);
    $loai = $shipping['loai'];
@endphp

<div class="admin-panel p-4 mb-4">

    @if($ghnSandbox)
        {{--
            NÓI TRƯỚC khi đưa ra bất kỳ con số nào.

            Cổng thử của GHN dùng bảng giá thử. Không có dòng này thì một
            con số "cửa hàng bù 14.900₫" đọc ra như tiền thật đã chi.
        --}}
        <div class="alert alert-warning py-2 px-3 small">
            Đang nối <strong>cổng thử</strong> của GHN: cước dưới đây là cước thử, không phải tiền cửa hàng thật sự trả.
        </div>
    @endif

    @if($shipping['van_don'] === 0)
        <p class="analytics-empty mb-0">Chưa có vận đơn GHN nào cho các đơn đặt trong kỳ này.</p>
    @elseif($shipping['tinh_duoc'] === 0)
        {{--
            CÓ VẬN ĐƠN MÀ KHÔNG TÍNH ĐƯỢC CÁI NÀO — nói rõ vì sao.

            In ba ô "thu 0₫ / trả 0₫ / bù 0₫" ở đây là nói "cửa hàng không
            bù đồng nào", một câu hoàn toàn khác với "không có số liệu để
            biết".
        --}}
        <p class="analytics-empty mb-2">
            Có {{ $shipping['van_don'] }} vận đơn trong kỳ nhưng chưa vận đơn nào tính được khoản bù.
        </p>
    @else
        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-4">
                <x-admin.kpi label="Phí ship thu của khách"
                             note="Trên {{ $shipping['tinh_duoc'] }} vận đơn tính được, {{ $shipping['mien_phi'] }} đơn miễn phí giao.">
                    {{ $tien($shipping['thu']) }}
                </x-admin.kpi>
            </div>
            <div class="col-12 col-sm-4">
                <x-admin.kpi label="Cước trả GHN" note="Theo cước GHN báo lúc tạo vận đơn.">
                    {{ $tien($shipping['tra']) }}
                </x-admin.kpi>
            </div>
            <div class="col-12 col-sm-4">
                @php $bu = bccomp($shipping['chenh'], '0', 2); @endphp
                {{--
                    NÓI BẰNG CHỮ chiều của chênh lệch.

                    Một con số âm hay dương trần trụi bắt người đọc nhớ quy
                    ước "trả trừ thu". Viết "cửa hàng bù" hay "thu dư" thì
                    không ai đọc ngược được.
                --}}
                <x-admin.kpi :label="$bu > 0 ? 'Cửa hàng bù ship' : ($bu < 0 ? 'Phí ship thu dư' : 'Chênh lệch')"
                             note="Cước trả GHN trừ phí thu của khách.">
                    <span class="{{ $bu > 0 ? 'text-danger' : '' }}">
                        {{ $tien(ltrim($shipping['chenh'], '-')) }}
                    </span>
                </x-admin.kpi>
            </div>
        </div>
    @endif

    {{--
        NÓI ĐÃ LOẠI NHỮNG GÌ, và vì sao.

        Tổng trên chỉ đúng cho những vận đơn còn lại. Không liệt kê phần
        bị loại thì "bù 30.000₫" đọc như con số của cả kỳ.
    --}}
    @if($loai['nguoi_nhan_tra'] + $loai['da_huy'] + $loai['thieu_cuoc'] > 0 || $shipping['hoan_hang'] > 0)
        <ul class="admin-page-subtitle small mb-0 ps-3">
            @if($loai['nguoi_nhan_tra'] > 0)
                <li>
                    {{ $loai['nguoi_nhan_tra'] }} vận đơn <strong>người nhận trả cước</strong> không được tính:
                    cửa hàng không trả GHN đồng nào cho chúng. Đây là các vận đơn tạo trước khi sửa người trả cước,
                    khi người nhận có thể đã bị thu phí ship hai lần.
                </li>
            @endif
            @if($loai['da_huy'] > 0)
                <li>{{ $loai['da_huy'] }} vận đơn đã huỷ không được tính.</li>
            @endif
            @if($loai['thieu_cuoc'] > 0)
                <li>{{ $loai['thieu_cuoc'] }} vận đơn GHN không báo cước lúc tạo, không được tính (không coi là 0₫).</li>
            @endif
            @if($shipping['hoan_hang'] > 0)
                <li>
                    {{ $shipping['hoan_hang'] }} vận đơn giao thất bại hoặc hoàn hàng: cước thật có thể cao hơn
                    vì phí hoàn, mà GHN không trả con số đó qua API. Đối chiếu với bảng đối soát của GHN.
                </li>
            @endif
        </ul>
    @endif

    @if($shippingMonths->isNotEmpty())
        <div class="table-responsive mt-3">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Tháng</th>
                        <th class="text-end">Vận đơn</th>
                        <th class="text-end">Thu của khách</th>
                        <th class="text-end">Trả GHN</th>
                        <th class="text-end">Cửa hàng bù</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($shippingMonths as $m)
                        <tr>
                            {{-- '!' đặt ngày về 1: thiếu nó thì createFromFormat lấy NGÀY HÔM NAY
                                 cho phần không khai, và vào ngày 31 thì "2026-02" tràn sang tháng 3. --}}
                            <td>{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $m['thang'])->format('m/Y') }}</td>
                            <td class="text-end">{{ $m['don'] }}</td>
                            <td class="text-end">{{ $tien($m['thu']) }}</td>
                            <td class="text-end">{{ $tien($m['tra']) }}</td>
                            <td class="text-end {{ bccomp($m['chenh'], '0', 2) > 0 ? 'text-danger' : '' }}">
                                {{-- Âm nghĩa là THU DƯ: in có dấu trừ để cột cộng lại đúng. --}}
                                {{ $tien($m['chenh']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($shippingSubsidies->isNotEmpty())
        <h3 class="h6 fw-bold mt-4 mb-2">Đơn bù ship nhiều nhất</h3>
        <p class="admin-page-subtitle small">
            Bù vì miễn phí giao, hay vì bảng phí theo tỉnh thấp hơn cước GHN? Hai nguyên nhân sửa ở hai chỗ khác nhau.
        </p>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Đơn</th>
                        <th>Tỉnh</th>
                        <th class="text-end">Thu của khách</th>
                        <th class="text-end">Trả GHN</th>
                        <th class="text-end">Bù</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($shippingSubsidies as $d)
                        <tr>
                            <td>
                                <a data-admin-link href="{{ route('admin.orders.show', $d['order']) }}">{{ $d['order']->order_number }}</a>
                            </td>
                            <td>{{ $d['order']->shipping_province }}</td>
                            <td class="text-end">
                                {{ bccomp($d['thu'], '0', 2) === 0 ? 'miễn phí' : $tien($d['thu']) }}
                            </td>
                            <td class="text-end">{{ $tien($d['tra']) }}</td>
                            <td class="text-end text-danger">{{ $tien($d['chenh']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>

<h2 class="admin-section-title">C. Hành vi khách hàng</h2>

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
                    <div class="stat-list__row">
                        <dt>Doanh thu (đơn đã giao)</dt>
                        <dd><x-site.money :amount="$orderStats['revenue']" /></dd>
                    </div>
                    <div class="stat-list__row">
                        <dt>Đã hoàn tiền cho những đơn đó</dt>
                        <dd>
                            @if($orderStats['refunded'] > 0)
                                −<x-site.money :amount="$orderStats['refunded']" />
                            @else
                                <x-site.money :amount="0" />
                            @endif
                        </dd>
                    </div>
                    {{--
                        DÒNG TỔNG LÀ DOANH THU THUẦN — cùng con số trang Tổng
                        quan đưa lên đầu. Hai trang đưa hai con số khác nhau
                        lên vị trí nổi bật nhất là đúng lỗi đã sửa ở QĐ-218.
                    --}}
                    <div class="stat-list__row stat-list__row--total">
                        <dt>
                            Doanh thu thuần
                            <x-admin.trend :now="$orderStats['net_revenue']" :before="$previous['orders']['net_revenue'] ?? null" format="tien" />
                        </dt>
                        <dd><x-site.money :amount="$orderStats['net_revenue']" /></dd>
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
