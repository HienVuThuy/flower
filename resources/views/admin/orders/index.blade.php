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
        placeholder="Tìm mã đơn, số điện thoại hoặc tên người nhận…"
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

        <select name="van_don" class="form-select" aria-label="Lọc theo vận đơn">
            <option value="">Mọi tình trạng vận đơn</option>
            <option value="cho-tao" @selected(request('van_don') === 'cho-tao')>Chờ tạo vận đơn</option>
            <option value="roi" @selected(request('van_don') === 'roi')>Đã có vận đơn</option>
        </select>

        <input type="date" name="tu_ngay" value="{{ request('tu_ngay') }}"
               class="form-control" style="width:auto" aria-label="Từ ngày">
        <input type="date" name="den_ngay" value="{{ request('den_ngay') }}"
               class="form-control" style="width:auto" aria-label="Đến ngày">
    </x-admin.filter-bar>

    <div class="admin-panel">

        @if($orders->isEmpty())

            <div class="p-4 text-center admin-page-subtitle">
                <p class="mb-0">
                    @if(request()->hasAny(['q', 'payment', 'van_don', 'hoan_tien', 'tu_ngay', 'den_ngay']))
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
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td class="fw-bold">
                                    {{ $order->order_number }}

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

                                <td>{{ $order->items_count }} dòng</td>

                                <td class="fw-bold">
                                    <x-site.money :amount="(float) $order->grand_total" />
                                </td>

                                <td>
                                    <span class="status-pill status-pill--{{ $order->payment_status->badge() }}">
                                        {{ $order->payment_status->label() }}
                                    </span>
                                    <div class="admin-page-subtitle">{{ $order->payment_method->label() }}</div>
                                </td>

                                <td>
                                    <span class="status-pill status-pill--{{ $order->status->badge() }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </td>

                                <td><x-site.time :at="$order->created_at" format="d/m/Y H:i" /></td>

                                <td class="text-end">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline-admin btn-sm">
                                        Xem
                                    </a>
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
