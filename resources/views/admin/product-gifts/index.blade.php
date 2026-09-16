@extends('layouts.admin')

@section('title', 'Quà tặng kèm sản phẩm')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Quà tặng kèm sản phẩm</h1>
    <p class="admin-page-subtitle mb-0">
        Quà mặc định của từng sản phẩm — khách mua là có, như "Mua 1 mặt hàng – nhận quà miễn phí".
        Quà theo chương trình (giới hạn suất, thời gian, hạng) nằm ở
        <a data-admin-link href="{{ route('admin.gift-campaigns.index') }}">Khuyến mại › Quà theo chương trình</a>.
    </p>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <form method="GET" action="{{ route('admin.product-gifts.open') }}" class="admin-panel p-3 d-flex gap-2" data-chon-san-pham-qua>
            <label class="visually-hidden" for="pg-chon">Chọn sản phẩm để thêm quà</label>
            <select id="pg-chon" name="product_id" class="form-select" required>
                <option value="">Chọn sản phẩm để thêm quà…</option>
                @foreach($chuaCoQua as $sp)
                    <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary-brand text-nowrap">Thêm quà</button>
        </form>
    </div>
    <div class="col-lg-6">
        <x-admin.filter-bar :action="route('admin.product-gifts.index')">
            <input type="search" name="q" class="form-control" placeholder="Tìm sản phẩm đã có quà" value="{{ request('q') }}" aria-label="Tìm sản phẩm">
        </x-admin.filter-bar>
    </div>
</div>

<div class="admin-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Sản phẩm</th>
                    <th scope="col">Quà kèm</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($sanPham as $sp)
                    <tr data-san-pham-co-qua="{{ $sp->id }}">
                        <td class="fw-bold">{{ $sp->name }}</td>
                        <td>
                            @foreach($quaTheoSanPham[$sp->id] ?? [] as $qua)
                                <div class="small {{ $qua->is_active ? '' : 'text-muted text-decoration-line-through' }}">
                                    {{ $qua->giftItem?->name }} × {{ $qua->gift_quantity }}
                                    @if($qua->per_quantity > 1) / mỗi {{ $qua->per_quantity }} sản phẩm @endif
                                    @if(($con = $qua->giftItem?->tonKhoCon()) !== null)
                                        <span class="admin-page-subtitle">· còn {{ $con }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </td>
                        <td class="text-end">
                            <a data-admin-link href="{{ route('admin.product-gifts.edit', $sp) }}" class="btn btn-sm btn-outline-admin">Sửa quà</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center py-5 text-muted">Chưa sản phẩm nào có quà kèm. Chọn một sản phẩm ở trên để bắt đầu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $sanPham->links() }}</div>

<p class="admin-page-subtitle small mt-3">
    Vật phẩm tặng riêng (túi vải, thẻ chăm cây…) và tồn kho của chúng: <a data-admin-link href="{{ route('admin.gift-items.index') }}">Kho vật phẩm quà</a>.
</p>

@endsection
