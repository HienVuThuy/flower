@extends('layouts.admin')

@section('title', 'Lập phiếu chăm hộ tại quầy')

@section('content')

@php
    $cheDo = old('mode', \App\Enums\BoardingMode::Thang->value);
    $rateChon = old('boarding_rate_id', $cacGia->first()?->id);
@endphp

<div class="mb-4">
    <p class="mb-1"><a data-admin-link href="{{ route('admin.boarding.index') }}">&larr; Chăm cây hộ</a></p>
    <h1 class="admin-page-title">Lập phiếu tại quầy</h1>
    <p class="admin-page-subtitle mb-0">
        Chép từ phiếu giấy khách điền — các ô cùng thứ tự với
        <a href="{{ route('admin.boarding.blank') }}" target="_blank" rel="noopener">phiếu trắng</a>.
        Khách không cần tài khoản; ghi email trùng tài khoản thì khách xem được phiếu trên web.
    </p>
</div>

@if($cacGia->isEmpty())
    <div class="admin-panel p-4">
        Chưa có dòng giá nào đang bật. <a data-admin-link href="{{ route('admin.boarding-rates.index') }}">Thêm bảng giá</a> trước.
    </div>
@else
    <form method="POST" action="{{ route('admin.boarding.store') }}" enctype="multipart/form-data"
          class="admin-panel p-4" data-cham-ho data-bao-gia-url="{{ route('shop.boarding.quote') }}" style="max-width: 52rem">
        @csrf

        <h2 class="h6 fw-bold mb-3">1. Khách hàng</h2>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="tq-ten">Họ tên</label>
                <input id="tq-ten" name="customer_name" required maxlength="120" class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name') }}">
                <x-form-error name="customer_name" />
            </div>
            <div class="col-md-6">
                <label class="form-label" for="tq-email">Email <span class="admin-page-subtitle">(không bắt buộc)</span></label>
                <input id="tq-email" type="email" name="customer_email" maxlength="255" class="form-control @error('customer_email') is-invalid @enderror" value="{{ old('customer_email') }}">
                <x-form-error name="customer_email" />
            </div>
        </div>

        <h2 class="h6 fw-bold mb-3">2–4. Cây, thời gian, giao nhận</h2>
        @include('shop.boarding._fields', ['tenCayMacDinh' => '', 'sdtMacDinh' => '', 'ngayGuiMacDinh' => $homNay])

        <h2 class="h6 fw-bold mt-3 mb-1">5. Giá chốt cho cây này</h2>
        <p class="admin-page-subtitle small mb-2">Đã xem cây tận mắt thì ghi giá chốt; bỏ trống là dùng giá tham khảo của loại cây đã chọn.</p>
        <div class="d-flex gap-2">
            <div>
                <label class="form-label small" for="tq-thang">Giá / tháng</label>
                <input id="tq-thang" type="number" name="monthly_price" min="1000" step="1000" class="form-control @error('monthly_price') is-invalid @enderror" value="{{ old('monthly_price') }}">
            </div>
            <div>
                <label class="form-label small" for="tq-nam">Giá / năm</label>
                <input id="tq-nam" type="number" name="yearly_price" min="1000" step="1000" class="form-control @error('yearly_price') is-invalid @enderror" value="{{ old('yearly_price') }}" placeholder="12 tháng">
            </div>
        </div>
        <x-form-error name="monthly_price" />

        <label class="d-flex align-items-center gap-2 mt-3">
            <input type="checkbox" class="form-check-input" name="nhan_cay_ngay" value="1" @checked(old('nhan_cay_ngay', true))>
            <span>Khách mang cây đến ngay — ghi nhận cây về cửa hàng vào ngày gửi</span>
        </label>

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary-brand">Lập phiếu</button>
        </div>
    </form>
@endif

@endsection
