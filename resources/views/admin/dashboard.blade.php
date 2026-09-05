@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')

<div class="mb-4">

    <h1 class="admin-page-title">
        Dashboard
    </h1>

    <p class="admin-page-subtitle">
        Xin chào {{ Auth::user()->name }}, đây là tổng quan hệ thống.
    </p>

</div>

{{--
    VIỆC CẦN LÀM — khối đầu tiên của trang.

    Bản trước, thứ đầu tiên admin nhìn thấy là bốn con số: bao nhiêu danh
    mục, bao nhiêu sản phẩm, bao nhiêu khách. Không con số nào nói cho họ
    biết PHẢI LÀM GÌ — chúng gần như không đổi từ ngày này sang ngày khác,
    và người ta học cách lướt qua.

    Mỗi dòng ở đây dẫn thẳng tới đúng danh sách ĐÃ LỌC SẴN, không bắt
    admin tự đi lọc lại.
--}}
<div class="admin-panel mb-4">

    <h2 class="h6 fw-bold mb-3">Việc cần làm</h2>

    @forelse($todo as $viec)
        <a href="{{ $viec['url'] }}" class="admin-todo admin-todo--{{ $viec['tone'] }}">
            <span class="admin-todo__count">{{ $viec['count'] }}</span>
            <span class="admin-todo__label">{{ $viec['label'] }}</span>
            <x-site.icon name="box-arrow-up-right" class="admin-todo__go" />
        </a>
    @empty
        {{-- Hết việc thì NÓI hết việc. Một danh sách toàn số 0 là danh
             sách không ai đọc, và đọc mãi thành quen bỏ qua. --}}
        <p class="admin-page-subtitle mb-0">
            Không có việc nào đang chờ. Đơn hàng, tồn kho và yêu cầu báo giá đều đã được xử lý.
        </p>
    @endforelse

</div>

<div class="row g-4">

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card__figure"><x-site.icon name="tags" /></div>
            <div>
                <div class="stat-card__value">{{ $stats['categories'] }}</div>
                <div class="stat-card__label">Danh mục</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card__figure"><x-site.icon name="flower1" /></div>
            <div>
                <div class="stat-card__value">{{ $stats['products'] }}</div>
                <div class="stat-card__label">Sản phẩm</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card__figure"><x-site.icon name="people" /></div>
            <div>
                <div class="stat-card__value">{{ $stats['customers'] }}</div>
                <div class="stat-card__label">Khách hàng</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <a href="{{ route('admin.bulk-inquiries.index', ['status' => 'new']) }}" class="text-decoration-none">
            <div class="stat-card card-hover">
                <div class="stat-card__figure"><x-site.icon name="envelope-paper" /></div>
                <div>
                    <div class="stat-card__value">{{ $stats['pending_inquiries'] }}</div>
                    <div class="stat-card__label">Yêu cầu chưa xử lý</div>
                </div>
            </div>
        </a>
    </div>

</div>

<div class="row g-4 mt-1">

    <div class="col-lg-6">

        <div class="admin-panel p-4 h-100">

            <h2 class="h6 fw-bold mb-3">
                <x-site.icon name="eye" />
                Sản phẩm được xem nhiều
            </h2>

            @if($topViewedProducts->isEmpty())

                <p class="text-muted small mb-0">Chưa có dữ liệu lượt xem.</p>

            @else

                <div class="d-flex flex-column gap-2">
                    @foreach($topViewedProducts as $product)
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                            <a href="{{ route('admin.products.show', $product) }}">{{ $product->name }}</a>
                            <span class="badge text-bg-light">{{ number_format($product->view_count) }} lượt</span>
                        </div>
                    @endforeach
                </div>

            @endif

        </div>

    </div>

    <div class="col-lg-6">

        <div class="admin-panel p-4 h-100">

            <h2 class="h6 fw-bold mb-3">
                <x-site.icon name="envelope-paper" />
                Yêu cầu gần đây
            </h2>

            @if($recentInquiries->isEmpty())

                <p class="text-muted small mb-0">Chưa có yêu cầu nào.</p>

            @else

                <div class="d-flex flex-column gap-2">
                    @foreach($recentInquiries as $inquiry)
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                            <a href="{{ route('admin.bulk-inquiries.show', $inquiry) }}">{{ $inquiry->contact_name }}</a>
                            <span class="badge {{ $inquiry->status->badgeClass() }}">{{ $inquiry->status->label() }}</span>
                        </div>
                    @endforeach
                </div>

            @endif

        </div>

    </div>

