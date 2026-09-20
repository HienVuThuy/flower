@extends('layouts.admin')

@section('title', $nhaCungCap->exists ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">
        {{ $nhaCungCap->exists ? 'Sửa: ' . $nhaCungCap->name : 'Thêm nhà cung cấp' }}
    </h1>
</div>

<form method="POST"
      action="{{ $nhaCungCap->exists
          ? route('admin.suppliers.update', $nhaCungCap)
          : route('admin.suppliers.store') }}">
    @csrf
    @if($nhaCungCap->exists)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="admin-panel p-4">

                <div class="mb-3">
                    <label class="form-label" for="ncc-name">Tên <span aria-hidden="true">*</span></label>
                    <input type="text" id="ncc-name" name="name" maxlength="160" required
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $nhaCungCap->name) }}"
                           placeholder="Vựa hoa Quảng Bá…">
                    <x-form-error name="name" />
                    <div class="form-text">
                        Mỗi nơi một dòng, đừng tạo hai dòng cho cùng một người —
                        hai dòng thì không so giá với nhau được.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="ncc-kind">Loại nguồn hàng</label>
                    <select id="ncc-kind" name="kind" class="form-select @error('kind') is-invalid @enderror">
                        @foreach($cacLoai as $loai)
                            <option value="{{ $loai->value }}"
                                    @selected(old('kind', $nhaCungCap->kind?->value ?? 'khac') === $loai->value)>
                                {{ $loai->label() }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="kind" />

                    <ul class="form-text mb-0 ps-3">
                        @foreach($cacLoai as $loai)
                            <li><strong>{{ $loai->label() }}</strong> — {{ $loai->hint() }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="ncc-note">Ghi chú</label>
                    <textarea id="ncc-note" name="note" rows="3" maxlength="2000"
                              class="form-control @error('note') is-invalid @enderror"
                              placeholder="Hoa đẹp nhưng hay thiếu hàng cuối tuần. Phải gọi trước 2 hôm.">{{ old('note', $nhaCungCap->note) }}</textarea>
                    <x-form-error name="note" />
                    <div class="form-text">
                        Chỗ để những thứ quyết định việc chọn ai mà không lên thành cột được.
                    </div>
                </div>

            </div>
        </div>

        <div class="col-lg-5">
            <div class="admin-panel p-4">

                <h2 class="h6 fw-bold mb-3">Liên hệ</h2>

                <div class="mb-3">
                    <label class="form-label" for="ncc-phone">Điện thoại</label>
                    <input type="text" id="ncc-phone" name="phone" maxlength="30"
                           class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $nhaCungCap->phone) }}">
                    <x-form-error name="phone" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="ncc-email">Email</label>
                    <input type="email" id="ncc-email" name="email" maxlength="160"
                           class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $nhaCungCap->email) }}">
                    <x-form-error name="email" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="ncc-address">Địa chỉ</label>
                    <input type="text" id="ncc-address" name="address" maxlength="255"
                           class="form-control @error('address') is-invalid @enderror"
                           value="{{ old('address', $nhaCungCap->address) }}">
                    <x-form-error name="address" />
                </div>

                <label class="d-flex align-items-center gap-2 mb-3">
                    <input type="checkbox" class="form-check-input" name="is_active" value="1"
                           @checked(old('is_active', $nhaCungCap->exists ? $nhaCungCap->is_active : true))>
                    <span>Còn đang lấy hàng</span>
                </label>

                <p class="admin-page-subtitle small">
                    Ngừng làm ăn thì bỏ tích ở trên, đừng xoá: phiếu nhập cũ trỏ tới
                    đây, xoá đi là mất dấu vết những lần đã mua.
                </p>

                <button type="submit" class="btn btn-primary-brand w-100">Lưu</button>
            </div>
        </div>
    </div>
</form>

@endsection
