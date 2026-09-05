@extends('layouts.app')

@section('title', $journal->exists ? 'Sửa sổ' : 'Tạo sổ mới')

@section('content')

@php
    $suaSo = $journal->exists;
    // Loại sổ có thể đến từ URL (bấm từ màn hình trống) hoặc từ sổ đang sửa.
    $loaiDangChon = old('kind', request('kind', $journal->kind?->value ?? 'growth'));
@endphp

<section class="section-sm">
    <div class="container-shop" style="max-width: 46rem;">

        <x-site.breadcrumb :items="[
            ['label' => 'Nhật ký của tôi', 'url' => route('shop.journals.index')],
            ['label' => $suaSo ? 'Sửa sổ' : 'Tạo sổ mới'],
        ]" />

        <h1 class="text-h2 mb-4">{{ $suaSo ? 'Sửa sổ' : 'Tạo sổ mới' }}</h1>

        <form method="POST"
              action="{{ $suaSo ? route('shop.journals.update', $journal) : route('shop.journals.store') }}"
              class="surface-card p-4">
            @csrf
            @if($suaSo) @method('PUT') @endif

            <div class="mb-3">
                <label class="form-label" for="title">Tên sổ <span class="text-danger">*</span></label>
                <input type="text" name="title" id="title"
                       class="form-control @error('title') is-invalid @enderror"
                       value="{{ old('title', $journal->title) }}"
                       maxlength="120" required
                       placeholder="Ví dụ: Monstera góc phòng khách">
                <x-form-error name="title"/>
            </div>

            {{--
                LOẠI SỔ HIỆN CẢ CÂU GIẢI THÍCH, không chỉ cái tên.

                "Phân tích cây" hay "Ghi chép tự do" đứng một mình thì
                người chưa dùng bao giờ không đoán được khác nhau ở đâu, và
                họ sẽ chọn bừa cái đầu tiên.
            --}}
            <div class="mb-3">
                <span class="form-label d-block">Kiểu sổ</span>

                @foreach($kinds as $kind)
                    <label class="d-flex gap-2 align-items-start p-2 rounded"
                           style="cursor: pointer; border: 1px solid var(--border-soft); margin-bottom: 0.4rem;">
                        <input type="radio" name="kind" value="{{ $kind->value }}"
                               class="form-check-input mt-1"
                               @checked($loaiDangChon === $kind->value)>
                        <span>
                            <span class="fw-semibold">{{ $kind->label() }}</span>
                            <span class="d-block text-body-sm">{{ $kind->hint() }}</span>
                        </span>
                    </label>
                @endforeach
                <x-form-error name="kind"/>
            </div>

            <div class="mb-3">
                <label class="form-label" for="description">Mô tả</label>
                <textarea name="description" id="description" rows="3"
                          class="form-control @error('description') is-invalid @enderror"
                          maxlength="1000"
                          placeholder="Ghi để sau này mở lại còn nhớ sổ này để làm gì.">{{ old('description', $journal->description) }}</textarea>
                <x-form-error name="description"/>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="product_id">Gắn với cây đã mua</label>
                    <select name="product_id" id="product_id" class="form-select">
                        <option value="">— Không gắn —</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" @selected((int) old('product_id', $journal->product_id) === $p->id)>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                    {{--
                        CHỈ LIỆT KÊ CÂY ĐÃ MUA, không phải cả cửa hàng.

                        Cho gắn vào bất kỳ sản phẩm nào thì trang sổ trở
                        thành một cách dò xem cửa hàng bán gì — và tệ hơn,
                        một cách dựng dữ liệu giả về việc mình đã mua.
                    --}}
                    <p class="form-text">
                        @if($products->isEmpty())
                            Bạn chưa mua cây nào ở đây. Sổ vẫn dùng bình thường mà không cần gắn.
                        @else
                            Gắn rồi thì trang sổ hiện luôn hướng dẫn chăm sóc của cây đó.
                        @endif
                    </p>
                    <x-form-error name="product_id"/>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="started_at">Bắt đầu từ ngày</label>
                    <input type="date" name="started_at" id="started_at"
                           class="form-control @error('started_at') is-invalid @enderror"
                           value="{{ old('started_at', $journal->started_at?->format('Y-m-d')) }}"
                           max="{{ now()->format('Y-m-d') }}">
                    <x-form-error name="started_at"/>
                </div>
            </div>

            {{--
                MỤC TIÊU — nhóm riêng, và nói rõ là không bắt buộc.

                Bốn ô này chỉ có nghĩa với sổ kiểu "Mục tiêu", nhưng KHÔNG
                ẩn đi theo lựa chọn ở trên: ẩn/hiện bằng JavaScript thì
                người tắt JavaScript mất hẳn phần này, còn người bật thì
                thấy khối nhảy ra nhảy vào mỗi lần đổi kiểu sổ. Một sổ sinh
                trưởng đặt thêm mục tiêu "cao 50cm trước tháng 6" cũng là
                chuyện hợp lý — không có lý do gì chặn.
            --}}
            <fieldset class="mb-3 p-3 rounded" style="border: 1px solid var(--border-soft);">
                <legend class="form-label float-none w-auto px-2">Mục tiêu (không bắt buộc)</legend>

                <p class="text-body-sm">
                    Đặt một đích cụ thể để trang sổ hiện thanh tiến độ.
                    Tiến độ tính từ lần ghi gần nhất của đúng chỉ số bạn đặt tên ở đây.
                </p>

                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label" for="target_metric">Theo dõi chỉ số</label>
                        <input type="text" name="target_metric" id="target_metric"
                               class="form-control" maxlength="60"
                               value="{{ old('target_metric', $journal->target_metric) }}"
                               placeholder="Chiều cao">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="target_value">Đạt tới</label>
                        <input type="number" step="0.01" name="target_value" id="target_value"
                               class="form-control"
                               value="{{ old('target_value', $journal->target_value) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="target_unit">Đơn vị</label>
                        <input type="text" name="target_unit" id="target_unit"
                               class="form-control" maxlength="20"
                               value="{{ old('target_unit', $journal->target_unit) }}"
                               placeholder="cm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="target_date">Trước ngày</label>
                        <input type="date" name="target_date" id="target_date"
                               class="form-control"
                               value="{{ old('target_date', $journal->target_date?->format('Y-m-d')) }}">
                    </div>
                </div>
            </fieldset>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary-brand">
                    {{ $suaSo ? 'Lưu thay đổi' : 'Tạo sổ' }}
                </button>
                <a href="{{ $suaSo ? route('shop.journals.show', $journal) : route('shop.journals.index') }}"
                   class="btn btn-ghost">Huỷ</a>
            </div>
        </form>

        @if($suaSo)
            {{--
                XOÁ TÁCH HẲN KHỎI BIỂU MẪU CHÍNH.

                Nút xoá nằm cạnh nút Lưu là công thức để có người bấm nhầm.
                Đặt ra ngoài, ở một khối riêng, kèm câu nói rõ hậu quả — và
                nhắc rằng LƯU TRỮ mới là thứ họ đang muốn trong hầu hết
                trường hợp.
            --}}
            <div class="surface-card p-4 mt-4">
                <h2 class="text-h4 mb-2">Xoá sổ này</h2>
                <p class="text-body-sm">
                    Xoá là mất hẳn: toàn bộ trang nhật ký, chỉ số và ảnh bên trong đều đi theo,
                    không khôi phục được.
                    Nếu chỉ muốn cho gọn danh sách thì dùng <strong>Lưu trữ</strong> ở trang sổ.
                </p>

                <form method="POST" action="{{ route('shop.journals.destroy', $journal) }}"
                      onsubmit="return confirm('Xoá sổ &quot;{{ $journal->title }}&quot; cùng toàn bộ nội dung? Không khôi phục được.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost text-danger">Xoá vĩnh viễn</button>
                </form>
            </div>
        @endif

    </div>
</section>

@endsection
