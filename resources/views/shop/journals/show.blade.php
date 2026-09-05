@extends('layouts.app')

@section('title', $journal->title)

@section('content')

@php
    $tienDo = $journal->goalProgress();
    // Chỉ số gợi ý theo kiểu sổ — mời sẵn để người ghi không phải nhìn ô trống.
    $goiY = $journal->kind->suggestedMetrics();
@endphp

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[
            ['label' => 'Nhật ký của tôi', 'url' => route('shop.journals.index')],
            ['label' => $journal->title],
        ]" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">
                    {{ $journal->kind->label() }}
                </span>
                <h1 class="text-h1 section-header__title">{{ $journal->title }}</h1>

                @if($journal->description)
                    <p class="text-body-sm mt-2 mb-0" style="max-width: 62ch;">{{ $journal->description }}</p>
                @endif

                @if($journal->product)
                    <p class="text-body-sm mt-2 mb-0">
                        Gắn với
                        <a href="{{ route('shop.products.show', $journal->product) }}">{{ $journal->product->name }}</a>
                    </p>
                @endif
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('shop.journals.edit', $journal) }}" class="btn btn-ghost">Sửa sổ</a>

                <form method="POST" action="{{ route('shop.journals.archive', $journal) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-ghost">
                        {{ $journal->is_archived ? 'Đưa trở lại' : 'Lưu trữ' }}
                    </button>
                </form>
            </div>
        </div>

        @if($journal->is_archived)
            <div class="alert alert-secondary py-2 px-3">
                Sổ này đang ở mục lưu trữ — vẫn ghi thêm được bình thường.
            </div>
        @endif

        {{-- ---------- MỤC TIÊU ---------- --}}
        @if($journal->target_metric && $journal->target_value !== null)
            <div class="surface-card p-3 mb-4">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <span>
                        <strong>Mục tiêu:</strong>
                        {{ $journal->target_metric }} đạt
                        {{ rtrim(rtrim(number_format((float) $journal->target_value, 2, ',', '.'), '0'), ',') }}{{ $journal->target_unit ? ' ' . $journal->target_unit : '' }}
                        @if($journal->target_date)
                            trước {{ $journal->target_date->format('d/m/Y') }}
                        @endif
                    </span>

                    <span class="fw-bold">
                        {{--
                            CHƯA ĐỦ DỮ KIỆN THÌ NÓI "CHƯA CÓ SỐ LIỆU", KHÔNG NÓI 0%.

                            0% đọc ra là "đã bắt đầu và chưa đi được bước
                            nào". Chưa ghi lần nào là chuyện khác hẳn, và
                            hiện 0% sẽ làm người ta tưởng mình đang tụt lại.
                        --}}
                        @if($tienDo === null)
                            <span class="text-body-sm">Chưa có số liệu cho chỉ số này</span>
                        @else
                            {{ rtrim(rtrim(number_format($tienDo, 1, ',', '.'), '0'), ',') }}%
                        @endif
                    </span>
                </div>

                @if($tienDo !== null)
                    <div class="goal-bar" role="progressbar"
                         aria-valuenow="{{ min(100, max(0, $tienDo)) }}" aria-valuemin="0" aria-valuemax="100"
                         aria-label="Tiến độ mục tiêu">
                        {{-- Chặn ở 100% để thanh không tràn ra ngoài khung khi vượt đích. --}}
                        <div class="goal-bar__fill" style="width: {{ min(100, max(0, $tienDo)) }}%"></div>
                    </div>

                    @if($tienDo >= 100)
                        <p class="text-body-sm mt-2 mb-0">Đã đạt mục tiêu.</p>
                    @endif
                @endif
            </div>
        @endif

        <div class="row g-4">

            {{-- ---------- CỘT TRÁI: BIỂU ĐỒ + DÒNG THỜI GIAN ---------- --}}
            <div class="col-lg-7">

                @if($metricNames->isNotEmpty())
                    @if($metricNames->count() > 1)
                        {{--
                            ĐỔI CHỈ SỐ BẰNG ĐƯỜNG DẪN, không bằng JavaScript.

                            Gửi link cho nhau được, nút Back chạy đúng, và
                            trang vẫn dùng được khi script hỏng — cùng cách
                            đã dùng cho bộ lọc sản phẩm.
                        --}}
                        <div class="filter-chip-group mb-3">
                            @foreach($metricNames as $ten)
                                <a href="{{ route('shop.journals.show', ['journal' => $journal, 'chi-so' => $ten]) }}"
                                   class="filter-chip {{ $chartMetric === $ten ? 'is-active' : '' }}">{{ $ten }}</a>
                            @endforeach
                        </div>
                    @endif

                    <div class="mb-4">
                        <x-journal.metric-chart :series="$series" :metric="$chartMetric" />
                    </div>
                @endif

                <h2 class="text-h3 mb-3">Dòng thời gian</h2>

                @if($journal->entries->isEmpty())
                    <x-site.empty-state
                        title="Chưa ghi lần nào"
                        text="Điền vào biểu mẫu bên cạnh để thêm trang đầu tiên."
                    />
                @else
                    @foreach($journal->entries as $entry)
                        <div class="journal-entry">
                            <div class="journal-entry__rail"></div>

                            <div>
                                <div class="journal-entry__date">
                                    {{ $entry->entry_date->format('d/m/Y') }}
                                    @if($entry->condition)
                                        · <span class="status-pill status-pill--{{ $entry->condition->badge() }}">
                                            {{ $entry->condition->label() }}
                                        </span>
                                    @endif
                                </div>

                                <div class="journal-entry__title">{{ $entry->displayTitle() }}</div>

                                @if($entry->photo)
                                    <x-site.image :path="$entry->photo"
                                                  :alt="'Ảnh ngày ' . $entry->entry_date->format('d/m/Y')"
                                                  class="journal-entry__photo" />
                                @endif

                                @if($entry->body)
                                    <div class="journal-entry__body">{{ $entry->body }}</div>
                                @endif

                                @if($entry->metrics->isNotEmpty())
                                    <div class="journal-entry__metrics">
                                        @foreach($entry->metrics as $metric)
                                            <span class="journal-metric-chip">
                                                <span class="journal-metric-chip__name">{{ $metric->name }}</span>
                                                <span class="journal-metric-chip__value">{{ $metric->display() }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                <form method="POST"
                                      action="{{ route('shop.journals.entries.destroy', [$journal, $entry]) }}"
                                      class="mt-2"
                                      onsubmit="return confirm('Xoá trang nhật ký ngày {{ $entry->entry_date->format('d/m/Y') }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm">Xoá trang này</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @endif

            </div>

            {{-- ---------- CỘT PHẢI: THÊM TRANG MỚI ---------- --}}
            <div class="col-lg-5">
                <div class="surface-card p-4" style="position: sticky; top: 1rem;">
                    <h2 class="text-h4 mb-3">Ghi thêm</h2>

                    <form method="POST" action="{{ route('shop.journals.entries.store', $journal) }}"
                          enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="entry_date">Ngày ghi nhận <span class="text-danger">*</span></label>
                            {{--
                                MẶC ĐỊNH LÀ HÔM NAY, nhưng SỬA ĐƯỢC.

                                Người ta hay ghi bù: chủ nhật ngồi ghi lại cả
                                tuần. Khoá cứng vào hôm nay thì bốn lần ghi
                                của bốn ngày dồn hết vào một ngày, và biểu đồ
                                sinh trưởng thành một cột dựng đứng.
                            --}}
                            <input type="date" name="entry_date" id="entry_date"
                                   class="form-control @error('entry_date') is-invalid @enderror"
                                   value="{{ old('entry_date', now()->format('Y-m-d')) }}"
                                   max="{{ now()->format('Y-m-d') }}" required>
                            <x-form-error name="entry_date"/>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="entry_title">Tiêu đề</label>
                            <input type="text" name="title" id="entry_title" class="form-control"
                                   maxlength="150" value="{{ old('title') }}"
                                   placeholder="Để trống cũng được">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="body">Nội dung</label>
                            <textarea name="body" id="body" rows="4" class="form-control"
                                      maxlength="5000"
                                      placeholder="Hôm nay quan sát thấy gì? Đã làm gì với cây?">{{ old('body') }}</textarea>
                            <x-form-error name="body"/>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="condition">Tình trạng cây</label>
                            <select name="condition" id="condition" class="form-select">
                                <option value="">— Không đánh giá —</option>
                                @foreach($conditions as $c)
                                    <option value="{{ $c->value }}" @selected(old('condition') === $c->value)>
                                        {{ $c->label() }} — {{ $c->hint() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="photo">Ảnh</label>
                            <input type="file" name="photo" id="photo" class="form-control"
                                   accept="image/png,image/jpeg,image/webp">
                            <x-form-error name="photo"/>
                            <p class="form-text">Chụp cùng một góc mỗi lần thì xem lại sẽ thấy rõ cây lớn thế nào.</p>
                        </div>

                        {{--
                            CHỈ SỐ — GỢI Ý SẴN, NHƯNG SỬA ĐƯỢC HẾT.

                            Ba hàng: hai hàng điền sẵn tên theo kiểu sổ, một
                            hàng trống để tự thêm. Tên nào cũng gõ đè được,
                            đơn vị cũng vậy.

                            Hàng nào thiếu tên hoặc thiếu giá trị thì bị bỏ
                            qua lúc lưu — KHÔNG báo lỗi. Bắt lỗi một hàng
                            người ta không định điền là chặn họ vì một việc
                            họ không làm.
                        --}}
                        <div class="mb-3">
                            <span class="form-label d-block">Chỉ số đo được</span>

                            @php
                                $tenGoiY = array_keys($goiY);
                                $donViGoiY = array_values($goiY);
                            @endphp

                            @for($i = 0; $i < 3; $i++)
                                <div class="metric-row">
                                    <input type="text" name="metrics[{{ $i }}][name]"
                                           class="form-control form-control-sm"
                                           maxlength="60"
                                           value="{{ old('metrics.'.$i.'.name', $tenGoiY[$i] ?? '') }}"
                                           placeholder="Tên chỉ số"
                                           aria-label="Tên chỉ số {{ $i + 1 }}">

                                    <input type="number" step="0.01" name="metrics[{{ $i }}][value]"
                                           class="form-control form-control-sm"
                                           value="{{ old('metrics.'.$i.'.value') }}"
                                           placeholder="Giá trị"
                                           aria-label="Giá trị chỉ số {{ $i + 1 }}">

                                    <input type="text" name="metrics[{{ $i }}][unit]"
                                           class="form-control form-control-sm"
                                           maxlength="20"
                                           value="{{ old('metrics.'.$i.'.unit', $donViGoiY[$i] ?? '') }}"
                                           placeholder="Đơn vị"
                                           aria-label="Đơn vị chỉ số {{ $i + 1 }}">
                                </div>
                            @endfor

                            <p class="form-text mb-0">
                                Tự đặt tên chỉ số nào cũng được — "số nụ", "đường kính thân",
                                "giá ngoài chợ". Bỏ trống hàng không dùng.
                            </p>
                        </div>

                        <button type="submit" class="btn btn-primary-brand w-100">Thêm trang</button>
                    </form>
                </div>
            </div>

        </div>

    </div>
</section>

@endsection
