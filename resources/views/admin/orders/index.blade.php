@extends('layouts.admin')

@section('title', 'Đơn hàng')

@section('content')

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="admin-page-title">Đơn hàng</h1>
            <p class="admin-page-subtitle">Xem và cập nhật trạng thái đơn khách đặt.</p>
        </div>
    </div>

    {{-- Tab lọc theo trạng thái. --}}
    <div class="admin-filter-tabs">

        @php($giuLoc = request()->except(['status', 'page']))

        <a href="{{ route('admin.orders.index', $giuLoc) }}"
           class="admin-filter-tab {{ $currentStatus === null ? 'is-active' : '' }}">
            Tất cả
            <span class="admin-filter-tab__count">{{ array_sum($counts) }}</span>
        </a>

        @foreach($statuses as $status)
            <a href="{{ route('admin.orders.index', $giuLoc + ['status' => $status->value]) }}"
               class="admin-filter-tab {{ $currentStatus === $status->value ? 'is-active' : '' }}">
                {{ $status->label() }}
                <span class="admin-filter-tab__count">{{ $counts[$status->value] ?? 0 }}</span>
            </a>
        @endforeach

    </div>

    <x-admin.filter-bar
        :action="route('admin.orders.index')"
        placeholder="Mã đơn, SĐT, tên, vận đơn, sản phẩm…"
        :total="$orders->total()"
    >
        @if($currentStatus)
            <input type="hidden" name="status" value="{{ $currentStatus }}">
        @endif

        <select name="payment" class="form-select" aria-label="Lọc theo thanh toán">
            <option value="">Mọi tình trạng trả tiền</option>
            <option value="unpaid" @selected(request('payment') === 'unpaid')>Chưa thanh toán</option>
            <option value="paid" @selected(request('payment') === 'paid')>Đã thanh toán</option>
            <option value="refunded" @selected(request('payment') === 'refunded')>Đã hoàn tiền</option>
        </select>

        <select name="phuong_thuc" class="form-select" aria-label="Lọc theo phương thức thanh toán">
            <option value="">Mọi phương thức</option>
            @foreach($phuongThuc as $pt)
                <option value="{{ $pt->value }}" @selected(request('phuong_thuc') === $pt->value)>{{ $pt->label() }}</option>
            @endforeach
        </select>

        <select name="van_chuyen" class="form-select" aria-label="Lọc theo trạng thái vận chuyển">
            <option value="">Mọi trạng thái vận chuyển</option>
            @foreach($vanChuyen as $vc)
                <option value="{{ $vc->value }}" @selected(request('van_chuyen') === $vc->value)>{{ $vc->label() }}</option>
            @endforeach
        </select>

        <select name="van_don" class="form-select" aria-label="Lọc theo vận đơn">
            <option value="">Mọi tình trạng vận đơn</option>
            <option value="cho-tao" @selected(request('van_don') === 'cho-tao')>Chờ tạo vận đơn</option>
            <option value="roi" @selected(request('van_don') === 'roi')>Đã có vận đơn</option>
        </select>

        <input type="date" name="tu_ngay" value="{{ request('tu_ngay') }}"
               class="form-control" style="width:auto" aria-label="Từ ngày">
        <input type="date" name="den_ngay" value="{{ request('den_ngay') }}"
               class="form-control" style="width:auto" aria-label="Đến ngày">

        <select name="moi_trang" class="form-select" style="width:auto" aria-label="Số đơn mỗi trang">
            @foreach([20, 50, 100] as $n)
                <option value="{{ $n }}" @selected((int) request('moi_trang', 20) === $n)>{{ $n }} / trang</option>
            @endforeach
        </select>
    </x-admin.filter-bar>

    <p class="admin-page-subtitle small">Số trên từng tab đếm theo bộ lọc đang chọn. Đơn đang giao không huỷ được — chỉ ghi nhận hoàn hàng khi hàng đã về cửa hàng.</p>

    <div class="admin-panel">

        @if($orders->isEmpty())

            <div class="p-4 text-center admin-page-subtitle">
                <p class="mb-0">
                    @if(request()->hasAny(['q', 'payment', 'phuong_thuc', 'van_chuyen', 'van_don', 'hoan_tien', 'tu_ngay', 'den_ngay']))
                        Không có đơn nào khớp với bộ lọc.
                        <a href="{{ route('admin.orders.index') }}" class="ms-1">Xoá lọc</a>
                    @elseif($currentStatus)
                        Không có đơn nào ở trạng thái này.
                    @else
                        Chưa có đơn hàng nào.
                    @endif
                </p>
            </div>

        @else

            <div class="table-responsive">

                <table class="admin-table align-middle mb-0">

                    <thead>
                        <tr>
                            <x-admin.sort-header khoa="ma" nhan="Mã đơn" />
                            <th>Khách hàng</th>
                            <th>Sản phẩm</th>
                            <x-admin.sort-header khoa="tien" nhan="Tổng tiền" dau="giam" />
                            <x-admin.sort-header khoa="thanh-toan" nhan="Thanh toán" />
                            <x-admin.sort-header khoa="trang-thai" nhan="Trạng thái" />
                            <x-admin.sort-header khoa="ngay" nhan="Ngày đặt" dau="giam" />
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td class="fw-bold">
                                    <a href="{{ route('admin.orders.show', $order) }}" data-admin-link title="Xem đơn {{ $order->order_number }}">{{ $order->order_number }}</a>

                                    @if($order->needsRiskReview())
                                        <span class="risk-flag"
                                              title="{{ collect($order->riskFlags())->pluck('label')->implode(' · ') }}">
                                            ⚠ {{ $order->risk_score }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    {{ $order->recipient_name }}
                                    <div class="admin-page-subtitle">{{ $order->recipient_phone }}</div>
                                </td>

                                <td class="o-dai">
                                    {{ $order->items->first()?->product_name }}
                                    @if($order->items_count > 1)
                                        <div class="admin-page-subtitle">và {{ $order->items_count - 1 }} món khác</div>
                                    @endif
                                </td>

                                <td class="fw-bold">
                                    <x-site.money :amount="(float) $order->grand_total" />
                                </td>

                                <td class="o-vua">
                                    <span class="status-pill status-pill--{{ $order->payment_status->badge() }}">
                                        {{ $order->payment_status->label() }}
                                    </span>
                                    <div class="admin-page-subtitle">{{ $order->payment_method->label() }}</div>
                                </td>

                                <td class="o-vua">
                                    <span class="status-pill status-pill--{{ $order->status->badge() }}">
                                        {{ $order->status->label() }}
                                    </span>
                                    @if($order->shipping_status)
                                        <div class="admin-page-subtitle">{{ \App\Enums\ShippingStatus::tryFrom($order->shipping_status)?->label() ?? $order->shipping_status }}</div>
                                    @endif
                                </td>

                                <td>
                                    <x-site.time :at="$order->created_at" format="d/m/Y" />
                                    <div class="admin-page-subtitle"><x-site.time :at="$order->created_at" format="H:i" /></div>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>

                </table>

            </div>

        @endif

    </div>

    @if($orders->hasPages())
        <div class="mt-3">{{ $orders->links() }}</div>
    @endif

@endsection
