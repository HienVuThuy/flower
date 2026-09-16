@extends('layouts.admin')

@section('title', 'Trả hàng nhà cung cấp')

@section('content')

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
@endphp

<div class="mb-4">
    <h1 class="admin-page-title">Trả hàng cho nhà cung cấp</h1>
    <p class="admin-page-subtitle">
        Hàng hỏng, giao sai, không đạt chất lượng. Chọn lô hàng đã nhận rồi ghi trả bao nhiêu.
    </p>
</div>

<x-admin.nhom-tab ten="nhap-kho" />

<div class="admin-panel p-4 mb-3">
    <h2 class="h6 fw-bold mb-2">Cách xử lý tiền quyết định giá vốn có giảm hay không</h2>
    <ul class="mb-0 ps-3 admin-page-subtitle">
        @foreach($cachXuLy as $c)
            <li><strong>{{ $c->label() }}</strong> — {{ $c->hint() }}</li>
        @endforeach
    </ul>
</div>

<h2 class="admin-section-title">Hàng đếm được — từ phiếu nhập đã ghi sổ</h2>

@if($phieuNhap->isEmpty())
    <div class="admin-panel p-4 mb-4">
        <p class="analytics-empty mb-0">
            Chưa có phiếu nhập nào đã ghi sổ. Phiếu còn nháp thì hàng chưa vào kho nên chưa có gì để trả —
            sửa hoặc xoá phiếu nháp đó thay vì lập phiếu trả.
        </p>
    </div>
