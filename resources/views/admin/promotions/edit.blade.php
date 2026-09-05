@extends('layouts.admin')

@section('title', 'Sửa chương trình khuyến mại')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

    <div>
        <h1 class="admin-page-title">{{ $promotion->name }}</h1>
        <p class="admin-page-subtitle">
            <span class="status-chip {{ $promotion->effectiveStatus()->chipClass() }}">
                {{ $promotion->effectiveStatus()->label() }}
            </span>
            <span class="ms-2">{{ $promotion->products->count() }} sản phẩm áp dụng</span>
        </p>
    </div>

    <a href="{{ route('admin.promotions.index') }}" class="btn btn-light px-4">Quay lại danh sách</a>

</div>

{{-- ============ 1. THÔNG TIN CHƯƠNG TRÌNH ============ --}}
<form action="{{ route('admin.promotions.update', $promotion) }}" method="POST" enctype="multipart/form-data">

    @csrf
    @method('PUT')

    @include('admin.promotions._form')

    <div class="d-flex justify-content-end mt-3 mb-5">
        <button type="submit" class="btn btn-primary-brand px-4">Lưu thông tin chương trình</button>
    </div>

</form>

{{-- ============ 2. SẢN PHẨM ÁP DỤNG ============ --}}
<form action="{{ route('admin.promotions.sync-products', $promotion) }}" method="POST" id="productsForm">

    @csrf
    @method('PUT')

    <div class="admin-panel">

        <div class="admin-panel__header">
            <div>
                <h2 class="form-panel__title mb-1">Sản phẩm áp dụng</h2>
                <p class="admin-page-subtitle mb-0">
                    Để trống cột ghi đè thì sản phẩm dùng mức chung của chương trình
                    ({{ rtrim(rtrim(number_format($promotion->discount_value, 2, ',', '.'), '0'), ',') }}{{ $promotion->type->unit() }}).
                </p>
            </div>
        </div>

        <div class="admin-panel__body">

            {{-- Thêm sản phẩm --}}
            <div class="d-flex gap-2 mb-3 flex-wrap">
                <select id="productPicker" class="form-select" style="max-width: 420px;">
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

                <button type="button" class="btn btn-secondary-brand" id="addProductBtn">
                    + Thêm vào chương trình
                </button>
            </div>

            <div class="table-responsive">

                <table class="admin-table align-middle mb-0" id="promoProductsTable">

                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th style="width: 130px;">Giá gốc</th>
                            <th style="width: 160px;">Ghi đè kiểu</th>
                            <th style="width: 130px;">Ghi đè mức</th>
                            <th style="width: 140px;">Giá sau KM</th>
                            <th style="width: 60px;"></th>
                        </tr>
                    </thead>

                    <tbody id="promoProductsBody">

                    @foreach($promotion->products as $i => $product)
                        @php
                            $pivot = $product->pivot;
                            $effType = $pivot->discount_type
                                ? \App\Enums\PromotionType::from($pivot->discount_type)
                                : $promotion->type;
                            $effValue = $pivot->discount_value ?? $promotion->discount_value;
                            $finalPrice = $pricing->preview($product->base_price, $effType, (float) $effValue);
                        @endphp

                        <tr data-row>
                            <td>
                                <input type="hidden" name="products[{{ $i }}][id]" value="{{ $product->id }}">
                                <div class="fw-semibold">{{ $product->name }}</div>
                                <small class="text-muted">{{ $product->category->name }}</small>
                            </td>

                            <td data-base-price="{{ $product->base_price }}">
                                {{ $product->base_price !== null
                                    ? \App\Services\Shop\Money::format($product->base_price)
                                    : '—' }}
                            </td>

                            <td>
                                <select name="products[{{ $i }}][discount_type]" class="form-select form-select-sm" data-type>
                                    <option value="">Theo chương trình</option>
                                    @foreach($types as $type)
                                        <option value="{{ $type->value }}" @selected($pivot->discount_type === $type->value)>
                                            {{ $type->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            <td>
                                <input
                                    type="number"
                                    name="products[{{ $i }}][discount_value]"
                                    value="{{ $pivot->discount_value !== null ? rtrim(rtrim($pivot->discount_value, '0'), '.') : '' }}"
                                    min="0"
                                    step="any"
                                    class="form-control form-control-sm"
                                    placeholder="—"
                                    data-value
                                >
                            </td>

                            <td class="fw-semibold text-accent" data-final>
                                {{ $finalPrice !== null ? \App\Services\Shop\Money::format($finalPrice) : '—' }}
                            </td>

                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger" data-remove>Xóa</button>
                            </td>
                        </tr>
                    @endforeach

                    </tbody>

                </table>

            </div>

            <div id="emptyProducts" class="{{ $promotion->products->isEmpty() ? '' : 'd-none' }}">
                <x-site.empty-state
                    title="Chưa có sản phẩm nào"
                    text="Chọn sản phẩm ở ô phía trên để thêm vào chương trình."
                />
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary-brand px-4">Lưu danh sách sản phẩm</button>
            </div>

        </div>

    </div>

</form>

{{-- Mẫu dòng cho sản phẩm mới thêm --}}
<template id="promoRowTemplate">
    <tr data-row>
        <td>
            <input type="hidden" name="products[__I__][id]" value="__ID__">
            <div class="fw-semibold">__NAME__</div>
            <small class="text-muted">__CATEGORY__</small>
        </td>
        <td data-base-price="__PRICE__">__PRICE_LABEL__</td>
        <td>
            <select name="products[__I__][discount_type]" class="form-select form-select-sm" data-type>
                <option value="">Theo chương trình</option>
                @foreach($types as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" name="products[__I__][discount_value]" min="0" step="any"
                   class="form-control form-control-sm" placeholder="—" data-value>
        </td>
        <td class="fw-semibold text-accent" data-final>—</td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove>Xóa</button>
        </td>
    </tr>
</template>

<script>
(function () {
    const body = document.getElementById('promoProductsBody');
    const picker = document.getElementById('productPicker');
    const tpl = document.getElementById('promoRowTemplate');
    const emptyBox = document.getElementById('emptyProducts');

    // Mức chung của chương trình — dùng khi dòng không ghi đè.
    const promoType = @json($promotion->type->value);
    const promoValue = @json((float) $promotion->discount_value);

    let nextIndex = {{ $promotion->products->count() }};

    /*
     * ĐỊNH DẠNG TIỀN LẤY TỪ CẤU HÌNH, KHÔNG VIẾT CỨNG.
     *
     * Đây là đoạn xem trước giá sau khuyến mại, chạy ở trình duyệt nên
     * không gọi được Money::format() của PHP. Nhưng viết cứng 'vi-VN' và
     * '₫' ở đây thì đổi đơn vị tiền tệ ở trang Cấu hình xong, cả trang
     * đổi mà riêng ô xem trước này vẫn hiện ký hiệu cũ — và admin sẽ tin
     * vào con số sai đơn vị ngay lúc đang đặt giá.
     *
     * Truyền tham số xuống bằng @json để hai bên luôn cùng một nguồn.
     */
    const tienTe = @json([
        'code' => \App\Services\Shop\Money::code(),
        'symbol' => \App\Services\Shop\Money::symbol(),
        'position' => \App\Services\Shop\Money::position(),
        'decimals' => \App\Services\Shop\Money::decimals(),
    ]);

    const fmt = (n) => {
        const so = new Intl.NumberFormat(tienTe.code === 'USD' ? 'en-US' : 'vi-VN', {
            minimumFractionDigits: tienTe.decimals,
            maximumFractionDigits: tienTe.decimals,
        }).format(n);

        return tienTe.position === 'before' ? tienTe.symbol + so : so + tienTe.symbol;
    };

    /** Tính giá sau KM — phản chiếu logic PricingService để xem trước. */
    function computeFinal(base, type, value) {
        if (base === null || isNaN(base) || value === null || isNaN(value)) return null;

        let final;
        if (type === 'percent')           final = base - (base * value / 100);
        else if (type === 'fixed_amount') final = base - value;
        else if (type === 'fixed_price')  final = value;
        else return null;

        if (final < 0) final = 0;
        return final > base ? base : final;
    }

    function refreshRow(row) {
        const baseAttr = row.querySelector('[data-base-price]')?.dataset.basePrice;
        const base = baseAttr === '' || baseAttr == null ? null : parseFloat(baseAttr);

        const typeSel = row.querySelector('[data-type]').value;
        const valInput = row.querySelector('[data-value]').value;

        const type = typeSel || promoType;
        const value = valInput !== '' ? parseFloat(valInput) : promoValue;

        const final = computeFinal(base, type, value);
        row.querySelector('[data-final]').textContent = final === null ? '—' : fmt(final);
    }

    function refreshAll() {
        body.querySelectorAll('[data-row]').forEach(refreshRow);
        emptyBox.classList.toggle('d-none', body.querySelectorAll('[data-row]').length > 0);
    }

    document.getElementById('addProductBtn').addEventListener('click', function () {
        const opt = picker.selectedOptions[0];
        if (!opt || !opt.value) return;

        const price = opt.dataset.price;
        const html = tpl.innerHTML
            .replaceAll('__I__', nextIndex++)
            .replaceAll('__ID__', opt.value)
            .replaceAll('__NAME__', opt.dataset.name)
            .replaceAll('__CATEGORY__', opt.dataset.category)
            .replaceAll('__PRICE__', price ?? '')
            .replaceAll('__PRICE_LABEL__', price ? fmt(parseFloat(price)) : '—');

        body.insertAdjacentHTML('beforeend', html);
        opt.remove();
        picker.value = '';
        refreshAll();
    });

    // Uỷ quyền sự kiện: dòng được thêm động vẫn hoạt động.
    body.addEventListener('input', e => {
        const row = e.target.closest('[data-row]');
        if (row) refreshRow(row);
    });

    body.addEventListener('change', e => {
        const row = e.target.closest('[data-row]');
        if (row) refreshRow(row);
    });

    body.addEventListener('click', e => {
        if (!e.target.matches('[data-remove]')) return;
        e.target.closest('[data-row]').remove();
        refreshAll();
    });

    refreshAll();
})();
</script>

@endsection
