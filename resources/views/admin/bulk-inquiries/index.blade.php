@extends('layouts.admin')

@section('title', 'Yêu cầu đặt số lượng lớn')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

    <div>
        <h1 class="admin-page-title">
            Yêu cầu đặt số lượng lớn
        </h1>

        <p class="admin-page-subtitle">
            Khách liên hệ đặt hoa/cây số lượng lớn cho sự kiện, cưới hỏi, khai trương...
        </p>
    </div>

    <form method="GET" class="d-flex gap-2">
        <select name="status" class="form-select" style="min-width: 12rem" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            @foreach($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
    </form>

</div>

<x-admin.filter-bar
    :action="route('admin.bulk-inquiries.index')"
    placeholder="Tìm theo tên, số điện thoại hoặc email…"
    :total="$inquiries->total()"
>
    <select name="status" class="form-select" aria-label="Lọc theo trạng thái">
        <option value="">Mọi trạng thái</option>
        @foreach(\App\Enums\InquiryStatus::cases() as $st)
            <option value="{{ $st->value }}" @selected(request('status') === $st->value)>
                {{ $st->label() }}
            </option>
        @endforeach
    </select>
</x-admin.filter-bar>

<div class="admin-panel">

    <div class="table-responsive">

        <table class="admin-table align-middle mb-0">

            <thead>
                <tr>
                    <th>Khách hàng</th>
                    <th>Liên hệ</th>
                    <th>Sản phẩm</th>
                    <th>Số lượng</th>
                    <th>Trạng thái</th>
                    <th>Ngày gửi</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>

            <tbody>

            @forelse($inquiries as $inquiry)

                <tr>
                    <td class="fw-semibold">{{ $inquiry->contact_name }}</td>

                    <td>
                        <div>{{ $inquiry->contact_phone }}</div>
                        @if($inquiry->contact_email)
                            <small class="text-muted">{{ $inquiry->contact_email }}</small>
                        @endif
                    </td>

                    <td>
                        {{ $inquiry->product?->name ?? '—' }}
                    </td>

                    <td>{{ $inquiry->quantity_estimate ?? '—' }}</td>

                    <td>
                        <span class="badge {{ $inquiry->status->badgeClass() }}">
                            {{ $inquiry->status->label() }}
                        </span>
                    </td>

                    <td>
                        <small class="text-muted"><x-site.time :at="$inquiry->created_at" format="d/m/Y H:i" /></small>
                    </td>

                    <td class="text-end">
                        <a href="{{ route('admin.bulk-inquiries.show', $inquiry) }}" class="btn btn-sm btn-outline-secondary">
                            Xem
                        </a>
                    </td>
                </tr>

            @empty

                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        Chưa có yêu cầu nào.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    @if($inquiries->hasPages())
        <div class="p-3 border-top">
            {{ $inquiries->links() }}
        </div>
    @endif

</div>

@endsection
