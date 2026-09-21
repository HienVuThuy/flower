@extends('layouts.admin')

@section('title', 'Phiếu chăm hộ ' . $phieu->code)

@section('content')

@php use App\Enums\BoardingHandover; use App\Enums\BoardingStatus; $tt = $phieu->status; @endphp

<div class="mb-4">
    <p class="mb-1"><a data-admin-link href="{{ route('admin.boarding.index') }}">&larr; Chăm cây hộ</a></p>
    <h1 class="admin-page-title">
        Phiếu {{ $phieu->code }}
        <span class="badge text-bg-{{ $tt->tone() }} align-middle">{{ $tt->label() }}</span>
    </h1>
    <p class="admin-page-subtitle mb-0">{{ $phieu->plant_name }} · {{ $phieu->rate?->name }} · {{ $phieu->mode->label() }} · {{ $phieu->source->label() }}</p>
    <a href="{{ route('admin.boarding.print', $phieu) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-admin mt-2">In phiếu</a>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="admin-panel p-4 mb-4">
            <dl class="boarding-money mb-0">
                <dt>Khách</dt><dd>{{ $phieu->tenKhach() }} · {{ $phieu->contact_phone }}{{ $phieu->user ? '' : ' · không có tài khoản' }}</dd>
                <dt>Giao nhận</dt><dd>{{ $phieu->handover->label() }}</dd>
                @if($phieu->address)<dt>Địa chỉ</dt><dd>{{ $phieu->address }}</dd>@endif
                <dt>{{ $phieu->received_on ? 'Đã nhận cây' : 'Hẹn nhận cây' }}</dt><dd>{{ ($phieu->received_on ?? $phieu->drop_off_on)->format('d/m/Y') }}</dd>
                <dt>{{ $phieu->returned_on ? 'Đã trả cây' : 'Hẹn trả cây' }}</dt><dd>{{ ($phieu->returned_on ?? $phieu->return_on)?->format('d/m/Y') ?? 'Khi khách báo' }}</dd>
                @if($phieu->window)<dt>Dịp</dt><dd>{{ $phieu->window->name }}{{ $phieu->repeat_yearly ? ' · lặp lại mỗi năm' : '' }}</dd>@endif
                @if($phieu->product)<dt>Sản phẩm của cửa hàng</dt><dd>{{ $phieu->product->name }}</dd>@endif
                @if($phieu->parent)<dt>Kỳ trước</dt><dd><a data-admin-link href="{{ route('admin.boarding.show', $phieu->parent) }}">{{ $phieu->parent->code }}</a></dd>@endif
                @if($phieu->plant_note)<dt>Tình trạng khách ghi</dt><dd>{{ $phieu->plant_note }}</dd>@endif
                @if($phieu->customer_note)<dt>Lời nhắn</dt><dd>{{ $phieu->customer_note }}</dd>@endif
                @if($phieu->reject_reason)<dt>Lý do</dt><dd>{{ $phieu->reject_reason }}</dd>@endif
            </dl>

            @if($phieu->photo)
                <img src="{{ asset('storage/' . $phieu->photo) }}" alt="Ảnh cây khách gửi" class="boarding-timeline__photo mt-3">
            @endif
        </div>

        @unless($tt->daKetThuc())
            <form method="POST" action="{{ route('admin.boarding.update', $phieu) }}" enctype="multipart/form-data" class="admin-panel p-4 mb-4">
                @csrf
                <h2 class="h6 fw-bold mb-2">Gửi cập nhật cho khách</h2>
                <textarea name="note" rows="2" maxlength="1000" class="form-control mb-2 @error('note') is-invalid @enderror"
                          placeholder="Đã tỉa cành, bón phân; cây ra nụ đều…">{{ old('note') }}</textarea>
                <x-form-error name="note" />
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control" style="max-width: 20rem" aria-label="Ảnh cây">
                    <button type="submit" class="btn btn-primary-brand ms-auto">Gửi cập nhật</button>
                </div>
                <x-form-error name="photo" />
            </form>
        @endunless

        <div class="admin-panel p-4 mb-4" data-viec-them-admin>
            <h2 class="h6 fw-bold mb-1">Việc làm thêm</h2>
            <p class="admin-page-subtitle small">Mỗi việc báo giá riêng theo cây; khách đồng ý mới cộng vào tiền phiếu.</p>

            @include('shop.boarding._viec-them', ['laAdmin' => true])

            @if(in_array($tt, [BoardingStatus::DaXacNhan, BoardingStatus::DangCham, BoardingStatus::ChoTra], true))
                <form method="POST" action="{{ route('admin.boarding.extra.propose', $phieu) }}" class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                    @csrf
                    <input name="viec" required maxlength="200" class="form-control form-control-sm" style="flex: 2 1 12rem" placeholder="Cửa hàng đề xuất: xử lý rệp sáp, thay đất…" aria-label="Việc đề xuất">
                    <input type="number" name="gia" min="0" step="1000" required class="form-control form-control-sm" style="max-width: 9rem" placeholder="Giá" aria-label="Giá">
                    <input name="ghi_chu" maxlength="500" class="form-control form-control-sm" style="flex: 1 1 10rem" placeholder="Vì sao cần" aria-label="Lý do đề xuất">
                    <button type="submit" class="btn btn-sm btn-outline-admin">Đề xuất cho khách</button>
                </form>
                <x-form-error name="viec" />
            @endif
        </div>

        <div class="admin-panel p-4">
            <h2 class="h6 fw-bold mb-3">Nhật ký</h2>
            @include('shop.boarding._timeline')
        </div>
    </div>

    <div class="col-xl-5">
        <div class="admin-panel p-4 mb-4">
            <h2 class="h6 fw-bold mb-3">Tiền</h2>
            @include('shop.boarding._tien')
        </div>

        @if($tt === BoardingStatus::ChoDuyet)
            <form method="POST" action="{{ route('admin.boarding.confirm', $phieu) }}" class="admin-panel p-4 mb-3" data-xac-nhan-cham-ho>
                @csrf @method('PATCH')
                <h2 class="h6 fw-bold mb-3">Xác nhận</h2>
                <label class="form-label small" for="xn-gui">Hẹn ngày nhận cây</label>
                <input id="xn-gui" type="date" name="drop_off_on" required class="form-control mb-2" value="{{ old('drop_off_on', $phieu->drop_off_on->toDateString()) }}">
                <x-form-error name="drop_off_on" />
                <p class="admin-page-subtitle small mb-1">
                    Giá chốt cho CÂY NÀY. Khách chọn dòng "{{ $phieu->rate?->name }}" — giá tham khảo
                    {{ \App\Services\Shop\Money::format((string) $phieu->monthly_price) }}/tháng; xem cây thật rồi sửa nếu cần.
                </p>
                <div class="d-flex gap-2 mb-2">
                    <div>
                        <label class="form-label small" for="xn-thang">Giá / tháng</label>
                        <input id="xn-thang" type="number" name="monthly_price" required min="1000" step="1000" class="form-control @error('monthly_price') is-invalid @enderror"
                               value="{{ old('monthly_price', (int) $phieu->monthly_price) }}">
                    </div>
                    <div>
                        <label class="form-label small" for="xn-nam">Giá / năm</label>
                        <input id="xn-nam" type="number" name="yearly_price" min="1000" step="1000" class="form-control @error('yearly_price') is-invalid @enderror"
                               value="{{ old('yearly_price', (int) $phieu->yearly_price) }}" placeholder="12 tháng">
                    </div>
                </div>
                <x-form-error name="monthly_price" />
                @if($phieu->handover === BoardingHandover::CuaHangLay)
                    <label class="form-label small" for="xn-phi">Phí đến lấy và trả cây</label>
                    <input id="xn-phi" type="number" name="handover_fee" min="0" step="1000" class="form-control mb-2" value="{{ old('handover_fee', 0) }}">
                @endif
                <label class="form-label small" for="xn-dc">Điều chỉnh một lần (âm là giảm)</label>
                <input id="xn-dc" type="number" name="adjustment" step="1000" class="form-control mb-2" value="{{ old('adjustment', 0) }}">
                <input type="text" name="adjustment_reason" maxlength="255" class="form-control mb-2 @error('adjustment_reason') is-invalid @enderror"
                       placeholder="Lý do điều chỉnh (cây to, chậu nặng…)" value="{{ old('adjustment_reason') }}" aria-label="Lý do điều chỉnh">
                <x-form-error name="adjustment_reason" />
                <input type="text" name="note" maxlength="500" class="form-control mb-3" placeholder="Lời nhắn cho khách (không bắt buộc)" aria-label="Lời nhắn">
                <button type="submit" class="btn btn-primary-brand w-100">Xác nhận và báo khách</button>
            </form>

            <form method="POST" action="{{ route('admin.boarding.reject', $phieu) }}" class="admin-panel p-4 mb-3">
                @csrf @method('PATCH')
                <input type="text" name="reason" required maxlength="255" class="form-control mb-2" placeholder="Lý do từ chối (cây bệnh, hết chỗ…)" aria-label="Lý do từ chối">
                <button type="submit" class="btn btn-outline-danger w-100">Từ chối</button>
            </form>
        @endif

        @if($tt === BoardingStatus::DaXacNhan)
            <form method="POST" action="{{ route('admin.boarding.receive', $phieu) }}" class="admin-panel p-4 mb-3">
                @csrf @method('PATCH')
                <h2 class="h6 fw-bold mb-3">Nhận cây</h2>
                <input type="date" name="ngay" required class="form-control mb-2" value="{{ $homNay->toDateString() }}" aria-label="Ngày nhận cây">
                <input type="text" name="note" maxlength="500" class="form-control mb-3" placeholder="Tình trạng cây lúc nhận" aria-label="Tình trạng cây lúc nhận">
                <button type="submit" class="btn btn-primary-brand w-100">Đã nhận cây</button>
            </form>
        @endif

        @if(in_array($tt, [BoardingStatus::DangCham, BoardingStatus::ChoTra], true))
            <form method="POST" action="{{ route('admin.boarding.return', $phieu) }}" class="admin-panel p-4 mb-3">
                @csrf @method('PATCH')
                <h2 class="h6 fw-bold mb-2">Trả cây</h2>
                <p class="admin-page-subtitle small">Tiền chăm tính lại theo ngày thực trả.
                    @if($phieu->repeat_yearly) Khách chọn lặp lại — trả xong hệ thống mở phiếu kỳ sau. @endif
                </p>
                <input type="date" name="ngay" required class="form-control mb-3" value="{{ $homNay->toDateString() }}" aria-label="Ngày trả cây">
                <x-form-error name="ngay" />
                <button type="submit" class="btn btn-primary-brand w-100">Đã trả cây</button>
            </form>
        @endif

        @if(in_array($tt, [BoardingStatus::DaXacNhan, BoardingStatus::DangCham, BoardingStatus::ChoTra, BoardingStatus::DaTra], true))
            <form method="POST" action="{{ route('admin.boarding.payment', $phieu) }}" class="admin-panel p-4 mb-3" data-ghi-tien-cham-ho>
                @csrf
                <h2 class="h6 fw-bold mb-2">Ghi tiền trực tiếp</h2>
                <p class="admin-page-subtitle small">Tiền khách trả tại quầy hoặc khi giao nhận cây. Nhập số âm khi trả lại tiền cho khách. Tiền trả online qua MoMo tự ghi vào đây.</p>
                <select name="method" class="form-select mb-2" aria-label="Cách trả">
                    @foreach(\App\Enums\BoardingPaymentMethod::ghiTay() as $cach)
                        <option value="{{ $cach->value }}" @selected(old('method') === $cach->value)>{{ $cach->label() }}</option>
                    @endforeach
                </select>
                <input type="number" name="amount" step="1000" required class="form-control mb-2 @error('amount') is-invalid @enderror"
                       value="{{ bccomp($phieu->conLai(), '0', 2) !== 0 ? (int) $phieu->conLai() : '' }}" aria-label="Số tiền">
                <x-form-error name="amount" />
                <input type="text" name="note" maxlength="200" class="form-control mb-3" placeholder="Ghi chú (mã chuyển khoản…)" aria-label="Ghi chú">
                <button type="submit" class="btn btn-outline-admin w-100">Ghi tiền</button>
            </form>
        @endif

        @if($tt->khachHuyDuoc())
            <form method="POST" action="{{ route('admin.boarding.cancel', $phieu) }}" class="admin-panel p-4">
                @csrf @method('PATCH')
                <input type="text" name="reason" required maxlength="255" class="form-control mb-2" placeholder="Lý do huỷ" aria-label="Lý do huỷ">
                <button type="submit" class="btn btn-outline-danger w-100">Huỷ phiếu</button>
            </form>
        @endif
    </div>
</div>

@endsection
