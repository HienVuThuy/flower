@extends('layouts.admin')

@section('title', 'Hoàn tiền')

@section('content')

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
@endphp

<div class="mb-4">
    <h1 class="admin-page-title">Hoàn tiền</h1>
    <p class="admin-page-subtitle">
        Mọi khoản đã trả lại khách, ở một chỗ để đối soát. Lập hoàn tiền mới ở trang chi tiết của từng đơn.
    </p>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
        <x-admin.kpi label="Đã hoàn (theo bộ lọc)" note="Chỉ cộng khoản đã xong, không cộng khoản chưa rõ hay thất bại.">
            <span data-da-hoan>{{ $tien($daHoan) }}</span>
        </x-admin.kpi>
    </div>
    <div class="col-sm-6 col-lg-3">
        <x-admin.kpi label="Chưa rõ kết quả" note="Tiền có thể đã đi hoặc chưa — cần kiểm tra.">
            <span class="{{ $chuaRo > 0 ? 'text-danger' : '' }}" data-chua-ro>{{ $chuaRo }}</span>
        </x-admin.kpi>
    </div>
</div>

<x-admin.filter-bar :action="route('admin.refunds.index')" placeholder="Mã hoàn tiền hoặc mã đơn" :total="$hoanTien->total()">
    <select name="trang_thai" class="form-select" aria-label="Lọc theo trạng thái">
        <option value="">Mọi trạng thái</option>
        @foreach($trangThai as $tt)
            <option value="{{ $tt->value }}" @selected(request('trang_thai') === $tt->value)>{{ $tt->label() }}</option>
        @endforeach
    </select>

    <select name="phuong_thuc" class="form-select" aria-label="Lọc theo cách hoàn">
        <option value="">Mọi cách hoàn</option>
        @foreach($phuongThuc as $pt)
            <option value="{{ $pt->value }}" @selected(request('phuong_thuc') === $pt->value)>{{ $pt->label() }}</option>
        @endforeach
    </select>
</x-admin.filter-bar>

<div class="admin-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Mã</th>
                    <th scope="col">Đơn</th>
                    <th scope="col" class="text-end">Số tiền</th>
                    <th scope="col">Lý do</th>
                    <th scope="col">Cách hoàn</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">Người lập</th>
                </tr>
            </thead>
            <tbody>
                @forelse($hoanTien as $r)
                    <tr>
                        <td>
                            {{ $r->code }}
                            <span class="d-block admin-page-subtitle small">
                                <x-site.time :at="$r->created_at" format="d/m/Y H:i" />
                            </span>
                        </td>
                        <td>
                            @if($r->order)
                                <a data-admin-link href="{{ route('admin.orders.show', $r->order) }}">{{ $r->order->order_number }}</a>
                                <span class="d-block admin-page-subtitle small">{{ $r->order->recipient_name }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end text-nowrap fw-bold">{{ $tien($r->amount) }}</td>
                        <td>{{ $r->reason?->label() ?? '—' }}</td>
                        <td>{{ $r->method?->label() ?? '—' }}</td>
                        <td>
                            <span class="badge text-bg-{{ $r->status->badge() }}">{{ $r->status->label() }}</span>
                            @if($r->completed_at)
                                <span class="d-block admin-page-subtitle small">
                                    xong <x-site.time :at="$r->completed_at" format="d/m/Y" />
                                </span>
                            @endif
                        </td>
                        <td>{{ $r->created_by_name ?? '—' }}</td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="7">
                        Chưa có khoản hoàn tiền nào khớp bộ lọc.
                    </x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $hoanTien->links() }}</div>

@endsection
