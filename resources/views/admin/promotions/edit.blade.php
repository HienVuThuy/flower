@extends('layouts.admin')

@section('title', 'Sửa chương trình khuyến mại')

@section('content')

@php $laQua = $promotion->laTangQua(); @endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

    <div>
        <h1 class="admin-page-title">{{ $promotion->name }}</h1>
        <p class="admin-page-subtitle">
            <span class="status-chip {{ $promotion->effectiveStatus()->chipClass() }}">
                {{ $promotion->effectiveStatus()->label() }}
            </span>
            <span class="ms-2">{{ $promotion->type->label() }} · {{ $promotion->products->count() }} sản phẩm</span>
            @if($promotion->used_count > 0 || $laQua)
                <span class="ms-2">· Đã phát quà cho {{ $promotion->used_count }}{{ $promotion->total_limit !== null ? '/' . $promotion->total_limit : '' }} đơn</span>
            @endif
        </p>
    </div>

    <a href="{{ route('admin.promotions.index') }}" class="btn btn-light px-4">Quay lại danh sách</a>

</div>

<form action="{{ route('admin.promotions.update', $promotion) }}" method="POST" enctype="multipart/form-data">

    @csrf
    @method('PUT')

    @include('admin.promotions._form')

    <div class="d-flex justify-content-end mt-3 mb-5">
        <button type="submit" class="btn btn-primary-brand px-4">Lưu thông tin chương trình</button>
    </div>

</form>

@php
    $tienTe = [
        'code' => \App\Services\Shop\Money::code(),
        'symbol' => \App\Services\Shop\Money::symbol(),
        'position' => \App\Services\Shop\Money::position(),
        'decimals' => \App\Services\Shop\Money::decimals(),
    ];
@endphp

<form action="{{ route('admin.promotions.sync-products', $promotion) }}" method="POST" id="productsForm"
      data-km-san-pham
      data-kieu="{{ $promotion->type->value }}"
      data-muc="{{ (float) $promotion->discount_value }}"
      data-qua-chung="{{ $promotion->giftItem ? $promotion->gift_quantity . ' × ' . $promotion->giftItem->name : '' }}"
      data-tien-te="{{ json_encode($tienTe) }}">

    @csrf
    @method('PUT')

    <div class="admin-panel">

        <div class="admin-panel__header">
            <div>
                <h2 class="form-panel__title mb-1">Sản phẩm áp dụng và ưu đãi từng sản phẩm</h2>
                <p class="admin-page-subtitle mb-0">
                    Mỗi sản phẩm mặc định nhận ưu đãi của chương trình
                    (<strong>{{ $laQua
                        ? 'Tặng ' . ($promotion->giftItem ? $promotion->gift_quantity . ' × ' . $promotion->giftItem->name : 'quà — chưa chọn quà chung')
                        : rtrim(rtrim(number_format($promotion->discount_value, 2, ',', '.'), '0'), ',') . $promotion->type->unit() }}</strong>).
                    Đổi cột "Ưu đãi" để sản phẩm đó được mức giảm khác, hoặc được <strong>tặng quà riêng</strong> thay vì giảm giá.
                    Quà tặng theo điều kiện nhận quà của chương trình; mỗi đơn chỉ tính một suất dù có nhiều quà.
                    @if($laQua)
                        Chương trình tặng quà không gắn sản phẩm nào thì tặng quà chung cho mọi đơn đạt điều kiện.
                    @endif
                </p>
            </div>
        </div>

        <div class="admin-panel__body">

            <div class="d-flex gap-2 mb-3 flex-wrap">
                <select id="productPicker" class="form-select" style="max-width: 420px;" data-chon-san-pham>
                    <option value="">— Chọn sản phẩm để thêm —</option>
                    @foreach($availableProducts as $product)
                        <option
                            value="{{ $product->id }}"
                            data-name="{{ $product->name }}"
                            data-category="{{ $product->category->name }}"
                            data-price="{{ $product->base_price }}"
                        >
                            {{ $product->name }} — {{ $product->category->name }}
                        </option>
                    @endforeach
                </select>

                <button type="button" class="btn btn-secondary-brand" data-them-san-pham>
                    + Thêm vào chương trình
                </button>
            </div>

            <div class="table-responsive">

                <table class="admin-table align-middle mb-0" id="promoProductsTable">

                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th style="width: 120px;">Giá gốc</th>
                            <th style="width: 170px;">Ưu đãi</th>
                            <th style="min-width: 260px;">Mức giảm / Quà tặng</th>
                            <th style="width: 170px;">Kết quả</th>
                            <th style="width: 60px;"></th>
                        </tr>
                    </thead>

                    <tbody data-dong-san-pham>

                    @foreach($promotion->products as $i => $product)
                        @php
                            $pivot = $product->pivot;
                            $kieuDong = $promotion->kieuCho($pivot);

                            if ($kieuDong === \App\Enums\PromotionType::TangQua) {
                                $vatDong = $pivot->gift_item_id ? $vatPham->firstWhere('id', $pivot->gift_item_id) : $promotion->giftItem;
                                $ketQua = $vatDong ? 'Tặng ' . ($pivot->gift_quantity ?? $promotion->gift_quantity) . ' × ' . $vatDong->name : 'Chưa chọn quà';
                            } else {
                                $giaSau = $pricing->preview($product->base_price, $kieuDong, (float) ($pivot->discount_value ?? $promotion->discount_value));
                                $ketQua = $giaSau !== null ? \App\Services\Shop\Money::format($giaSau) : '—';
                            }
                        @endphp

                        @include('admin.promotions._dong-san-pham', [
                            'i' => $i,
                            'id' => $product->id,
                            'ten' => $product->name,
                            'danhMuc' => $product->category->name,
                            'gia' => $product->base_price,
                            'kieu' => $pivot->discount_type,
                            'muc' => $pivot->discount_value,
                            'qua' => $pivot->gift_item_id ? 'vp:' . $pivot->gift_item_id : null,
                            'soQua' => $pivot->gift_quantity,
                            'ketQua' => $ketQua,
                        ])
                    @endforeach

                    </tbody>

                </table>

            </div>

            <div data-trong class="{{ $promotion->products->isEmpty() ? '' : 'd-none' }}">
                <x-site.empty-state
                    title="Chưa có sản phẩm nào"
                    text="Chọn sản phẩm ở ô phía trên để thêm vào chương trình."
                />
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary-brand px-4">Lưu sản phẩm và ưu đãi</button>
            </div>

        </div>

    </div>

</form>

<template data-mau-dong>
    @include('admin.promotions._dong-san-pham', [
        'i' => '__I__',
        'id' => '__ID__',
        'ten' => '__NAME__',
        'danhMuc' => '__CATEGORY__',
        'gia' => '__PRICE__',
    ])
</template>

@endsection
