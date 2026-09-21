@extends('layouts.admin')

@section('title', 'Chăm cây hộ')

@section('content')

@php use App\Enums\BoardingStatus; use App\Services\Shop\Money; @endphp

<div class="mb-4">
    <h1 class="admin-page-title">Chăm cây hộ</h1>
    <p class="admin-page-subtitle">
        Khách gửi cây cho cửa hàng chăm và nhận lại đúng hẹn hoặc đúng dịp. Tiền chăm tính theo tháng thực gửi.
        Khách trả trực tiếp (cửa hàng ghi vào phiếu) hoặc trả online qua MoMo (tự ghi). Tiền đã thu vào Sổ thu chi.
        Tháng này đã thu: <strong>{{ \App\Services\Shop\Money::format(\App\Services\Analytics\CashFlowReport::chamHo(\App\Services\Analytics\CashFlowReport::khoangThang(now(\App\Services\Time\Gio::mui())->format('Y-m')))) }}</strong>.
    </p>
</div>

<x-admin.nhom-tab ten="cham-ho" />

<div class="d-flex flex-wrap gap-2 mb-3">
    <a data-admin-link href="{{ route('admin.boarding.create') }}" class="btn btn-primary-brand">Lập phiếu tại quầy</a>
    <a href="{{ route('admin.boarding.blank') }}" target="_blank" rel="noopener" class="btn btn-outline-admin">In phiếu trắng</a>
</div>

<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Lọc theo tình trạng">
    <a data-admin-link href="{{ route('admin.boarding.index', request()->except('trang_thai', 'page')) }}"
       class="filter-chip {{ ! $trangThai ? 'is-active' : '' }}">Tất cả</a>
    @foreach(BoardingStatus::cases() as $tt)
        <a data-admin-link href="{{ route('admin.boarding.index', ['trang_thai' => $tt->value] + request()->except('trang_thai', 'page')) }}"
           class="filter-chip {{ $trangThai === $tt ? 'is-active' : '' }}">
            {{ $tt->label() }}
            <span class="filter-chip__count">{{ $dem[$tt->value] ?? 0 }}</span>
        </a>
    @endforeach
</nav>

<x-admin.filter-bar :action="route('admin.boarding.index')">
    @if($trangThai)<input type="hidden" name="trang_thai" value="{{ $trangThai->value }}">@endif
    <input type="search" name="q" class="form-control" placeholder="Mã phiếu, tên cây, số điện thoại"
           value="{{ request('q') }}" aria-label="Tìm phiếu chăm hộ">
</x-admin.filter-bar>

<div class="admin-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Phiếu</th>
                    <th scope="col">Khách / cây</th>
                    <th scope="col">Thời gian</th>
                    <th scope="col" class="text-end">Tổng / đã thu</th>
                    <th scope="col">Tình trạng</th>
                </tr>
            </thead>
            <tbody>
                @forelse($phieu as $p)
                    <tr data-phieu-cham-ho="{{ $p->code }}">
                        <td>
                            <a data-admin-link href="{{ route('admin.boarding.show', $p) }}">{{ $p->code }}</a>
                            <span class="d-block admin-page-subtitle small"><x-site.time :at="$p->created_at" format="d/m/Y" /></span>
                        </td>
                        <td>
                            {{ $p->tenKhach() }}
                            <span class="d-block admin-page-subtitle small">{{ $p->plant_name }} · {{ $p->rate?->name }}{{ $p->source === \App\Enums\BoardingSource::TaiQuay ? ' · tại quầy' : '' }}</span>
                        </td>
                        <td class="small">
                            {{ $p->mode->label() }}
                            <span class="d-block admin-page-subtitle">
                                {{ ($p->received_on ?? $p->drop_off_on)->format('d/m') }} → {{ $p->return_on?->format('d/m/Y') ?? 'chưa hẹn' }}
                                @if($p->repeat_yearly) · lặp lại @endif
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            {{ Money::format($p->tongTien()) }}
                            <span class="d-block admin-page-subtitle small">{{ Money::format($p->paid_amount) }}</span>
                        </td>
                        <td><span class="badge text-bg-{{ $p->status->tone() }}">{{ $p->status->label() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted">Chưa có phiếu nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $phieu->links() }}</div>

@endsection