</div>

<div class="admin-panel p-4 mt-4">

    {{-- ============ ĐƠN HÀNG ============ --}}
    @if($orderStats === null)

        {{-- Cờ giỏ hàng tắt: nói rõ lý do thay vì hiện một dãy số 0. --}}
        <div class="admin-notice mb-4">
            <div class="admin-notice__title">Module đơn hàng đang tắt</div>
            <p class="admin-notice__body mb-0">
                Bật <code>FEATURE_CART=true</code> trong tệp <code>.env</code>
                để khách đặt hàng được và để số liệu đơn hàng hiện ở đây.
            </p>
        </div>

    @else

        <h2 class="h5 fw-bold mb-3">Đơn hàng</h2>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <x-admin.stat-card icon="bag" :value="$orderStats['total']" label="Tổng đơn" />
            </div>
            <div class="col-6 col-lg-3">
                <x-admin.stat-card icon="envelope" :value="$orderStats['pending']" label="Chờ xác nhận"
                                   :href="route('admin.orders.index', ['status' => 'pending'])" />
            </div>
            <div class="col-6 col-lg-3">
                <x-admin.stat-card icon="arrow-repeat" :value="$orderStats['open']" label="Đang xử lý" />
            </div>
            <div class="col-6 col-lg-3">
                <x-admin.stat-card icon="check-circle" :value="$orderStats['completed']" label="Đã giao" />
            </div>
        </div>

        <div class="admin-panel mb-4">

            <h2 class="h6 fw-bold mb-3">Doanh thu</h2>

            <p class="admin-stat-figure">
                <x-site.money :amount="$orderStats['revenue']" />
            </p>

            {{--
                Nói rõ con số này đếm cái gì. Doanh thu chỉ cộng đơn ĐÃ
                GIAO: đơn đang chờ chưa chắc thành tiền, đơn đã huỷ thì
                không bao giờ.
            --}}
            <p class="admin-page-subtitle mb-0">
                Chỉ tính {{ $orderStats['completed'] }} đơn đã giao thành công.
                Đơn đang xử lý và đơn đã huỷ không được cộng vào.
            </p>

        </div>

        @if($recentOrders->isNotEmpty())
            <div class="admin-panel mb-4">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 fw-bold mb-0">Đơn gần đây</h2>
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-admin btn-sm">Xem tất cả</a>
                </div>

                <div class="admin-list">
                    @foreach($recentOrders as $order)
                        <a href="{{ route('admin.orders.show', $order) }}" class="admin-list__row">
                            <span class="fw-bold">{{ $order->order_number }}</span>
                            <span class="admin-page-subtitle">{{ $order->recipient_name }}</span>
                            <span class="status-pill status-pill--{{ $order->status->badge() }}">
                                {{ $order->status->label() }}
                            </span>
                            <span class="fw-bold"><x-site.money :amount="(float) $order->grand_total" /></span>
                        </a>
                    @endforeach
                </div>

            </div>
        @endif

    @endif

    <h2 class="h5 fw-bold mb-3">Lối tắt</h2>

    <div class="d-flex flex-wrap gap-2">

        <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-admin px-4">
            Quản lý danh mục
        </a>

        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-admin px-4">
            Quản lý sản phẩm
        </a>

        <a href="{{ route('admin.bulk-inquiries.index') }}" class="btn btn-outline-admin px-4">
            Yêu cầu số lượng lớn
        </a>

    </div>

</div>

@endsection
