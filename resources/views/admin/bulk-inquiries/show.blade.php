@extends('layouts.admin')

@section('title', 'Chi tiết yêu cầu')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h1 class="admin-page-title">
            Yêu cầu #{{ $bulkInquiry->id }}
        </h1>

        <p class="admin-page-subtitle">
            Gửi lúc <x-site.time :at="$bulkInquiry->created_at" format="d/m/Y H:i" />
        </p>
    </div>

    <a href="{{ route('admin.bulk-inquiries.index') }}" class="btn btn-light px-4">
        Quay lại
    </a>

</div>

<div class="row g-4">

    <div class="col-lg-7">

        <div class="admin-panel p-4">

            <h2 class="h6 fw-bold mb-3">Thông tin liên hệ</h2>

            <div class="row g-3 mb-2">

                <div class="col-md-6">
                    <span class="text-muted small">Họ tên</span>
                    <div class="fw-semibold">{{ $bulkInquiry->contact_name }}</div>
                </div>

                {{--
                    BẤM ĐƯỢC, không chỉ đọc được.

                    Việc tiếp theo sau khi đọc phiếu này luôn là gọi hoặc gửi
                    báo giá. Chép tay số điện thoại sang máy khác là chỗ gõ
                    nhầm một chữ số và gọi nhầm người.

                    `tel:` chỉ giữ chữ số và dấu +: khách gõ "0912 345 678" hay
                    "(091) 234-5678" đều phải thành một số gọi được.
                --}}
                <div class="col-md-6">
                    <span class="text-muted small">Điện thoại</span>
                    <div class="fw-semibold">
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $bulkInquiry->contact_phone) }}">{{ $bulkInquiry->contact_phone }}</a>
                    </div>
                </div>

                <div class="col-md-6">
                    <span class="text-muted small">Email</span>
                    <div class="fw-semibold">
                        @if($bulkInquiry->contact_email)
                            {{-- Tiêu đề điền sẵn: khách tìm lại thư báo giá trong hộp thư theo đúng dịp họ đã hỏi. --}}
                            <a href="mailto:{{ $bulkInquiry->contact_email }}?subject={{ rawurlencode('Báo giá ' . ($bulkInquiry->occasion ?: 'đặt hoa số lượng lớn') . ' — ' . \App\Services\Shop\StoreProfile::name()) }}">{{ $bulkInquiry->contact_email }}</a>
                        @else
                            —
                        @endif
                    </div>
                </div>

                <div class="col-md-6">
                    <span class="text-muted small">Số lượng dự kiến</span>
                    <div class="fw-semibold">{{ $bulkInquiry->quantity_estimate ?? '—' }}</div>
                </div>

                <div class="col-md-6">
                    <span class="text-muted small">Dịp / sự kiện</span>
                    <div class="fw-semibold">{{ $bulkInquiry->occasion ?? '—' }}</div>
                </div>

                {{--
                    CHI TIẾT SỰ KIỆN — chỉ hiện ô nào khách có điền.

                    Mọi ô ở biểu mẫu đều không bắt buộc, nên phần lớn phiếu sẽ
                    thiếu vài thứ. In đủ mười dòng với tám dấu "—" thì nhân viên
                    phải đọc lướt qua toàn chỗ trống để tìm hai dòng có nội dung.
                    Ẩn hẳn ô trống thì cái gì hiện ra đều là thông tin thật.
                --}}
                @if($bulkInquiry->event_date)
                    <div class="col-md-6">
                        <span class="text-muted small">Ngày cần hoa</span>
                        <div class="fw-semibold">
                            {{ $bulkInquiry->event_date->format('d/m/Y') }}

                            @php($conLai = $bulkInquiry->daysUntilEvent())
                            @if($conLai !== null)
                                {{-- Mức gấp là thứ quyết định làm phiếu nào trước.
                                     Ngày trần trụi bắt nhân viên tự nhẩm. --}}
                                <span class="text-muted small">
                                    @if($conLai < 0)
                                        (đã qua {{ abs($conLai) }} ngày)
                                    @elseif($conLai === 0)
                                        (hôm nay)
                                    @else
                                        (còn {{ $conLai }} ngày)
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>
                @endif

                @if($bulkInquiry->event_location)
                    <div class="col-md-6">
                        <span class="text-muted small">Nơi giao / địa điểm</span>
                        <div class="fw-semibold">{{ $bulkInquiry->event_location }}</div>
                    </div>
                @endif

                @if($bulkInquiry->budgetText())
                    <div class="col-md-6">
                        <span class="text-muted small">Ngân sách</span>
                        <div class="fw-semibold">{{ $bulkInquiry->budgetText() }}</div>
                    </div>
                @endif

                @if($bulkInquiry->company_name)
                    <div class="col-md-6">
                        <span class="text-muted small">Công ty / đơn vị</span>
                        <div class="fw-semibold">{{ $bulkInquiry->company_name }}</div>
                    </div>
                @endif

                @if($bulkInquiry->color_preference)
                    <div class="col-md-6">
                        <span class="text-muted small">Tông màu mong muốn</span>
                        <div class="fw-semibold">{{ $bulkInquiry->color_preference }}</div>
                    </div>
                @endif

                @if($bulkInquiry->flower_preference)
                    <div class="col-md-6">
                        <span class="text-muted small">Loại hoa ưa thích</span>
                        <div class="fw-semibold">{{ $bulkInquiry->flower_preference }}</div>
                    </div>
                @endif

                @if($bulkInquiry->preferred_contact)
                    <div class="col-md-6">
                        <span class="text-muted small">Muốn được liên hệ bằng</span>
                        <div class="fw-semibold">{{ $bulkInquiry->preferred_contact->label() }}</div>
                    </div>
                @endif

                <div class="col-md-6">
                    <span class="text-muted small">Sản phẩm quan tâm</span>
                    <div class="fw-semibold">
                        @if($bulkInquiry->product)
                            <a href="{{ route('admin.products.show', $bulkInquiry->product) }}">
                                {{ $bulkInquiry->product->name }}
                            </a>
                        @else
                            —
                        @endif
                    </div>
                </div>

            </div>

            @if($bulkInquiry->message)
                <div class="mt-3">
                    <span class="text-muted small">Nội dung yêu cầu</span>
                    <div class="mt-1">{{ $bulkInquiry->message }}</div>
                </div>
            @endif

        </div>

    </div>

    <div class="col-lg-5">

        <div class="admin-panel p-4">

            <h2 class="h6 fw-bold mb-3">Xử lý yêu cầu</h2>

            <div class="mb-3">
                <span class="text-muted small">Trạng thái hiện tại</span>
                <div>
                    <span class="badge {{ $bulkInquiry->status->badgeClass() }}">
                        {{ $bulkInquiry->status->label() }}
                    </span>
                </div>
            </div>

            @if($bulkInquiry->handledBy)
                <div class="mb-3 small text-muted">
                    Cập nhật lần cuối bởi {{ $bulkInquiry->handledBy->name }}
                    lúc <x-site.time :at="$bulkInquiry->handled_at" format="d/m/Y H:i" />
                </div>
            @endif

            <form action="{{ route('admin.bulk-inquiries.update-status', $bulkInquiry) }}" method="POST">

                @csrf
                @method('PATCH')

                <div class="mb-3">
                    <label class="form-label">Cập nhật trạng thái</label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror">
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected($bulkInquiry->status === $status)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="status"/>
                </div>

                <div class="mb-3">
                    <label class="form-label">Ghi chú nội bộ</label>
                    <textarea name="admin_notes" rows="4" class="form-control @error('admin_notes') is-invalid @enderror">{{ old('admin_notes', $bulkInquiry->admin_notes) }}</textarea>
                    <x-form-error name="admin_notes"/>
                </div>

                <button type="submit" class="btn btn-primary-brand px-4">
                    Lưu cập nhật
                </button>

            </form>

        </div>

    </div>

</div>

@endsection
