@extends('layouts.admin')

@section('title', $user->name)

@section('content')

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">{{ $user->name }}</h1>
        <p class="admin-page-subtitle mb-0">
            {{ $user->email }}
            &middot; {{ $user->role->label() }}
            &middot; tham gia <x-site.time :at="$user->created_at" format="d/m/Y" />
            @if(! $user->email_verified_at)
                &middot; <span class="text-danger">chưa xác thực email</span>
            @endif
            @if($user->isLocked())
                &middot; <span class="text-danger">đang bị khoá</span>
            @endif
        </p>
    </div>

    <a data-admin-link href="{{ route('admin.users.index') }}" class="btn btn-outline-admin">Về danh sách</a>
</div>

{{--
    BỐN CON SỐ, CÙNG ĐỊNH NGHĨA VỚI DANH SÁCH NGƯỜI DÙNG.

    "Đã chi" chỉ tính đơn ĐÃ GIAO. Đơn huỷ đứng riêng một ô: khách huỷ
    nhiều là điều nhân viên cần biết trước khi nhận một đơn COD lớn.
--}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Đơn đã giao" :note="'Trên tổng ' . $user->orders_count . ' đơn đã đặt'">
            {{ $user->completed_orders_count }}
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Đã chi" note="Chỉ tính đơn đã giao.">
            {{ $tien($user->spent_total ?? 0) }}
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Đơn huỷ" note="Gồm cả khách tự huỷ và cửa hàng huỷ.">
            <span class="{{ $user->cancelled_orders_count > 0 ? 'text-danger' : '' }}">{{ $user->cancelled_orders_count }}</span>
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Đặt gần nhất" :note="$user->reviews_count . ' đánh giá đã viết'">
            @if($donGanNhat)
                <x-site.time :at="$donGanNhat" format="d/m/Y" />
            @else
                <span class="admin-page-subtitle">chưa đặt đơn nào</span>
            @endif
        </x-admin.kpi>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-panel p-4">
            <h2 class="h6 fw-bold mb-3">Đơn hàng</h2>

            @if($don->isEmpty())
                <p class="analytics-empty mb-0">Khách này chưa đặt đơn nào.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Mã đơn</th>
                                <th scope="col">Ngày đặt</th>
                                <th scope="col">Trạng thái</th>
                                <th scope="col" class="text-end">Món</th>
                                <th scope="col" class="text-end">Giá trị</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($don as $d)
                                <tr>
                                    <td>
                                        <a data-admin-link href="{{ route('admin.orders.show', $d) }}">{{ $d->order_number }}</a>
                                    </td>
                                    <td class="text-nowrap"><x-site.time :at="$d->created_at" format="d/m/Y H:i" /></td>
                                    <td>{{ $d->status->label() }}</td>
                                    <td class="text-end">{{ $d->items_count }}</td>
                                    <td class="text-end text-nowrap">{{ $tien($d->grand_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">{{ $don->links() }}</div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-panel p-4">
            <h2 class="h6 fw-bold mb-3">Địa chỉ đã lưu</h2>

            @forelse($diaChi as $dc)
                <div class="mb-3 pb-3 border-bottom">
                    <div class="fw-semibold">
                        {{ $dc->recipient_name }}
                        @if($dc->is_default)
                            <span class="badge text-bg-light ms-1">Mặc định</span>
                        @endif
                        @if($dc->label)
                            <span class="admin-page-subtitle small">· {{ $dc->label }}</span>
                        @endif
                    </div>
                    <div>{{ $dc->recipient_phone }}</div>
                    <div class="admin-page-subtitle small">
                        {{ collect([$dc->address_line, $dc->ward, $dc->district, $dc->province])->filter()->implode(', ') }}
                    </div>
                </div>
            @empty
                {{-- Khách vãng lai hoặc chưa lưu địa chỉ: địa chỉ giao nằm trên từng đơn. --}}
                <p class="analytics-empty mb-0">Chưa lưu địa chỉ nào. Địa chỉ giao của từng lần mua nằm trên đơn.</p>
            @endforelse
        </div>
    </div>
</div>

@endsection
