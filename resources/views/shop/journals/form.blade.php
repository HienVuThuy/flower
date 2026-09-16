@extends('layouts.app')

@section('title', $journal->exists ? 'Sửa sổ' : 'Tạo sổ mới')

@section('content')

@php
    $suaSo = $journal->exists;
    $loaiDangChon = old('kind', request('kind', $journal->kind?->value ?? 'growth'));
    $themeDangChon = old('theme_key', $journal->theme_key?->value);
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
              class="surface-card p-4"
              enctype="multipart/form-data"
              data-journal-form>
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

            <div class="mb-3">
                <span class="form-label d-block">Kiểu sổ</span>

                <div class="kind-picker">
                    @foreach($kinds as $kind)
                        @php
                            $khoi = [
                                'goal' => 'thanh tiến độ mục tiêu',
                                'milestones' => 'danh sách mốc cần đạt',
                                'chart' => 'biểu đồ theo thời gian',
                                'photo-strip' => 'dải ảnh theo thời gian',
                                'care-summary' => 'tổng hợp việc đã chăm',
                                'price-stats' => 'thống kê giá cao/thấp',
                                'price-table' => 'bảng các lần khảo giá',
                                'rating' => 'điểm bạn tự chấm',
                                'findings' => 'gom được / chưa được',
                                'timeline' => 'dòng thời gian',
                            ];
                        @endphp

                        <label class="kind-picker__option">
                            <input type="radio" name="kind" value="{{ $kind->value }}"
                                   @checked($loaiDangChon === $kind->value)
                                   data-kind-radio>

                            <span class="kind-picker__box">
                                <span class="kind-picker__head">
                                    <x-site.icon :name="$kind->icon()" class="kind-picker__icon" />
                                    <span class="kind-picker__name">{{ $kind->label() }}</span>
                                </span>

                                <span class="kind-picker__hint">{{ $kind->hint() }}</span>

                                <span class="kind-picker__panels">
                                    @foreach($kind->panels() as $p)
                                        @if(isset($khoi[$p]))
                                            <span class="kind-picker__chip">{{ $khoi[$p] }}</span>
                                        @endif
                                    @endforeach
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-form-error name="kind"/>
            </div>

            <div class="mb-3">
                <span class="form-label d-block">Giao diện sổ</span>

                <div class="theme-picker">
                    <label class="theme-picker__option">
                        <input type="radio" name="theme_key" value="" @checked(! $themeDangChon)>
                        <span class="theme-picker__swatch theme-picker__swatch--auto">
                            <span class="theme-picker__name">Theo kiểu sổ</span>
                        </span>
                    </label>

                    @foreach(\App\Enums\JournalTheme::cases() as $theme)
                        <label class="theme-picker__option">
                            <input type="radio" name="theme_key" value="{{ $theme->value }}"
                                   @checked($themeDangChon === $theme->value)>
                            <span class="theme-picker__swatch {{ $theme->token() }}">
                                @if($theme->pattern())
                                    <x-journal.pattern :pattern="$theme->pattern()" />
                                @endif
                                <span class="theme-picker__name">{{ $theme->label() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-form-error name="theme_key"/>
            </div>

            <div class="mb-3">
                <label class="form-label" for="cover_image">Ảnh bìa sổ</label>

                @if($journal->cover_image)
                    <div class="journal-cover-current">
                        <x-site.image :path="$journal->cover_image" alt="Ảnh bìa hiện tại"
                                      class="journal-cover-current__img" />
                        <label class="journal-cover-current__remove">
                            <input type="checkbox" name="remove_cover" value="1">
                            Bỏ ảnh bìa
                        </label>
                    </div>
                @endif

                <input type="file" name="cover_image" id="cover_image" class="form-control"
                       accept="image/png,image/jpeg,image/webp">
                <x-form-error name="cover_image"/>
                <p class="form-text">
                    Không bắt buộc — giao diện sổ ở trên đã đủ để phân biệt các quyển với nhau.
                </p>
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
                           max="{{ \App\Services\Time\Gio::choONgay(now()) }}">
                    <x-form-error name="started_at"/>
                </div>
            </div>

            @foreach($kinds as $kind)
                @php
                    $oNhap = [
                        'price' => 'ô nhập giá',
                        'place' => 'nơi khảo giá',
                        'condition' => 'tình trạng cây',
                        'care' => 'tích việc đã chăm (tưới, bón, thay chậu, cắt tỉa)',
                        'photo' => 'tải ảnh lên',
                        'rating' => 'chấm điểm 1–5',
                        'good' => 'ô “được” / “chưa được”',
                        'sticker' => 'nhãn dán',
                        'metrics' => 'chỉ số tự đặt tên',
                    ];

                    $co = collect($kind->entryFields())
                        ->map(fn ($f) => $oNhap[$f] ?? null)
                        ->filter()
                        ->values();
                @endphp

                <div class="journal-form-block kind-brief mb-3 p-3 rounded"
                     data-for-kinds="{{ $kind->value }}">

                    <h2 class="kind-brief__title">
                        Sổ “{{ $kind->label() }}” sẽ hoạt động thế nào
                    </h2>

                    <p class="text-body-sm">
                        Mỗi lần ghi, sổ này hỏi bạn:
                        <strong>{{ $co->join(', ') }}</strong>.
                        Nút ghi có tên <em>“{{ $kind->entryWords()['add'] }}”</em>.
                    </p>

                    @if($kind->hasField('metrics') && $kind->suggestedMetrics())
                        <p class="text-body-sm">
                            Chỉ số điền sẵn:
                            @foreach($kind->suggestedMetrics() as $ten => $donVi)
                                <span class="kind-brief__metric">{{ $ten }} ({{ $donVi }})</span>
                            @endforeach
                            — sửa được hết, thêm chỉ số nào cũng được.
                        </p>
                    @endif

                    @if($kind->usesMilestones())
                        <p class="text-body-sm mb-0">
                            Sau khi tạo xong, trang sổ có chỗ để bạn chia mục tiêu thành
                            <strong>các mốc nhỏ</strong>, mỗi mốc một hạn riêng.
                        </p>
                    @endif

                    @if($kind === \App\Enums\JournalKind::Price)
                        <p class="text-body-sm mb-0">
                            Từ lần khảo thứ hai trở đi, sổ tự hiện mức
                            <strong>thấp nhất, cao nhất và khoảng dao động</strong>,
                            kèm chỗ bạn khảo được giá rẻ nhất.
                        </p>
                    @endif

                    <p class="text-body-sm mb-0">
                        Có <strong>{{ count(\App\Enums\JournalSticker::forKind($kind)) }} nhãn dán</strong>
                        hợp với kiểu sổ này, và giao diện mặc định là
                        <strong>{{ $kind->defaultTheme()->label() }}</strong>.
                    </p>
                </div>
            @endforeach

            <fieldset class="journal-form-block mb-3 p-3 rounded"
                      data-for-kinds="goal growth free">
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
            <div class="surface-card p-4 mt-4">
                <h2 class="text-h4 mb-2">Xoá sổ này</h2>
                <p class="text-body-sm">
                    Xoá là mất hẳn: toàn bộ trang nhật ký, chỉ số, mốc và ảnh bên trong đều đi theo,
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
