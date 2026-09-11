@extends('layouts.admin')

@section('title', 'Phiếu ' . $phieu->code)

@section('content')

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);

    /*
     * GIÁ TRỊ CHÊNH LỆCH TÍNH THEO GIÁ BÁN HIỆN TẠI, và nói rõ như vậy.
     *
     * Giá vốn của phần hao hụt thường không có (hàng tồn từ trước không có phiếu
     * nhập). Gọi con số này là "thiệt hại" là nói sai một con số kế toán.
     */
    $giaTri = fn ($d) => $d->variant?->price ?? $d->product?->price()->finalPrice;
    $tongGiaTri = '0.00';
    $khongDinhGia = 0;
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Phiếu kiểm kê {{ $phieu->code }}</h1>
        <p class="admin-page-subtitle mb-0">
            <span class="status-pill status-pill--{{ $phieu->status->badge() }}">{{ $phieu->status->label() }}</span>
            &middot; {{ $phieu->status->hint() }}
        </p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        @unless($phieu->isPosted())
            <form method="POST" action="{{ route('admin.stock-counts.post', $phieu) }}"
                  onsubmit="return confirm('Ghi sổ phiếu {{ $phieu->code }}? Tồn kho sẽ được điều chỉnh theo chênh lệch và không hoàn tác được.');">
                @csrf
                <button type="submit" class="btn btn-primary-brand">Ghi sổ &amp; điều chỉnh kho</button>
            </form>

            <form method="POST" action="{{ route('admin.stock-counts.destroy', $phieu) }}"
                  onsubmit="return confirm('Xoá phiếu nháp {{ $phieu->code }}?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-admin">Xoá phiếu</button>
            </form>
        @endunless

        <a data-admin-link href="{{ route('admin.stock-counts.index') }}" class="btn btn-ghost text-nowrap">Về danh sách</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-panel">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Mặt hàng</th>
                            <th class="text-end text-nowrap">Hệ thống lúc lập</th>
                            <th class="text-end text-nowrap">Đếm được</th>
                            <th class="text-end text-nowrap">Chênh lệch</th>
                            <th class="text-end text-nowrap">Theo giá bán</th>
                            <th>Lý do</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($phieu->items as $d)
                            @php
                                $chenh = $d->chenhLech();
                                $gia = $giaTri($d);

                                if ($chenh !== 0) {
                                    if ($gia === null) {
                                        $khongDinhGia++;
                                    } else {
                                        $tongGiaTri = bcadd($tongGiaTri, bcmul((string) $gia, (string) $chenh, 2), 2);
                                    }
                                }
                            @endphp
                            <tr class="{{ $chenh === 0 ? 'text-muted' : '' }}">
                                <td>
                                    {{ $d->product_name }}
                                    @if($d->variant_name)<span class="text-muted">— {{ $d->variant_name }}</span>@endif
                                    @unless($d->product)<span class="text-muted small">(sản phẩm đã xoá)</span>@endunless
                                </td>
                                <td class="text-end">{{ $d->system_quantity }}</td>
                                <td class="text-end">{{ $d->counted_quantity }}</td>
                                <td class="text-end fw-bold {{ $chenh < 0 ? 'text-danger' : '' }}">
                                    {{ $chenh > 0 ? '+' : '' }}{{ $chenh }}
                                </td>
                                <td class="text-end text-nowrap">
                                    {{ $chenh === 0 ? '—' : ($gia === null ? 'giá liên hệ' : $tien(bcmul((string) $gia, (string) $chenh, 2))) }}
                                </td>
                                <td class="small">{{ $d->reason }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-panel p-4">
            <h2 class="h6 fw-bold mb-3">Thông tin phiếu</h2>

            <dl class="admin-detail-list">
                <div><dt>Ngày đếm</dt><dd>{{ $phieu->counted_at->format('d/m/Y') }}</dd></div>
                <div><dt>Người lập</dt><dd>{{ $phieu->actorLabel() }}</dd></div>
                <div><dt>Dòng đếm</dt><dd>{{ $phieu->items->count() }}</dd></div>
                <div><dt>Dòng lệch</dt><dd>{{ $phieu->soDongLech() }}</dd></div>
                <div>
                    <dt>Chênh lệch theo giá bán</dt>
                    <dd class="fw-bold {{ bccomp($tongGiaTri, '0', 2) < 0 ? 'text-danger' : '' }}">{{ $tien($tongGiaTri) }}</dd>
                </div>
                @if($phieu->isPosted())
                    <div><dt>Ghi sổ lúc</dt><dd>{{ \App\Services\Analytics\KhoangThoiGian::diaPhuong($phieu->posted_at)->format('H:i d/m/Y') }}</dd></div>
                @endif
            </dl>

            <p class="admin-page-subtitle small mb-0">
                Tính theo <strong>giá bán</strong> hiện tại, không phải giá vốn — không phải "thiệt hại".
                @if($khongDinhGia > 0)
                    {{ $khongDinhGia }} dòng lệch là hàng giá liên hệ, không cộng vào.
                @endif
                @unless($phieu->isPosted())
                    <br>Ghi sổ sẽ <strong>cộng chênh lệch</strong> vào tồn hiện tại, không gán số đếm — hàng bán ra sau lúc lập phiếu vẫn được giữ đúng.
                @endunless
            </p>

            @if($phieu->note)
                <p class="admin-page-subtitle mt-3 mb-0">{{ $phieu->note }}</p>
            @endif
        </div>
    </div>
</div>

@endsection
