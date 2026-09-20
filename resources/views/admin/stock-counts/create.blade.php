@extends('layouts.admin')

@section('title', 'Lập phiếu kiểm kê')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Lập phiếu kiểm kê</h1>
        <p class="admin-page-subtitle mb-0">
            Nhập số đếm được trên kệ. <strong>Để trống</strong> nghĩa là không đếm món đó (khác với đếm được 0).
            Tạo phiếu <strong>chưa</strong> đổi kho — xem lại chênh lệch rồi mới ghi sổ.
        </p>
    </div>
    <a data-admin-link href="{{ route('admin.stock-counts.index') }}" class="btn btn-ghost text-nowrap">Về danh sách</a>
</div>

<form method="POST" action="{{ route('admin.stock-counts.store') }}">
    @csrf

    <div class="admin-panel p-4 mb-3">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="counted_at">Ngày đếm</label>
                <input type="date" id="counted_at" name="counted_at" required
                       value="{{ old('counted_at', \App\Services\Analytics\KhoangThoiGian::diaPhuong(now())->toDateString()) }}"
                       class="form-control @error('counted_at') is-invalid @enderror">
                <x-form-error name="counted_at"/>
            </div>
            <div class="col-md-9">
                <label class="form-label" for="note">Ghi chú</label>
                <input type="text" id="note" name="note" maxlength="500" value="{{ old('note') }}"
                       class="form-control" placeholder="Kiểm kê cuối tháng…">
            </div>
        </div>
    </div>

    <div class="admin-panel mb-3">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Mặt hàng</th>
                        <th class="text-end text-nowrap">Hệ thống đang ghi</th>
                        <th style="width: 8rem">Đếm được</th>
                        <th>Lý do nếu lệch</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($donVi as $d)
                        <tr>
                            <td>
                                {{ $d['ten'] }}
                                @if($d['quy_cach'])
                                    <span class="text-muted">— {{ $d['quy_cach'] }}</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $d['ton'] }}</td>
                            <td>
                                <input type="number" min="0" step="1" name="dem[{{ $d['value'] }}][counted]"
                                       value="{{ old('dem.' . $d['value'] . '.counted') }}"
                                       class="form-control form-control-sm"
                                       aria-label="Số đếm được: {{ $d['label'] }}">
                            </td>
                            <td>
                                <input type="text" maxlength="255" name="dem[{{ $d['value'] }}][reason]"
                                       value="{{ old('dem.' . $d['value'] . '.reason') }}"
                                       class="form-control form-control-sm" placeholder="Vỡ, chết…"
                                       aria-label="Lý do lệch: {{ $d['label'] }}">
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-4 text-center admin-page-subtitle">Chưa có mặt hàng nào bật theo dõi tồn kho.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <button type="submit" class="btn btn-primary-brand">Lập phiếu nháp</button>
</form>

@endsection
