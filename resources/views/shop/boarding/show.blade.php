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

                @php $bgCho = $phieu->baoGiaDangCho(); @endphp
                @if($phieu->status === BoardingStatus::ChoKhachDuyet && $bgCho)
                    <div class="surface-card p-4 mb-4 boarding-quote-cta" data-bao-gia-cho>
                        <h2 class="text-h4 mb-1">Cửa hàng đã gửi báo giá</h2>
                        <p class="text-caption">Xem từng dòng bên dưới. Đồng ý thì xác nhận để thanh toán; muốn đổi gì thì yêu cầu sửa hoặc nhắn nhân viên.</p>

                        @include('shop.boarding._bao-gia', ['laAdmin' => false])

                        @if($bgCho->hetHan())
                            <p class="text-danger small mt-3 mb-0">Báo giá đã hết hạn — bấm "Yêu cầu sửa" để cửa hàng báo giá lại.</p>
                        @else
                            <form method="POST" action="{{ route('shop.boarding.quote.accept', $phieu) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="btn btn-primary-brand w-100">Xác nhận báo giá {{ \App\Services\Shop\Money::format((string) $bgCho->total) }}</button>
                            </form>
                        @endif
                        <x-form-error name="bao_gia" />

                        <details class="mt-3" @if($errors->has('noi_dung') || $bgCho->hetHan()) open @endif>
                            <summary class="small">Yêu cầu sửa báo giá</summary>
                            <form method="POST" action="{{ route('shop.boarding.quote.revise', $phieu) }}" class="mt-2">
                                @csrf
                                <textarea name="noi_dung" rows="2" maxlength="1000" required class="form-control mb-2 @error('noi_dung') is-invalid @enderror"
                                          placeholder="Muốn đổi gì: bỏ việc thay chậu, gửi thêm 1 tháng, xin giảm phí đến lấy…" aria-label="Điều muốn sửa">{{ old('noi_dung') }}</textarea>
                                <x-form-error name="noi_dung" />
                                <textarea name="them_yeu_cau" rows="2" maxlength="2500" class="form-control mb-2"
                                          placeholder="Thêm yêu cầu mới (mỗi dòng một việc, không bắt buộc)" aria-label="Thêm yêu cầu">{{ old('them_yeu_cau') }}</textarea>
                                <button type="submit" class="btn btn-secondary-brand">Gửi yêu cầu sửa</button>
                            </form>
                        </details>
                    </div>
                @elseif($phieu->quotes->isNotEmpty())
                    <div class="surface-card p-4 mb-4">
                        <h2 class="text-h5 mb-3">Báo giá</h2>
                        @include('shop.boarding._bao-gia', ['laAdmin' => false])
                    </div>
                @endif

                <div class="surface-card p-4 mb-4">
                    <h2 class="text-h5 mb-2">Trao đổi với cửa hàng</h2>
                    @include('shop.boarding._trao-doi', ['laAdmin' => false])
                </div>

                <div class="surface-card p-4 mb-4" data-yeu-cau-them>
                    <h2 class="text-h5 mb-1">Yêu cầu thêm</h2>
                    <p class="text-caption">Cần thay chậu, tạo dáng, kích hoa đúng dịp…? Gửi yêu cầu, cửa hàng xem cây rồi báo giá riêng. Bạn đồng ý mới tính tiền.</p>

                    @include('shop.boarding._viec-them', ['laAdmin' => false])

                    @if(in_array($phieu->status, [BoardingStatus::ChoDuyet, BoardingStatus::DaXacNhan, BoardingStatus::DangCham, BoardingStatus::ChoTra], true))
                        <form method="POST" action="{{ route('shop.boarding.extra.store', $phieu) }}" class="mt-3">
                            @csrf
                            <label class="form-label" for="yc-viec">Việc cần làm thêm</label>
                            <input id="yc-viec" name="viec" required maxlength="200" class="form-control mb-2 @error('viec') is-invalid @enderror"
                                   placeholder="Ví dụ: thay chậu to hơn, tỉa tạo dáng tròn" value="{{ old('viec') }}">
                            <x-form-error name="viec" />
                            <input name="ghi_chu" maxlength="500" class="form-control mb-2" placeholder="Ghi chú thêm (không bắt buộc)" aria-label="Ghi chú" value="{{ old('ghi_chu') }}">
                            <button type="submit" class="btn btn-secondary-brand">Gửi yêu cầu</button>
                        </form>
                    @endif
                </div>

                <h2 class="text-h4 mb-3">Nhật ký chăm sóc</h2>
                @include('shop.boarding._timeline')
            </div>

            <div class="col-lg-5">
                <div class="surface-card p-4 mb-4" data-tien-phieu>
                    <h2 class="text-h5 mb-3">Chi phí</h2>
                    @include('shop.boarding._tien')

                    @if($phieu->traOnlineDuoc())
                        @if($coMomo)
                            <form method="POST" action="{{ route('shop.boarding.momo', $phieu) }}" class="mt-3" data-tra-momo>
                                @csrf
                                <button type="submit" class="btn btn-primary-brand w-100">
                                    Trả online qua MoMo {{ \App\Services\Shop\Money::format($phieu->conLai()) }}
                                </button>
                            </form>
                            <p class="text-caption mt-2 mb-0">Hoặc trả trực tiếp (tiền mặt / chuyển khoản) khi giao nhận cây.</p>
                        @else
                            <p class="text-caption mt-3 mb-0">Thanh toán trực tiếp (tiền mặt hoặc chuyển khoản) khi giao nhận cây.</p>
                        @endif
                    @elseif($phieu->status === \App\Enums\BoardingStatus::ChoDuyet)
                        <p class="text-caption mt-3 mb-0">Cửa hàng xác nhận phiếu xong thì bạn trả được — trực tiếp hoặc online.</p>
                    @endif

                    <a href="{{ route('shop.boarding.print', $phieu) }}" target="_blank" rel="noopener" class="btn btn-ghost w-100 mt-3">In phiếu</a>
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
