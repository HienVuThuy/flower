@extends('layouts.admin')

@section('title', 'Lịch trả cây theo dịp')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Chăm cây hộ</h1>
    <p class="admin-page-subtitle">
        Đợt mang cây về cho khách theo dịp, ví dụ "Tết 2027": ngày mang cây về và ngày nhận cây lại sau dịp.
        Nhập ngày thật mỗi năm — Tết theo âm lịch nên hệ thống không tự đoán.
        Cùng một nhóm dịp (ví dụ "tet") thì phiếu lặp lại tự nối sang đợt năm sau.
    </p>
</div>

<x-admin.nhom-tab ten="cham-ho" />

<form method="POST" action="{{ route('admin.boarding-windows.store') }}" class="admin-panel p-3 mb-4" data-them-dip>
    @csrf
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label small" for="d-ten">Tên dịp</label>
            <input id="d-ten" name="name" required maxlength="120" class="form-control" placeholder="Tết 2027" value="{{ old('name') }}">
            <x-form-error name="name" />
        </div>
        <div class="col-md-2">
            <label class="form-label small" for="d-nhom">Nhóm dịp</label>
            <input id="d-nhom" name="group_key" maxlength="40" class="form-control" list="nhom-dip" placeholder="tet" value="{{ old('group_key') }}">
            <datalist id="nhom-dip">
                @foreach($nhom as $n)<option value="{{ $n->group_key }}">{{ $n->name }}</option>@endforeach
            </datalist>
        </div>
        <div class="col-md-3">
            <label class="form-label small" for="d-ve">Mang cây về cho khách</label>
            <input id="d-ve" type="date" name="return_on" required class="form-control" value="{{ old('return_on') }}">
            <x-form-error name="return_on" />
        </div>
        <div class="col-md-3">
            <label class="form-label small" for="d-lai">Nhận cây lại sau dịp</label>
            <input id="d-lai" type="date" name="take_back_on" class="form-control" value="{{ old('take_back_on') }}">
            <x-form-error name="take_back_on" />
        </div>
    </div>
    <div class="text-end mt-2"><button type="submit" class="btn btn-primary-brand">Thêm lịch</button></div>
</form>

<div class="admin-panel">
    @forelse($cacDip as $dip)
        <form method="POST" action="{{ route('admin.boarding-windows.update', $dip) }}" class="d-flex flex-wrap gap-2 align-items-center p-3 {{ ! $loop->first ? 'border-top' : '' }}">
            @csrf @method('PUT')
            <input name="name" required maxlength="120" class="form-control form-control-sm" style="max-width: 14rem" value="{{ $dip->name }}" aria-label="Tên dịp">
            <input name="group_key" maxlength="40" class="form-control form-control-sm" style="max-width: 8rem" value="{{ $dip->group_key }}" aria-label="Nhóm dịp">
            <input type="date" name="return_on" required class="form-control form-control-sm" style="max-width: 10rem" value="{{ $dip->return_on->toDateString() }}" aria-label="Mang cây về">
            <input type="date" name="take_back_on" class="form-control form-control-sm" style="max-width: 10rem" value="{{ $dip->take_back_on?->toDateString() }}" aria-label="Nhận lại sau dịp">
            <label class="d-flex align-items-center gap-1 mb-0 small">
                <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($dip->is_active)> Bật
            </label>
            <button type="submit" class="btn btn-sm btn-outline-admin ms-auto">Lưu</button>
        </form>
    @empty
        <p class="text-center py-5 text-muted mb-0">Chưa có lịch dịp nào. Khách chỉ chọn được "Nhận lại đúng dịp" khi có lịch sắp tới.</p>
    @endforelse
</div>

@endsection