@else
    <div class="admin-panel p-4 mb-4">
        @foreach($phieuNhap as $p)
            <details class="mb-2">
                <summary>
                    <strong>{{ $p->code }}</strong>
                    — <x-site.time :at="$p->received_at" format="d/m/Y" />
                    @if($p->tenNhaCungCap())
                        · {{ $p->tenNhaCungCap() }}
                    @endif
                    · {{ $p->items->count() }} dòng
                </summary>

                <form method="POST" action="{{ route('admin.supplier-returns.goods', $p) }}" class="mt-3">
                    @csrf

                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Mặt hàng</th>
                                    <th scope="col">Đã nhập</th>
                                    <th scope="col">Đơn giá</th>
                                    <th scope="col">Còn trả được</th>
                                    <th scope="col">Trả bao nhiêu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($p->items as $d)
                                    @php
                                        $daTraDong = $daTraTheoDong($d->id);
                                        $con = (int) $d->quantity - $daTraDong;
                                    @endphp
                                    <tr>
                                        <td>
                                            {{ $d->product_name }}
                                            @if($d->variant_name)
                                                <span class="admin-page-subtitle">— {{ $d->variant_name }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $d->quantity }}</td>
                                        <td>
                                            @if($d->unit_cost !== null)
                                                {{ $tien($d->unit_cost) }}
                                            @else
                                                <span class="admin-page-subtitle">chưa điền giá</span>
                                            @endif
                                        </td>
                                        <td>{{ max(0, $con) }}</td>
                                        <td>
                                            <label class="visually-hidden" for="tra-{{ $d->id }}">
                                                Số lượng trả của {{ $d->product_name }}
                                            </label>
                                            <input type="number" id="tra-{{ $d->id }}"
                                                   name="items[{{ $d->id }}][quantity]"
                                                   class="form-control form-control-sm" style="max-width:7rem"
                                                   min="0" max="{{ max(0, $con) }}" value="0">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap align-items-end gap-2">
                        <div>
                            <label class="form-label small mb-1" for="ld-{{ $p->id }}">Lý do</label>
                            <select id="ld-{{ $p->id }}" name="reason" class="form-select form-select-sm" required>
                                @foreach($lyDo as $l)
                                    <option value="{{ $l->value }}">{{ $l->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label small mb-1" for="cx-{{ $p->id }}">Xử lý tiền</label>
                            <select id="cx-{{ $p->id }}" name="settlement" class="form-select form-select-sm" required>
                                @foreach($cachXuLy as $c)
                                    <option value="{{ $c->value }}">{{ $c->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label small mb-1" for="st-{{ $p->id }}">Tiền lấy lại được</label>
                            <input type="number" id="st-{{ $p->id }}" name="settlement_amount"
                                   class="form-control form-control-sm" style="max-width:10rem"
                                   min="0" step="1" placeholder="theo đơn giá">
                        </div>

                        <div>
                            <label class="form-label small mb-1" for="nd-{{ $p->id }}">Ngày trả</label>
                            <input type="date" id="nd-{{ $p->id }}" name="returned_at" required
                                   class="form-control form-control-sm"
                                   value="{{ \App\Services\Time\Gio::choONgay(now()) }}"
                                   max="{{ \App\Services\Time\Gio::choONgay(now()) }}">
                        </div>

                        <button type="submit" class="btn btn-sm btn-primary-brand">Lập phiếu trả</button>
                    </div>

                    <p class="admin-page-subtitle small mt-2 mb-0">
                        Để trống ô tiền thì hệ thống tính theo đúng đơn giá đã mua.
                        Điền tay khi vựa trả ít hơn.
                    </p>
                </form>
            </details>
        @endforeach
    </div>
@endif

<h2 class="admin-section-title">Hoa tươi — từ lô đã lấy</h2>

@if($loHoa->isEmpty())
    <div class="admin-panel p-4 mb-4">
        <p class="analytics-empty mb-0">Không có lô hoa nào chưa ghi trả hàng.</p>
    </div>
@else
    <div class="admin-panel p-4 mb-4">
        @foreach($loHoa as $l)
            <details class="mb-2">
                <summary>
                    <strong>{{ $l->code }}</strong>
                    — {{ $l->kind?->name }},
                    {{ rtrim(rtrim(number_format((float) $l->quantity, 2, ',', '.'), '0'), ',') }}
                    {{ $l->unit->label() }}
                    · {{ $tien($l->total_cost) }}
                    @if($l->supplier_name)
                        · {{ $l->supplier_name }}
                    @endif
                </summary>

                <form method="POST" action="{{ route('admin.supplier-returns.flower', $l) }}"
                      class="d-flex flex-wrap align-items-end gap-2 mt-3">
                    @csrf

                    <div>
                        <label class="form-label small mb-1" for="lq-{{ $l->id }}">
                            Trả bao nhiêu ({{ $l->unit->label() }})
                        </label>
                        <input type="number" id="lq-{{ $l->id }}" name="quantity" required
                               class="form-control form-control-sm" style="max-width:8rem"
                               min="0.01" step="0.01" max="{{ $l->quantity }}">
                    </div>

                    <div>
                        <label class="form-label small mb-1" for="lld-{{ $l->id }}">Lý do</label>
                        <select id="lld-{{ $l->id }}" name="reason" class="form-select form-select-sm" required>
                            @foreach($lyDo as $ld)
                                <option value="{{ $ld->value }}">{{ $ld->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label small mb-1" for="lcx-{{ $l->id }}">Xử lý tiền</label>
                        <select id="lcx-{{ $l->id }}" name="settlement" class="form-select form-select-sm" required>
                            @foreach($cachXuLy as $c)
                                <option value="{{ $c->value }}">{{ $c->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary-brand">Ghi trả hàng</button>
                </form>

                <p class="admin-page-subtitle small mt-2 mb-0">
                    Tiền lấy lại tính theo đúng đơn giá của lô
                    ({{ $l->donGia() ? $tien($l->donGia()) . '/' . $l->unit->label() : 'chưa tính được' }}).
                </p>
            </details>
        @endforeach
    </div>
@endif

@if($daTra->isNotEmpty() || $loDaTra->isNotEmpty())
    <h2 class="admin-section-title">Đã trả</h2>

    <div class="admin-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Chứng từ</th>
                        <th scope="col">Nguồn</th>
                        <th scope="col">Lý do</th>
                        <th scope="col">Xử lý tiền</th>
                        <th scope="col">Lấy lại được</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($daTra as $p)
                        <tr>
                            <td>
                                <a data-admin-link href="{{ route('admin.stock-receipts.show', $p) }}">{{ $p->code }}</a>
                                <span class="d-block admin-page-subtitle small">
                                    {{ $p->isPosted() ? 'đã ghi sổ' : 'còn nháp' }}
                                    · <x-site.time :at="$p->received_at" format="d/m/Y" />
                                </span>
                            </td>
                            <td>{{ $p->phieuGoc?->code ?? '—' }}</td>
                            <td>{{ $p->return_reason?->label() ?? '—' }}</td>
                            <td>{{ $p->settlement?->label() ?? '—' }}</td>
                            <td>
                                @if($p->settlement_amount !== null)
                                    {{ $tien($p->settlement_amount) }}
                                @else
                                    <span class="admin-page-subtitle">không có tiền</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    @foreach($loDaTra as $l)
                        <tr>
                            <td>
                                {{ $l->code }}
                                <span class="d-block admin-page-subtitle small">
                                    lô hoa · <x-site.time :at="$l->tra_lai_at" format="d/m/Y" />
                                </span>
                            </td>
                            <td>
                                {{ $l->kind?->name }} —
                                {{ rtrim(rtrim(number_format((float) $l->tra_lai_qty, 2, ',', '.'), '0'), ',') }}
                                {{ $l->unit->label() }}
                            </td>
                            <td>{{ $l->tra_lai_ly_do?->label() ?? '—' }}</td>
                            <td>{{ $l->tra_lai_settlement?->label() ?? '—' }}</td>
                            <td>
                                @if($l->tra_lai_tien !== null)
                                    {{ $tien($l->tra_lai_tien) }}
                                @else
                                    <span class="admin-page-subtitle">không có tiền</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection
