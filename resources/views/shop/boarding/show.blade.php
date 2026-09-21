@extends('layouts.app')

@section('title', 'Phiếu chăm hộ ' . $phieu->code)

@section('content')

@php use App\Enums\BoardingMode; use App\Enums\BoardingStatus; @endphp

<section class="section">
    <div class="container-shop">

        <x-site.breadcrumb :items="[
            ['label' => 'Chăm cây hộ', 'url' => route('shop.boarding.index')],
            ['label' => 'Cây của tôi', 'url' => route('shop.boarding.mine')],
            ['label' => $phieu->code],
        ]" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">Phiếu {{ $phieu->code }}</span>
                <h1 class="text-h2 section-header__title">{{ $phieu->plant_name }}</h1>
                <span class="badge text-bg-{{ $phieu->status->tone() }}" data-trang-thai-phieu>{{ $phieu->status->label() }}</span>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="surface-card p-4 mb-4">
                    <dl class="boarding-money mb-0">
                        <dt>Loại cây</dt><dd>{{ $phieu->rate?->name }}</dd>
                        <dt>Thời gian</dt>
                        <dd>
                            {{ $phieu->mode->label() }}
                            @if($phieu->mode === BoardingMode::TheoDip && $phieu->window) — {{ $phieu->window->name }} @endif
                        </dd>
                        <dt>{{ $phieu->received_on ? 'Cửa hàng nhận cây' : 'Hẹn gửi cây' }}</dt>
                        <dd>{{ ($phieu->received_on ?? $phieu->drop_off_on)->format('d/m/Y') }}</dd>
                        <dt>{{ $phieu->returned_on ? 'Đã trả cây' : 'Nhận cây lại' }}</dt>
                        <dd>{{ ($phieu->returned_on ?? $phieu->return_on)?->format('d/m/Y') ?? 'Khi bạn báo' }}</dd>
                        <dt>Giao nhận</dt><dd>{{ $phieu->handover->label() }}</dd>
                        @if($phieu->parent)
                            <dt>Kỳ trước</dt><dd><a href="{{ route('shop.boarding.show', $phieu->parent) }}">{{ $phieu->parent->code }}</a></dd>
                        @endif
                        @if($phieu->reject_reason)
                            <dt>Lý do</dt><dd>{{ $phieu->reject_reason }}</dd>
                        @endif
                    </dl>

                    @if($phieu->photo)
                        <img src="{{ asset('storage/' . $phieu->photo) }}" alt="Ảnh cây lúc gửi" class="boarding-timeline__photo mt-3">
                    @endif
                </div>

                <h2 class="text-h4 mb-3">Nhật ký chăm sóc</h2>
                @include('shop.boarding._timeline')
            </div>

            <div class="col-lg-5">
                <div class="surface-card p-4 mb-4">
                    <h2 class="text-h5 mb-3">Chi phí</h2>
                    @include('shop.boarding._tien')
                </div>

                @if(in_array($phieu->status, [BoardingStatus::DangCham, BoardingStatus::ChoTra], true))
                    <form method="POST" action="{{ route('shop.boarding.early', $phieu) }}" class="surface-card p-4 mb-3" data-nhan-som>
                        @csrf
                        <label class="form-label" for="ns-ngay">
                            {{ $phieu->mode === BoardingMode::KhongHen ? 'Hẹn ngày nhận cây lại' : 'Cần nhận cây sớm hơn?' }}
                        </label>
                        <input id="ns-ngay" type="date" name="ngay" required min="{{ $homNay->toDateString() }}" class="form-control mb-2 @error('ngay') is-invalid @enderror">
                        <x-form-error name="ngay" />
                        <p class="text-caption">Tiền chăm tính lại theo thời gian thực gửi.
                            @if(\App\Services\Shop\ThamSoKinhDoanh::so('kinh_doanh.cham_ho.phi_gap') > 0)
                                Báo trước dưới {{ \App\Services\Shop\ThamSoKinhDoanh::so('kinh_doanh.cham_ho.bao_gap_ngay') }} ngày có phí nhận gấp
                                {{ \App\Services\Shop\Money::format(\App\Services\Shop\ThamSoKinhDoanh::so('kinh_doanh.cham_ho.phi_gap')) }}.
                            @endif
                        </p>
                        <button type="submit" class="btn btn-secondary-brand w-100">Hẹn nhận cây</button>
                    </form>
                @endif

                @if($phieu->mode === BoardingMode::TheoDip && ! $phieu->status->daKetThuc())
                    <form method="POST" action="{{ route('shop.boarding.repeat', $phieu) }}" class="surface-card p-4 mb-3">
                        @csrf
                        <input type="hidden" name="bat" value="{{ $phieu->repeat_yearly ? 0 : 1 }}">
                        <p class="mb-2">
                            {{ $phieu->repeat_yearly
                                ? 'Đang lặp lại mỗi năm: qua dịp cửa hàng nhận cây lại chăm tiếp.'
                                : 'Chưa bật lặp lại: qua dịp bạn tự giữ cây.' }}
                        </p>
                        <button type="submit" class="btn btn-ghost w-100">{{ $phieu->repeat_yearly ? 'Tắt lặp lại' : 'Bật lặp lại mỗi năm' }}</button>
                    </form>
                @endif

                @if($phieu->status->khachHuyDuoc())
                    <form method="POST" action="{{ route('shop.boarding.cancel', $phieu) }}" onsubmit="return confirm('Huỷ yêu cầu chăm hộ này?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">Huỷ yêu cầu</button>
                    </form>
                @endif
            </div>
        </div>

    </div>
</section>

@endsection
