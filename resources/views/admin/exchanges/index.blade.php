@extends('layouts.admin')

@section('title', 'Đổi hàng')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Phiếu đổi hàng</h1>
    <p class="admin-page-subtitle">
        Hạn đổi {{ \App\Services\Exchange\ExchangeService::HAN_DOI_NGAY }} ngày kể từ khi giao.
        Phiếu lập từ trang đơn hàng.
    </p>
</div>

<x-admin.filter-bar :action="route('admin.exchanges.index')">
    <input type="search" name="q" class="form-control" placeholder="Mã phiếu hoặc mã đơn"
           value="{{ request('q') }}" aria-label="Tìm phiếu đổi hàng">

    <select name="trang_thai" class="form-select" aria-label="Lọc theo tình trạng">
        <option value="">Mọi tình trạng</option>
        @foreach($trangThai as $tt)
            <option value="{{ $tt->value }}" @selected(request('trang_thai') === $tt->value)>
                {{ $tt->label() }}
            </option>
        @endforeach
    </select>
</x-admin.filter-bar>

<div class="admin-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Phiếu</th>
                    <th scope="col">Đơn</th>
                    <th scope="col">Lý do</th>
                    <th scope="col">Chênh lệch</th>
                    <th scope="col">Tình trạng</th>
                    <th scope="col">Người lập</th>
                </tr>
            </thead>
            <tbody>
                @forelse($phieu as $p)
                    <tr>
                        <td>
                            <a data-admin-link href="{{ route('admin.exchanges.show', $p) }}">{{ $p->code }}</a>
                            <span class="d-block admin-page-subtitle small">
                                <x-site.time :at="$p->created_at" format="d/m/Y H:i" />
                            </span>
                        </td>
                        <td>
                            @if($p->order)
                                <a data-admin-link href="{{ route('admin.orders.show', $p->order) }}">
                                    {{ $p->order->order_number }}
                                </a>
                            @else
                                <span class="admin-page-subtitle">—</span>
                            @endif
                        </td>
                        <td>{{ $p->reason->label() }}</td>
                        <td>
                            @if(bccomp((string) $p->chenh_lech, '0', 2) === 0)
                                <span class="admin-page-subtitle">đổi ngang</span>
                            @elseif($p->cuaHangNoLai())
                                <span class="text-danger-emphasis">
                                    &minus;<x-site.money :amount="(string) abs((float) $p->chenh_lech)" />
                                </span>
                            @else
                                +<x-site.money :amount="(string) $p->chenh_lech" />
                            @endif
                        </td>
                        <td><span class="badge text-bg-{{ $p->status->tone() }}">{{ $p->status->label() }}</span></td>
                        <td>
                            {{-- Người lập có thể đã bị xoá tài khoản; phiếu thì vẫn còn. --}}
                            {{ $p->createdBy?->name ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="6">
                        Chưa có phiếu đổi hàng nào.
                    </x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $phieu->links() }}</div>

@endsection
