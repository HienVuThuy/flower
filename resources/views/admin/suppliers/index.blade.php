@extends('layouts.admin')

@section('title', 'Nhà cung cấp')

@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Nhà cung cấp</h1>
        <p class="admin-page-subtitle mb-0">
            Nơi cửa hàng lấy hàng. Ghi đủ ở đây thì mới so được cùng một loại hoa mua chỗ nào rẻ hơn.
        </p>
    </div>

    <a data-admin-link href="{{ route('admin.suppliers.create') }}" class="btn btn-primary-brand">
        Thêm nhà cung cấp
    </a>
</div>

<x-admin.filter-bar :action="route('admin.suppliers.index')">
    <input type="search" name="q" class="form-control" placeholder="Tên, điện thoại, địa chỉ"
           value="{{ request('q') }}" aria-label="Tìm nhà cung cấp">

    <select name="loai" class="form-select" aria-label="Lọc theo loại nguồn hàng">
        <option value="">Mọi loại nguồn</option>
        @foreach($cacLoai as $loai)
            <option value="{{ $loai->value }}" @selected(request('loai') === $loai->value)>
                {{ $loai->label() }}
            </option>
        @endforeach
    </select>
</x-admin.filter-bar>

<div class="admin-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Tên</th>
                    <th scope="col">Loại nguồn</th>
                    <th scope="col">Liên hệ</th>
                    <th scope="col">Số lần nhập</th>
                    <th scope="col">Tình trạng</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($nhaCungCap as $ncc)
                    <tr>
                        <td>
                            {{ $ncc->name }}
                            @if($ncc->address)
                                <span class="d-block admin-page-subtitle small">{{ $ncc->address }}</span>
                            @endif
                            @if($ncc->note)
                                {{-- Ghi chú là chỗ đựng thứ quyết định việc chọn ai:
                                     "hay thiếu hàng cuối tuần", "phải gọi trước 2 hôm". --}}
                                <span class="d-block admin-page-subtitle small fst-italic">{{ $ncc->note }}</span>
                            @endif
                        </td>
                        <td>{{ $ncc->kind->label() }}</td>
                        <td>
                            @if($ncc->phone)
                                {{ $ncc->phone }}
                            @endif
                            @if($ncc->email)
                                <span class="d-block admin-page-subtitle small">{{ $ncc->email }}</span>
                            @endif
                            @unless($ncc->phone || $ncc->email)
                                <span class="admin-page-subtitle">chưa có</span>
                            @endunless
                        </td>
                        <td>{{ $ncc->receipts_count }}</td>
                        <td>
                            <span class="badge text-bg-{{ $ncc->is_active ? 'success' : 'secondary' }}">
                                {{ $ncc->is_active ? 'Đang lấy hàng' : 'Đã ngừng' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a data-admin-link href="{{ route('admin.suppliers.edit', $ncc) }}"
                               class="btn btn-sm btn-outline-admin">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="6">
                        Chưa có nhà cung cấp nào. Thêm một cái rồi phiếu nhập mới chọn được.
                    </x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $nhaCungCap->links() }}</div>

@endsection
