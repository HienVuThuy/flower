@extends('layouts.admin')

@section('title', 'Quà kèm: ' . $product->name)

@section('content')

{{-- ============ 1. ĐẦU TRANG ============ --}}
<div class="mb-4">
    <p class="mb-1"><a data-admin-link href="{{ route('admin.product-gifts.index') }}">&larr; Quà tặng kèm sản phẩm</a></p>
    <h1 class="admin-page-title">Quà kèm: {{ $product->name }}</h1>
    <p class="admin-page-subtitle mb-0">
        Khách mua sản phẩm này là tự được tặng các món bên dưới — không cần tự thêm quà vào giỏ. Quà nằm ngay dưới món hàng trong đơn, giá 0đ.
        Mọi luật sửa được bất cứ lúc nào; đơn đã đặt giữ nguyên quà đã nhận.
    </p>
</div>

<form method="POST" action="{{ route('admin.product-gifts.store', $product) }}" class="admin-panel p-4 mb-4" data-them-qua>
    @csrf
    <h2 class="h6 fw-bold mb-3">Thêm quà</h2>

    <p class="fw-semibold mb-2">Quà là gì?</p>
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <label class="d-flex align-items-center gap-2 mb-2">
                <input type="radio" class="form-check-input" name="nguon" value="san_pham" @checked(old('nguon', 'san_pham') === 'san_pham')>
                <span>Sản phẩm có sẵn trong cửa hàng</span>
            </label>

            <label class="form-label small mb-1" for="qg-sp">Bước 1 — Chọn sản phẩm</label>
            <select id="qg-sp" name="gift_product_id" class="form-select mb-2 @error('gift_product_id') is-invalid @enderror" data-qua-chon-san-pham>
                <option value="">Chọn sản phẩm…</option>
                @foreach($sanPham as $sp)
                    <option value="{{ $sp->id }}" @selected((string) old('gift_product_id') === (string) $sp->id)>{{ $sp->name }}</option>
                @endforeach
            </select>
            <x-form-error name="gift_product_id" />

            <label class="form-label small mb-1" for="qg-qc">Bước 2 — Chọn quy cách</label>
            <select id="qg-qc" name="gift_variant_id" class="form-select @error('gift_variant_id') is-invalid @enderror"
                    data-qua-chon-quy-cach @disabled(! old('gift_product_id'))>
                <option value="">Không chọn quy cách</option>
                @foreach($quyCach as $qc)
                    <option value="{{ $qc->id }}" data-san-pham="{{ $qc->product_id }}"
                            @if((string) old('gift_product_id') !== (string) $qc->product_id) hidden disabled @endif
                            @selected((string) old('gift_variant_id') === (string) $qc->id)>{{ $qc->name }}</option>
                @endforeach
            </select>
            <div class="form-text">Chọn sản phẩm trước — chỉ hiện quy cách của sản phẩm đó.</div>
            <x-form-error name="gift_variant_id" />

            @if($vatPhamRieng->isNotEmpty())
                <label class="d-flex align-items-center gap-2 mt-3 mb-2">
                    <input type="radio" class="form-check-input" name="nguon" value="vat_pham_co" @checked(old('nguon') === 'vat_pham_co')>
                    <span>Vật phẩm tặng riêng đã có</span>
                </label>
                <select name="gift_item_id" class="form-select @error('gift_item_id') is-invalid @enderror" aria-label="Vật phẩm quà đã có">
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
                <span>Tạo vật phẩm tặng riêng mới (bao lì xì, sticker, thiệp, dây buộc cây…)</span>
            </label>
            <div class="row g-2">
                <div class="col-12">
                    <input type="text" name="name" maxlength="150" class="form-control @error('name') is-invalid @enderror"
                           placeholder="Tên vật phẩm" value="{{ old('name') }}" aria-label="Tên vật phẩm">
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
                <div class="col-12">
                    <input type="number" name="value" min="0" class="form-control" placeholder="Trị giá tham khảo (đồng, không bắt buộc)"
                           value="{{ old('value') }}" aria-label="Trị giá tham khảo">
                </div>
            </div>
        </div>
    </div>

    <p class="fw-semibold mb-2">Luật tặng</p>
    @include('admin.product-gifts._luat', ['pg' => new \App\Models\ProductGift(), 'ma' => 'them'])

    <div class="text-end mt-3">
        <button type="submit" class="btn btn-primary-brand">Thêm quà</button>
    </div>
</form>

<div class="admin-panel p-4" data-danh-sach-qua>
    <h2 class="h6 fw-bold mb-3">Quà đã cấu hình</h2>

    @forelse($cacQua as $qua)
        <div class="border-top py-3" data-qua-gan="{{ $qua->id }}">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <strong>{{ $qua->giftItem?->name }}</strong>
                    @unless($qua->is_active)<span class="badge text-bg-secondary">đang tắt</span>@endunless
                    <span class="d-block admin-page-subtitle small">
                        {{ $qua->giftItem?->laSanPham() ? 'Sản phẩm trong cửa hàng' : 'Vật phẩm tặng riêng' }}
                        · {{ $qua->variant ? 'Khi mua quy cách ' . $qua->variant->name : 'Mọi quy cách' }}
                        · {{ $qua->moTaLuat() }}
                        · thiếu quà: {{ $qua->khi_thieu_kho->label() }}
                        · trả hàng: {{ $qua->tra_hang->label() }}
                        · {{ $qua->cho_doi_hang ? 'cho đổi' : 'không cho đổi' }}
                        · còn {{ $qua->giftItem?->tonKhoCon() ?? 'không giới hạn' }}
                    </span>
                </div>
                <form method="POST" action="{{ route('admin.product-gifts.destroy', [$product, $qua]) }}" onsubmit="return confirm('Bỏ quà này khỏi sản phẩm?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Bỏ</button>
                </form>
            </div>

            <details class="mt-2">
                <summary class="small">Sửa luật</summary>
                <form method="POST" action="{{ route('admin.product-gifts.update', [$product, $qua]) }}" class="mt-3">
                    @csrf
                    @method('PUT')

                    @include('admin.product-gifts._luat', ['pg' => $qua, 'ma' => 'sua-' . $qua->id])

                    <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                        @if($qua->giftItem && ! $qua->giftItem->laSanPham())
                            <label class="d-flex align-items-center gap-2 mb-0">
                                <span>Số lượng quà đang có</span>
                                <input type="number" name="stock_quantity" min="0" class="form-control form-control-sm" style="width:6rem"
                                       value="{{ $qua->giftItem->stock_quantity }}">
                            </label>
                        @endif
                        <label class="d-flex align-items-center gap-2 mb-0">
                            <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($qua->is_active)>
                            <span>Đang tặng</span>
                        </label>
                        <button type="submit" class="btn btn-sm btn-primary-brand ms-auto">Lưu</button>
                    </div>
                </form>
            </details>
        </div>
    @empty
        <p class="text-muted mb-0" data-chua-co-qua>Chưa có quà tặng kèm. Hãy thêm quà ở phía trên.</p>
    @endforelse
</div>

@endsection
