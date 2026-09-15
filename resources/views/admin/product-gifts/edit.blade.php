@extends('layouts.admin')

@section('title', 'Quà kèm: ' . $product->name)

@section('content')

<div class="mb-4">
    <p class="mb-1"><a data-admin-link href="{{ route('admin.product-gifts.index') }}">&larr; Quà tặng kèm sản phẩm</a></p>
    <h1 class="admin-page-title">Quà kèm: {{ $product->name }}</h1>
    <p class="admin-page-subtitle mb-0">
        Khách mua sản phẩm này là được tặng các món dưới đây. Quà nằm ngay dưới món hàng trong đơn, giá 0đ.
    </p>
</div>

{{-- ============ QUÀ ĐANG GẮN ============ --}}
<div class="admin-panel mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Quà</th>
                    <th scope="col">Mua mỗi</th>
                    <th scope="col">Tặng</th>
                    <th scope="col">Còn tặng được</th>
                    <th scope="col">Đang tặng</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($cacQua as $qua)
                    <tr data-qua-gan="{{ $qua->id }}">
                        <td>
                            {{ $qua->giftItem?->name }}
                            <span class="d-block admin-page-subtitle small">
                                {{ $qua->giftItem?->laSanPham() ? 'Sản phẩm trong cửa hàng' : 'Vật phẩm tặng riêng' }}
                            </span>
                        </td>
                        <td colspan="4">
                            <form method="POST" action="{{ route('admin.product-gifts.update', [$product, $qua]) }}" class="d-flex flex-wrap align-items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="number" name="per_quantity" min="1" max="100" required class="form-control form-control-sm" style="width:5rem"
                                       value="{{ $qua->per_quantity }}" aria-label="Mua mỗi bao nhiêu sản phẩm">
                                <span class="small">sản phẩm → tặng</span>
                                <input type="number" name="gift_quantity" min="1" max="100" required class="form-control form-control-sm" style="width:5rem"
                                       value="{{ $qua->gift_quantity }}" aria-label="Số quà">
                                @if($qua->giftItem && ! $qua->giftItem->laSanPham())
                                    <span class="small">· đang có</span>
                                    <input type="number" name="stock_quantity" min="0" class="form-control form-control-sm" style="width:6rem"
                                           value="{{ $qua->giftItem->stock_quantity }}" aria-label="Số lượng quà đang có">
                                @else
                                    <span class="small admin-page-subtitle">· còn {{ $qua->giftItem?->tonKhoCon() ?? 'không giới hạn' }}</span>
                                @endif
                                <label class="d-flex align-items-center gap-1 small ms-2">
                                    <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($qua->is_active)> Đang tặng
                                </label>
                                <button type="submit" class="btn btn-sm btn-outline-admin">Lưu</button>
                            </form>
                        </td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.product-gifts.destroy', [$product, $qua]) }}" onsubmit="return confirm('Bỏ quà này khỏi sản phẩm?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Bỏ</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">Sản phẩm chưa có quà kèm.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ============ THÊM QUÀ ============ --}}
<form method="POST" action="{{ route('admin.product-gifts.store', $product) }}" class="admin-panel p-4" data-them-qua>
    @csrf
    <h2 class="h6 fw-bold mb-3">Thêm quà</h2>

    <div class="row g-3">
        <div class="col-lg-6">
            <label class="d-flex align-items-center gap-2 mb-2">
                <input type="radio" class="form-check-input" name="nguon" value="san_pham" @checked(old('nguon', 'san_pham') === 'san_pham')>
                <span class="fw-semibold">Sản phẩm có sẵn trong cửa hàng</span>
            </label>
            <select name="gift_product_id" class="form-select mb-2 @error('gift_product_id') is-invalid @enderror" aria-label="Sản phẩm dùng làm quà">
                <option value="">Chọn sản phẩm…</option>
                @foreach($sanPham as $sp)
                    <option value="{{ $sp->id }}" @selected((string) old('gift_product_id') === (string) $sp->id)>{{ $sp->name }}</option>
                @endforeach
            </select>
            <x-form-error name="gift_product_id" />
            <select name="gift_variant_id" class="form-select mb-3 @error('gift_variant_id') is-invalid @enderror" aria-label="Quy cách">
                <option value="">Không chọn quy cách</option>
                @foreach($quyCach as $qc)
                    <option value="{{ $qc->id }}" @selected((string) old('gift_variant_id') === (string) $qc->id)>{{ $sanPham->firstWhere('id', $qc->product_id)?->name }} — {{ $qc->name }}</option>
                @endforeach
            </select>
            <x-form-error name="gift_variant_id" />

            @if($vatPhamRieng->isNotEmpty())
                <label class="d-flex align-items-center gap-2 mb-2">
                    <input type="radio" class="form-check-input" name="nguon" value="vat_pham_co" @checked(old('nguon') === 'vat_pham_co')>
                    <span class="fw-semibold">Vật phẩm tặng riêng đã có</span>
                </label>
                <select name="gift_item_id" class="form-select mb-3 @error('gift_item_id') is-invalid @enderror" aria-label="Vật phẩm quà đã có">
                    <option value="">Chọn vật phẩm…</option>
                    @foreach($vatPhamRieng as $vat)
                        <option value="{{ $vat->id }}" @selected((string) old('gift_item_id') === (string) $vat->id)>{{ $vat->name }} (còn {{ $vat->stock_quantity }})</option>
                    @endforeach
                </select>
                <x-form-error name="gift_item_id" />
            @endif
        </div>

        <div class="col-lg-6">
            <label class="d-flex align-items-center gap-2 mb-2">
                <input type="radio" class="form-check-input" name="nguon" value="vat_pham_moi" @checked(old('nguon') === 'vat_pham_moi')>
                <span class="fw-semibold">Tạo vật phẩm tặng riêng mới</span>
            </label>
            <div class="row g-2 mb-3">
                <div class="col-12">
                    <input type="text" name="name" maxlength="150" class="form-control @error('name') is-invalid @enderror"
                           placeholder="Túi phân bón nhỏ, thẻ hướng dẫn chăm cây…" value="{{ old('name') }}" aria-label="Tên vật phẩm">
                    <x-form-error name="name" />
                </div>
                <div class="col-6">
                    <select name="kind" class="form-select" aria-label="Loại quà">
                        @foreach(\App\Enums\GiftKind::cases() as $loai)
                            <option value="{{ $loai->value }}" @selected(old('kind', 'qua_tang') === $loai->value)>{{ $loai->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6">
                    <input type="number" name="stock_quantity" min="0" class="form-control @error('stock_quantity') is-invalid @enderror"
                           placeholder="Số lượng đang có" value="{{ old('stock_quantity') }}" aria-label="Số lượng đang có">
                    <x-form-error name="stock_quantity" />
                </div>
            </div>

            <label class="form-label" for="pg-value">Trị giá tham khảo (đồng, không bắt buộc)</label>
            <input type="number" id="pg-value" name="value" min="0" class="form-control mb-3" value="{{ old('value') }}">
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <span>Mua mỗi</span>
        <input type="number" name="per_quantity" min="1" max="100" required class="form-control" style="width:5rem" value="{{ old('per_quantity', 1) }}" aria-label="Mua mỗi bao nhiêu sản phẩm">
        <span>sản phẩm thì tặng</span>
        <input type="number" name="gift_quantity" min="1" max="100" required class="form-control" style="width:5rem" value="{{ old('gift_quantity', 1) }}" aria-label="Số quà">
        <span>quà</span>
        <button type="submit" class="btn btn-primary-brand ms-auto">Thêm quà</button>
    </div>
</form>

@endsection
