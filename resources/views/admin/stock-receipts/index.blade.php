@extends('layouts.admin')

@section('title', 'Nhập kho')

@section('content')

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) round($v));
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Phiếu nhập kho</h1>
        <p class="admin-page-subtitle">
            Mỗi lần hàng về là một phiếu. Phiếu ghi sổ xong mới cộng vào tồn kho.
        </p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a data-admin-link href="{{ route('admin.stock-receipts.index') }}"
           class="btn btn-sm {{ $trangThai === null ? 'btn-primary-brand' : 'btn-outline-admin' }}">Tất cả</a>

        @foreach($cacTrangThai as $tt)
            <a data-admin-link href="{{ route('admin.stock-receipts.index', ['trang-thai' => $tt->value]) }}"
               class="btn btn-sm {{ $trangThai === $tt ? 'btn-primary-brand' : 'btn-outline-admin' }}">{{ $tt->label() }}</a>
        @endforeach

        <a data-admin-link href="{{ route('admin.stock-receipts.create') }}" class="btn btn-sm btn-primary-brand">
            <x-site.icon name="plus" /> Lập phiếu nhập
        </a>
    </div>
</div>

<x-admin.nhom-tab ten="nhap-kho" />

<div class="admin-panel p-4">
    @if($receipts->isEmpty())
        <p class="analytics-empty mb-0">Chưa có phiếu nhập nào.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Mã phiếu</th>
                        <th>Ngày nhập</th>
                        <th>Nhà cung cấp</th>
                        <th>Dòng</th>
                        <th>Đơn vị</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($receipts as $r)
                        <tr>
                            <td class="fw-bold">{{ $r->code }}</td>
                            <td>{{ $r->received_at->format('d/m/Y') }}</td>
                            <td>{{ $r->supplier ?: '—' }}</td>
                            <td>{{ $r->items->count() }}</td>
                            <td>{{ number_format($r->totalQuantity(), 0, ',', '.') }}</td>
                            <td>
                                {{ $tien($r->totalCost()) }}
                                @if($r->hasUnpricedItems())
                                    {{-- NÓI RA CHỖ THIẾU. Tổng tiền bỏ qua dòng
                                         chưa điền giá; im lặng thì con số đọc ra
                                         như đã đủ. --}}
                                    <span class="text-muted small">· có dòng chưa điền giá</span>
                                @endif
                            </td>
                            <td>
                                <span class="status-pill status-pill--{{ $r->status->badge() }}">
                                    {{ $r->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a data-admin-link href="{{ route('admin.stock-receipts.show', $r) }}"
                                   class="btn btn-outline-admin btn-sm">Xem</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $receipts->links() }}</div>
    @endif
</div>

@endsection
