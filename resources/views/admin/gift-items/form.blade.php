@extends('layouts.admin')

@section('title', $vat->exists ? 'Sửa vật phẩm quà' : 'Thêm vật phẩm quà')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">{{ $vat->exists ? 'Sửa: ' . $vat->name : 'Thêm vật phẩm quà' }}</h1>
</div>

<form method="POST" action="{{ $vat->exists ? route('admin.gift-items.update', $vat) : route('admin.gift-items.store') }}">
    @csrf
    @if($vat->exists)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="admin-panel p-4">
                <div class="mb-3">
                    <label class="form-label" for="qv-name">Tên quà <span aria-hidden="true">*</span></label>
                    <input type="text" id="qv-name" name="name" maxlength="150" required
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $vat->name) }}" placeholder="Túi vải Angevil, chậu sen đá mini…">
                    <x-form-error name="name" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="qv-kind">Loại quà</label>
                    <select id="qv-kind" name="kind" class="form-select">
                        @foreach(\App\Enums\GiftKind::cases() as $loai)
                            <option value="{{ $loai->value }}" @selected(old('kind', $vat->kind?->value) === $loai->value)>{{ $loai->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="qv-product">Là sản phẩm đang có</label>
                    <select id="qv-product" name="product_id" class="form-select @error('product_id') is-invalid @enderror">
                        <option value="">— Vật phẩm tặng riêng —</option>
                        @foreach($sanPham as $sp)
                            <option value="{{ $sp->id }}" @selected((string) old('product_id', $vat->product_id) === (string) $sp->id)>{{ $sp->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Chọn sản phẩm thì quà trừ vào tồn kho của sản phẩm đó.</div>
                    <x-form-error name="product_id" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="qv-variant">Quy cách (nếu sản phẩm có)</label>
                    <select id="qv-variant" name="product_variant_id" class="form-select @error('product_variant_id') is-invalid @enderror">
                        <option value="">— Không —</option>
                        @foreach($quyCach as $qc)
                            <option value="{{ $qc->id }}" @selected((string) old('product_variant_id', $vat->product_variant_id) === (string) $qc->id)>
                                {{ $sanPham->firstWhere('id', $qc->product_id)?->name }} — {{ $qc->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="product_variant_id" />
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="admin-panel p-4">
                <div class="mb-3">
                    <label class="form-label" for="qv-stock">Số lượng đang có (vật phẩm tặng riêng)</label>
                    <input type="number" id="qv-stock" name="stock_quantity" min="0" step="1"
                           class="form-control @error('stock_quantity') is-invalid @enderror"
                           value="{{ old('stock_quantity', $vat->stock_quantity) }}">
                    <div class="form-text">Bỏ qua nếu quà là sản phẩm đang có.</div>
                    <x-form-error name="stock_quantity" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="qv-value">Trị giá tham khảo (đồng)</label>
                    <input type="number" id="qv-value" name="value" min="0" step="1"
                           class="form-control @error('value') is-invalid @enderror"
                           value="{{ old('value', $vat->value !== null ? (int) $vat->value : '') }}">
                    <div class="form-text">Hiện cho khách biết món quà đáng bao nhiêu. Để trống thì không hiện.</div>
                    <x-form-error name="value" />
                </div>

                <label class="d-flex align-items-center gap-2 mb-3">
                    <input type="checkbox" class="form-check-input" name="is_active" value="1"
                           @checked(old('is_active', $vat->exists ? $vat->is_active : true))>
                    <span>Đang dùng làm quà</span>
                </label>

                <button type="submit" class="btn btn-primary-brand w-100">Lưu</button>
            </div>
        </div>
    </div>
</form>

@endsection
