@extends('layouts.admin')

@section('title', 'Kiểm kê kho')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Kiểm kê kho</h1>
        <p class="admin-page-subtitle mb-0">
            Đi đếm hàng thật trên kệ, ghi chênh lệch với hệ thống. Cây chết, chậu vỡ, hoa héo phải bỏ — những thứ không qua đơn nào — chỉ vào sổ qua đây.
        </p>
    </div>

    <a data-admin-link href="{{ route('admin.stock-counts.create') }}" class="btn btn-primary-brand">Lập phiếu kiểm kê</a>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a data-admin-link href="{{ route('admin.stock-counts.index') }}"
       class="btn btn-sm {{ $trangThai === null ? 'btn-primary-brand' : 'btn-outline-admin' }}">Tất cả</a>
    @foreach($cacTrangThai as $tt)
        <a data-admin-link href="{{ route('admin.stock-counts.index', ['trang-thai' => $tt->value]) }}"
           class="btn btn-sm {{ $trangThai === $tt ? 'btn-primary-brand' : 'btn-outline-admin' }}">{{ $tt->label() }}</a>
    @endforeach
</div>

<div class="admin-panel">
    @if($phieu->isEmpty())
        <p class="p-4 mb-0 admin-page-subtitle text-center">
            @if($trangThai)
                Không có phiếu nào ở trạng thái này.
            @else
                Chưa có phiếu kiểm kê nào.
            @endif
        </p>
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Mã phiếu</th>
                        <th>Ngày đếm</th>
                        <th class="text-end text-nowrap">Dòng đếm</th>
                        <th class="text-end text-nowrap">Dòng lệch</th>
                        <th class="text-end text-nowrap">Tổng chênh</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($phieu as $p)
                        @php $tong = $p->items->sum(fn ($i) => $i->chenhLech()); @endphp
                        <tr>
                            <td><a data-admin-link href="{{ route('admin.stock-counts.show', $p) }}" class="fw-bold">{{ $p->code }}</a></td>
                            <td>{{ $p->counted_at->format('d/m/Y') }}</td>
                            <td class="text-end">{{ $p->items->count() }}</td>
                            <td class="text-end">{{ $p->soDongLech() }}</td>
                            <td class="text-end {{ $tong < 0 ? 'text-danger' : '' }}">{{ $tong > 0 ? '+' : '' }}{{ $tong }}</td>
                            <td><span class="status-pill status-pill--{{ $p->status->badge() }}">{{ $p->status->label() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-3">{{ $phieu->links() }}</div>
    @endif
</div>

@endsection
