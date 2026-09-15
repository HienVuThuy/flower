@extends('layouts.admin')

@section('title', 'Hạng thành viên')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Hạng thành viên</h1>
    <p class="admin-page-subtitle mb-0">
        Hạng tính theo tiền hàng của các đơn <strong>đã giao</strong> (không gồm phí vận chuyển, trừ phần đã hoàn) —
        không theo điểm, nên khách tiêu điểm không bị tụt hạng. Hạng thấp nhất luôn từ 0đ.
    </p>
</div>

<form method="POST" action="{{ route('admin.member-tiers.update') }}">
    @csrf
    @method('PUT')

    @error('hang')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="admin-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Tên hạng</th>
                        <th scope="col">Chi tiêu từ (đồng)</th>
                        <th scope="col">Thưởng thêm điểm khi đơn được giao (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cacHang as $i => $hang)
                        <tr data-hang-admin="{{ $hang->code }}">
                            <td>
                                <input type="hidden" name="hang[{{ $i }}][id]" value="{{ $hang->id }}">
                                <input type="text" name="hang[{{ $i }}][name]" maxlength="50" required
                                       class="form-control @error('hang.' . $i . '.name') is-invalid @enderror"
                                       value="{{ old('hang.' . $i . '.name', $hang->name) }}" aria-label="Tên hạng">
                            </td>
                            <td>
                                <input type="number" name="hang[{{ $i }}][min_spend]" min="0" step="1" required
                                       class="form-control @error('hang.' . $i . '.min_spend') is-invalid @enderror"
                                       value="{{ old('hang.' . $i . '.min_spend', (int) $hang->min_spend) }}" aria-label="Ngưỡng chi tiêu">
                            </td>
                            <td>
                                <input type="number" name="hang[{{ $i }}][bonus_points_percent]" min="0" max="100" step="1" required
                                       class="form-control @error('hang.' . $i . '.bonus_points_percent') is-invalid @enderror"
                                       value="{{ old('hang.' . $i . '.bonus_points_percent', $hang->bonus_points_percent) }}" aria-label="Phần trăm điểm thưởng thêm">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <button type="submit" class="btn btn-primary-brand mt-3">Lưu</button>
</form>

@endsection
