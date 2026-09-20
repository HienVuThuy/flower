@php
    $kieu ??= null;
    $muc ??= null;
    $qua ??= null;
    $soQua ??= null;
    $ketQua ??= '—';
    $tien = fn ($v) => $v === null || $v === '' ? '—' : \App\Services\Shop\Money::format((string) $v);
    $laQua = ($kieu ?: $promotion->type->value) === 'tang_qua';
@endphp

<tr data-row>
    <td>
        <input type="hidden" name="products[{{ $i }}][id]" value="{{ $id }}">
        <div class="fw-semibold">{{ $ten }}</div>
        <small class="text-muted">{{ $danhMuc }}</small>
    </td>

    <td data-base-price="{{ $gia }}">{{ is_numeric($gia) ? $tien($gia) : $gia }}</td>

    <td>
        <select name="products[{{ $i }}][discount_type]" class="form-select form-select-sm" style="min-width: 11rem" data-type aria-label="Ưu đãi của {{ $ten }}">
            <option value="">Theo chương trình</option>
            @foreach($types as $type)
                <option value="{{ $type->value }}" @selected($kieu === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </td>

    <td>
        <div data-o="giam" @if($laQua) hidden @endif>
            <input type="number" name="products[{{ $i }}][discount_value]" min="0" step="any"
                   value="{{ $muc !== null ? rtrim(rtrim((string) $muc, '0'), '.') : '' }}"
                   class="form-control form-control-sm" placeholder="Theo chương trình" data-value
                   aria-label="Mức giảm riêng của {{ $ten }}">
        </div>

        <div class="d-flex gap-1" data-o="qua" @unless($laQua) hidden @endunless>
            <select name="products[{{ $i }}][qua]" class="form-select form-select-sm" style="min-width: 14rem" data-qua aria-label="Quà của {{ $ten }}">
                <option value="">{{ $promotion->giftItem ? 'Quà chung: ' . $promotion->giftItem->name : '— Chọn quà —' }}</option>
                <optgroup label="Vật phẩm quà">
                    @foreach($vatPham as $vat)
                        <option value="vp:{{ $vat->id }}" @selected($qua === 'vp:' . $vat->id)>{{ $vat->name }}{{ $vat->is_active ? '' : ' (đang ngừng)' }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="Lấy sản phẩm đang bán làm quà">
                    @foreach($sanPhamLamQua as $sp)
                        @if($sp->variants->isEmpty())
                            <option value="sp:{{ $sp->id }}">{{ $sp->name }}</option>
                        @else
                            @foreach($sp->variants as $qc)
                                <option value="sp:{{ $sp->id }}:{{ $qc->id }}">{{ $sp->name }} — {{ $qc->name }}</option>
                            @endforeach
                        @endif
                    @endforeach
                </optgroup>
            </select>
            <input type="number" name="products[{{ $i }}][gift_quantity]" min="1" max="100" value="{{ $soQua }}"
                   class="form-control form-control-sm" style="width: 70px" placeholder="{{ $promotion->gift_quantity ?: 1 }}"
                   data-qty aria-label="Số quà mỗi đơn cho {{ $ten }}">
        </div>
        <x-form-error :name="'products.' . $i . '.qua'" />
        <x-form-error :name="'products.' . $i . '.discount_value'" />
    </td>

    <td class="fw-semibold text-accent" data-final>{{ $ketQua }}</td>

    <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove>Xóa</button>
    </td>
</tr>
