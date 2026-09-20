@extends('layouts.admin')

@section('title', 'Vật phẩm quà tặng')

@section('content')

<x-admin.promo-tabs />

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Vật phẩm quà tặng</h1>
        <p class="admin-page-subtitle mb-0">
            Thứ được tặng: một sản phẩm đang có (dùng chung tồn kho) hoặc một vật phẩm tặng riêng không bán (tồn kho riêng).
            Dùng cho <a data-admin-link href="{{ route('admin.product-gifts.index') }}">quà kèm sản phẩm</a> và
            <a data-admin-link href="{{ route('admin.promotions.index') }}">chương trình khuyến mại tặng quà</a>.
        </p>
    </div>
    <a data-admin-link href="{{ route('admin.gift-items.create') }}" class="btn btn-primary-brand">Thêm vật phẩm</a>
</div>

<div class="admin-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Tên</th>
                    <th scope="col">Loại</th>
                    <th scope="col">Nguồn</th>
                    <th scope="col">Còn tặng được</th>
                    <th scope="col">Tình trạng</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($vatPham as $vat)
                    @php
                        $con = $vat->tonKhoCon();
                    @endphp
                    <tr data-vat-pham="{{ $vat->id }}">
                        <td>
                            {{ $vat->name }}
                            @if($vat->value !== null)
                                <span class="d-block admin-page-subtitle small">Trị giá {{ \App\Services\Shop\Money::format((string) $vat->value) }}</span>
                            @endif
                        </td>
                        <td>{{ $vat->kind->label() }}</td>
                        <td class="o-dai">
                            @if($vat->laSanPham())
                                Sản phẩm: {{ $vat->product?->name ?? 'đã xoá' }}@if($vat->variant) — {{ $vat->variant->name }}@endif
                            @else
                                Vật phẩm tặng riêng
                            @endif
                            <span class="d-block admin-page-subtitle small">{{ $vat->promotions_count }} chương trình</span>
                        </td>
                        <td>{{ $con === null ? 'Không giới hạn' : number_format($con, 0, ',', '.') }}</td>
                        <td>
                            <span class="badge text-bg-{{ $vat->is_active ? 'success' : 'secondary' }}">{{ $vat->is_active ? 'Đang dùng' : 'Ngừng' }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a data-admin-link href="{{ route('admin.gift-items.edit', $vat) }}" class="btn btn-sm btn-outline-admin">Sửa</a>
                            <form method="POST" action="{{ route('admin.gift-items.destroy', $vat) }}" class="d-inline"
                                  onsubmit="return confirm('Xoá vật phẩm này?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Xoá</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-5 text-muted">Chưa có vật phẩm quà nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $vatPham->links() }}</div>

@endsection
