@extends('layouts.admin')

@section('title', 'Bảng giá chăm cây hộ')

@section('content')

@php use App\Enums\CareDifficulty; @endphp

<div class="mb-4">
    <h1 class="admin-page-title">Chăm cây hộ</h1>
    <p class="admin-page-subtitle">
        Đây là GIÁ THAM KHẢO theo loại cây, độ khó, cỡ cây — khách thấy "từ … /tháng". Mỗi cây mỗi khác nên
        cửa hàng chốt giá riêng khi xác nhận phiếu. Giá năm để trống thì bằng 12 tháng.
        Chưa có dòng giá nào đang bật thì mọi lối vào dịch vụ phía khách đều ẩn.
        Sửa giá không đổi các phiếu đã gửi — phiếu giữ giá lúc gửi.
    </p>
</div>

<x-admin.nhom-tab ten="cham-ho" />

@foreach(collect([null])->concat($cacGia) as $gia)
    <form method="POST" action="{{ $gia ? route('admin.boarding-rates.update', $gia) : route('admin.boarding-rates.store') }}"
          class="admin-panel p-3 mb-3 {{ $gia ? '' : 'border-success' }}" data-dong-gia="{{ $gia?->id ?? 'moi' }}">
        @csrf
        @if($gia) @method('PUT') @endif

        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>{{ $gia ? $gia->name : 'Thêm dòng giá mới' }}</strong>
            @if($gia)<span class="admin-page-subtitle small">{{ $gia->bookings_count }} phiếu đã dùng</span>@endif
        </div>

        <div class="row g-2">
            <div class="col-md-4">
                <input type="text" name="name" required maxlength="120" class="form-control" placeholder="Ví dụ: Đào thế chậu dưới 1,5m"
                       value="{{ $gia?->name }}" aria-label="Tên">
            </div>
            <div class="col-md-2">
                <select name="care_difficulty" class="form-select" aria-label="Độ khó">
                    <option value="">Mọi độ khó</option>
                    @foreach(CareDifficulty::cases() as $dk)
                        <option value="{{ $dk->value }}" @selected($gia?->care_difficulty === $dk)>{{ $dk->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" name="monthly_price" required min="1000" step="1000" class="form-control" placeholder="Giá từ / tháng"
                       value="{{ $gia ? (int) $gia->monthly_price : '' }}" aria-label="Giá mỗi tháng">
            </div>
            <div class="col-md-2">
                <input type="number" name="yearly_price" min="1000" step="1000" class="form-control" placeholder="Giá / năm"
                       value="{{ $gia?->yearly_price !== null ? (int) $gia->yearly_price : '' }}" aria-label="Giá mỗi năm">
            </div>
            <div class="col-md-2">
                <input type="number" name="sort_order" min="0" class="form-control" placeholder="Thứ tự" value="{{ $gia?->sort_order ?? 0 }}" aria-label="Thứ tự">
            </div>
            <div class="col-12">
                <input type="text" name="description" maxlength="500" class="form-control" placeholder="Gồm những gì: tưới, bón, tỉa, xử lý sâu…"
                       value="{{ $gia?->description }}" aria-label="Mô tả">
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
            <label class="d-flex align-items-center gap-2 mb-0">
                <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($gia?->is_active ?? true)>
                <span>Đang nhận</span>
            </label>
            <button type="submit" class="btn btn-sm btn-primary-brand ms-auto">{{ $gia ? 'Lưu' : 'Thêm' }}</button>
        </div>
    </form>

    @if($gia)
        <form method="POST" action="{{ route('admin.boarding-rates.destroy', $gia) }}" class="text-end mb-4" onsubmit="return confirm('Xoá dòng giá này?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-link btn-sm text-danger p-0">{{ $gia->bookings_count ? 'Tắt (đã có phiếu dùng)' : 'Xoá' }}</button>
        </form>
    @endif
@endforeach

@endsection
