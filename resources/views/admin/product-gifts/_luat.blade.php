{{--
    LUẬT CỦA MỘT MÓN QUÀ — dùng chung cho khối "Thêm quà" và "Sửa" từng dòng.
    Biến: $pg (ProductGift, có thể mới), $quyCachSanPham, $ma (tiền tố id cho ô).
--}}
<div class="row g-3">
    @if($quyCachSanPham->isNotEmpty())
        <div class="col-md-6">
            <label class="form-label" for="{{ $ma }}-trigger">Áp dụng khi mua</label>
            <select id="{{ $ma }}-trigger" name="trigger_variant_id" class="form-select @error('trigger_variant_id') is-invalid @enderror">
                <option value="">Mọi quy cách</option>
                @foreach($quyCachSanPham as $qc)
                    <option value="{{ $qc->id }}" @selected((string) old('trigger_variant_id', $pg->product_variant_id) === (string) $qc->id)>Quy cách {{ $qc->name }}</option>
                @endforeach
            </select>
            <x-form-error name="trigger_variant_id" />
        </div>
    @endif

    <div class="col-12">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span>Mua mỗi</span>
            <input type="number" name="per_quantity" min="1" max="100" required class="form-control" style="width:5rem"
                   value="{{ old('per_quantity', $pg->per_quantity ?? 1) }}" aria-label="Mua mỗi bao nhiêu sản phẩm">
            <span>sản phẩm → tặng</span>
            <input type="number" name="gift_quantity" min="1" max="100" required class="form-control" style="width:5rem"
                   value="{{ old('gift_quantity', $pg->gift_quantity ?? 1) }}" aria-label="Số quà">
            <span>quà, tối đa</span>
            <input type="number" name="max_quantity" min="1" max="1000" class="form-control" style="width:6rem"
                   placeholder="không giới hạn" value="{{ old('max_quantity', $pg->max_quantity) }}" aria-label="Tối đa quà mỗi đơn">
            <span>quà mỗi đơn</span>
        </div>
        <x-form-error name="max_quantity" />
    </div>

    <div class="col-md-4">
        <label class="form-label" for="{{ $ma }}-thieu">Khi quà không đủ</label>
        <select id="{{ $ma }}-thieu" name="khi_thieu_kho" class="form-select">
            @foreach(\App\Enums\GiftStockRule::cases() as $luat)
                <option value="{{ $luat->value }}" @selected(old('khi_thieu_kho', $pg->khi_thieu_kho?->value ?? 'tang_phan_con') === $luat->value)>{{ $luat->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label" for="{{ $ma }}-tra">Khi khách trả hàng</label>
        <select id="{{ $ma }}-tra" name="tra_hang" class="form-select">
            @foreach(\App\Enums\GiftReturnRule::cases() as $luat)
                <option value="{{ $luat->value }}" @selected(old('tra_hang', $pg->tra_hang?->value ?? 'kem_qua') === $luat->value)>{{ $luat->label() }}</option>
            @endforeach
        </select>
        <div class="form-text">Chỉ là giá trị điền sẵn — phiếu trả vẫn sửa được.</div>
    </div>

    <div class="col-md-4 d-flex align-items-center">
        <label class="d-flex align-items-center gap-2 mb-0">
            <input type="checkbox" class="form-check-input" name="cho_doi_hang" value="1" @checked(old('cho_doi_hang', $pg->cho_doi_hang))>
            <span>Cho đổi quà sang hàng khác</span>
        </label>
    </div>
</div>
