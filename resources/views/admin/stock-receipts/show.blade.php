@extends('layouts.admin')

@section('title', 'Phiếu ' . $receipt->code)

@section('content')

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) round($v));
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">
            {{ $receipt->laTonDauKy() ? 'Phiếu tồn đầu kỳ' : 'Phiếu nhập' }} {{ $receipt->code }}
        </h1>

        <p class="admin-page-subtitle mb-0">{{ $receipt->kind->hint() }}</p>
        <p class="admin-page-subtitle mb-0">
            <span class="status-pill status-pill--{{ $receipt->status->badge() }}">
                {{ $receipt->status->label() }}
            </span>
            &middot; {{ $receipt->status->hint() }}
        </p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        @unless($receipt->isPosted())
            <form method="POST" action="{{ route('admin.stock-receipts.post', $receipt) }}"
                  onsubmit="return confirm('Ghi sổ phiếu {{ $receipt->code }}? {{ $receipt->laTonDauKy() ? 'Phiếu này chỉ khai giá vốn, KHÔNG cộng vào tồn.' : 'Tồn kho sẽ được cộng thêm và không hoàn tác được.' }}');">
                @csrf
                <button type="submit" class="btn btn-primary-brand">Ghi sổ &amp; cộng vào kho</button>
            </form>

            <form method="POST" action="{{ route('admin.stock-receipts.destroy', $receipt) }}"
                  onsubmit="return confirm('Xoá phiếu nháp {{ $receipt->code }}?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-admin">Xoá phiếu</button>
            </form>
        @endunless

        <a data-admin-link href="{{ route('admin.stock-receipts.index') }}" class="btn btn-ghost">Về danh sách</a>
    </div>
</div>

<div class="row g-3">

    <div class="col-lg-8">
        <div class="admin-panel p-4">
            <h2 class="h6 fw-bold mb-3">Hàng nhập</h2>

            @if($receipt->items->isEmpty())
                <p class="analytics-empty mb-0">
                    Phiếu chưa có dòng hàng nào. Không ghi sổ được — hãy xoá và lập lại.
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Mặt hàng</th>
                                <th>Số lượng</th>
                                <th>Giá vốn / đơn vị</th>
                                <th>Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($receipt->items as $d)
                                <tr>
                                    <td>
                                        {{ $d->product_name }}
                                        @if($d->variant_name)
                                            <span class="text-muted">— {{ $d->variant_name }}</span>
                                        @endif

                                        @unless($d->product)
                                            <span class="text-muted small">(sản phẩm đã xoá)</span>
                                        @endunless
                                    </td>

                                    <td class="{{ $d->quantity < 0 ? 'text-danger' : '' }}">
                                        {{ number_format($d->quantity, 0, ',', '.') }}
                                    </td>

                                    <td>
                                        {{ $d->unit_cost === null ? '— chưa điền' : $tien($d->unit_cost) }}
                                    </td>

                                    <td>{{ $d->lineCost() === null ? '—' : $tien($d->lineCost()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-panel p-4">
            <h2 class="h6 fw-bold mb-3">Thông tin phiếu</h2>

            <dl class="admin-detail-list">
                <div><dt>Ngày nhập</dt><dd>{{ $receipt->received_at->format('d/m/Y') }}</dd></div>
                <div><dt>Nhà cung cấp</dt><dd>{{ $receipt->supplier ?: '—' }}</dd></div>
                <div><dt>Người lập</dt><dd>{{ $receipt->actorLabel() }}</dd></div>
                <div><dt>Tổng đơn vị</dt><dd>{{ number_format($receipt->totalQuantity(), 0, ',', '.') }}</dd></div>
                <div>
                    <dt>Tổng tiền</dt>
                    <dd class="fw-bold">{{ $tien($receipt->totalCost()) }}</dd>
                </div>

                @if($receipt->isPosted())
                    <div><dt>Ghi sổ lúc</dt><dd><x-site.time :at="$receipt->posted_at" /></dd></div>
                @endif
            </dl>

            @if($receipt->hasUnpricedItems())
                <div class="alert alert-warning py-2 px-3 small mb-0">
                    Có dòng chưa điền giá vốn. Tổng tiền ở trên <strong>bỏ qua</strong> những dòng đó,
                    không tính chúng bằng 0₫.
                </div>
            @endif

            @if($receipt->note)
                <p class="admin-page-subtitle mt-3 mb-0">{{ $receipt->note }}</p>
            @endif
        </div>
    </div>

</div>

@endsection
