@extends('layouts.admin')

@php
    /* MỘT biểu mẫu cho cả ghi mới và sửa: hai bản thì sớm muộn một bản
       thiếu ô, và sửa lô là lặng lẽ xoá mất giá trị ô đó. */
    $lo = $lo ?? null;
    $tieuDe = $lo ? 'Sửa lô ' . $lo->code : 'Ghi lô hoa';
@endphp

@section('title', $tieuDe)

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">{{ $tieuDe }}</h1>
    <p class="admin-page-subtitle">
        Một lần lấy hàng là một lô. Ghi số lượng và tiền theo <strong>đơn vị lúc mua</strong>
        (bó, cân, thùng…), không quy về cành.
    </p>
</div>

@if($loaiHoa->isEmpty())
    <div class="admin-panel p-4">
        <p class="analytics-empty mb-3">
            Chưa có loại hoa nào. Phải khai loại hoa trước thì mới ghi lô được —
            loại hoa là thứ cho phép so giá giữa các lần mua và giữa các nơi.
        </p>
        <a data-admin-link href="{{ route('admin.flower-kinds.index') }}" class="btn btn-primary-brand">
            Khai loại hoa
        </a>
    </div>
@else
<form method="POST" action="{{ $lo ? route('admin.flower-lots.update', $lo) : route('admin.flower-lots.store') }}">
    @csrf
    @if($lo)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="admin-panel p-4">

                <div class="mb-3">
                    <label class="form-label" for="flower_kind_id">Loại hoa <span aria-hidden="true">*</span></label>
                    <select id="flower_kind_id" name="flower_kind_id" required
                            class="form-select @error('flower_kind_id') is-invalid @enderror">
                        <option value="">— chọn —</option>
                        @foreach($loaiHoa as $lh)
                            <option value="{{ $lh->id }}" @selected(old('flower_kind_id', $lo?->flower_kind_id) == $lh->id)
                                    data-don-vi="{{ $lh->default_unit->value }}">
                                {{ $lh->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="flower_kind_id" />
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-sm-5">
                        <label class="form-label" for="quantity">Số lượng <span aria-hidden="true">*</span></label>
                        <input type="number" id="quantity" name="quantity" required
                               class="form-control @error('quantity') is-invalid @enderror"
                               min="0.01" step="0.01" value="{{ old('quantity', $lo ? rtrim(rtrim((string) $lo->quantity, '0'), '.') : null) }}">
                        <x-form-error name="quantity" />
                        {{-- Có phần thập phân: mua theo cân thì 3,5kg là chuyện thường. --}}
                        <div class="form-text">Được ghi số lẻ, ví dụ 3,5 kg.</div>
                    </div>

                    <div class="col-sm-3">
                        <label class="form-label" for="unit">Đơn vị</label>
                        <select id="unit" name="unit" class="form-select @error('unit') is-invalid @enderror">
                            @foreach($donVi as $dv)
                                <option value="{{ $dv->value }}" @selected(old('unit', $lo?->unit->value ?? 'bo') === $dv->value)>
                                    {{ $dv->label() }}
                                </option>
                            @endforeach
                        </select>
                        <x-form-error name="unit" />
                    </div>

                    <div class="col-sm-4">
                        <label class="form-label" for="total_cost">Tổng tiền <span aria-hidden="true">*</span></label>
                        <input type="number" id="total_cost" name="total_cost" required
                               class="form-control @error('total_cost') is-invalid @enderror"
                               min="1" step="1" value="{{ old('total_cost', $lo ? (int) $lo->total_cost : null) }}">
                        <x-form-error name="total_cost" />
                        <div class="form-text">Tiền cả lô, không phải giá mỗi đơn vị.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="note">Ghi chú</label>
                    <textarea id="note" name="note" rows="3" maxlength="1000"
                              class="form-control @error('note') is-invalid @enderror"
                              placeholder="Hoa hơi non, để được lâu. Giá đang lên vì gần lễ.">{{ old('note', $lo?->note) }}</textarea>
                    <x-form-error name="note" />
                </div>

            </div>
        </div>

        <div class="col-lg-5">
            <div class="admin-panel p-4">

                <div class="mb-3">
                    <label class="form-label" for="supplier_id">Lấy của ai</label>
                    <select id="supplier_id" name="supplier_id"
                            class="form-select @error('supplier_id') is-invalid @enderror">
                        <option value="">— chưa ghi / mua lẻ —</option>
                        @foreach($nhaCungCap as $ncc)
                            <option value="{{ $ncc->id }}" @selected(old('supplier_id', $lo?->supplier_id) == $ncc->id)>
                                {{ $ncc->name }} ({{ $ncc->kind->label() }})
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="supplier_id" />
                    <div class="form-text">
                        Chưa có thì
                        <a data-admin-link href="{{ route('admin.suppliers.create') }}">thêm nhà cung cấp</a>.
                        Ghi đủ mới so được giá giữa các nơi.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="purchased_at">Ngày lấy hàng</label>
                    <input type="date" id="purchased_at" name="purchased_at" required
                           class="form-control @error('purchased_at') is-invalid @enderror"
                           value="{{ old('purchased_at', $lo ? $lo->purchased_at->toDateString() : \App\Services\Time\Gio::choONgay(now())) }}"
                           max="{{ \App\Services\Time\Gio::choONgay(now()) }}">
                    <x-form-error name="purchased_at" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="quality">Chất lượng khi nhận</label>
                    <select id="quality" name="quality" class="form-select @error('quality') is-invalid @enderror">
                        <option value="">— chưa đánh giá —</option>
                        @foreach(\App\Enums\FlowerQuality::cases() as $cl)
                            <option value="{{ $cl->value }}" @selected(old('quality', $lo?->quality?->value) === $cl->value)>
                                {{ $cl->label() }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="quality" />
                    {{-- Câu hỏi thật không phải "ở đâu rẻ nhất" mà là "ở đâu đáng
                         tiền nhất" — và cái đắt vì hoa dập không nằm trên hoá đơn. --}}
                    <div class="form-text">Để so cùng với giá: rẻ hơn mà hay dập thì không rẻ hơn.</div>
                </div>

                <button type="submit" class="btn btn-primary-brand w-100">{{ $lo ? 'Lưu thay đổi' : 'Ghi lô' }}</button>

                <p class="admin-page-subtitle small mt-2 mb-0">
                    @if($lo)
                        Chỉ sửa được khi lô <strong>còn mở</strong>. Mọi thay đổi được ghi vào
                        nhật ký kèm giá trị cũ.
                    @else
                        Ghi xong là lô <strong>đang dùng</strong>. Dùng hết thì đóng lô ở
                        trang danh sách — chưa đóng thì tiền chưa vào giá vốn.
                    @endif
                </p>
            </div>
        </div>
    </div>
</form>
@endif

@endsection
