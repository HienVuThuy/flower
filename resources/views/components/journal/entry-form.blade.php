@props(['journal', 'conditions'])

@php
    $kind = $journal->kind;
    $tu = $kind->entryWords();
    $goiY = $kind->suggestedMetrics();
    $nhanDan = \App\Enums\JournalSticker::forKind($kind);
@endphp

<form method="POST" action="{{ route('shop.journals.entries.store', $journal) }}"
      enctype="multipart/form-data">
    @csrf

    <div class="mb-3">
        <label class="form-label" for="entry_date">Ngày ghi nhận <span class="text-danger">*</span></label>
        <input type="date" name="entry_date" id="entry_date"
               class="form-control @error('entry_date') is-invalid @enderror"
               value="{{ old('entry_date', \App\Services\Time\Gio::choONgay(now())) }}"
               max="{{ \App\Services\Time\Gio::choONgay(now()) }}" required>
        <x-form-error name="entry_date"/>
    </div>

    @if($kind->hasField('price'))
        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label" for="price">
                    Giá thấy được ({{ \App\Services\Shop\Money::symbol() }})
                </label>
                <input type="number" step="1" min="0" name="price" id="price"
                       class="form-control @error('price') is-invalid @enderror"
                       value="{{ old('price') }}" inputmode="numeric"
                       placeholder="450000">
                <x-form-error name="price"/>
            </div>

            <div class="col-6">
                <label class="form-label" for="place">Khảo ở đâu</label>
                <input type="text" name="place" id="place" class="form-control"
                       maxlength="120" value="{{ old('place') }}"
                       placeholder="Chợ hoa Quảng An">
            </div>
        </div>
    @endif

    @if($kind->hasField('title'))
        <div class="mb-3">
            <label class="form-label" for="entry_title">Tiêu đề</label>
            <input type="text" name="title" id="entry_title" class="form-control"
                   maxlength="150" value="{{ old('title') }}"
                   placeholder="Để trống cũng được">
        </div>
    @endif

    @if($kind->hasField('rating'))
        <div class="mb-3">
            <span class="form-label d-block">Chấm điểm lần này</span>

            <div class="rating-input">
                @for($i = 1; $i <= 5; $i++)
                    <label class="rating-input__option">
                        <input type="radio" name="rating" value="{{ $i }}"
                               @checked((int) old('rating') === $i)>
                        <span class="rating-input__box">{{ $i }}</span>
                    </label>
                @endfor

                <label class="rating-input__option">
                    <input type="radio" name="rating" value="" @checked(old('rating') === null)>
                    <span class="rating-input__box rating-input__box--none">chưa chấm</span>
                </label>
            </div>
            <x-form-error name="rating"/>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="good">Được</label>
                <textarea name="good" id="good" rows="2" class="form-control" maxlength="500"
                          placeholder="Lá lên đều, không rụng">{{ old('good') }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="bad">Chưa được</label>
                <textarea name="bad" id="bad" rows="2" class="form-control" maxlength="500"
                          placeholder="Mép lá vẫn khô">{{ old('bad') }}</textarea>
            </div>
        </div>
    @endif

    @if($kind->hasField('body'))
        <div class="mb-3">
            <label class="form-label" for="body">Nội dung</label>
            <textarea name="body" id="body" rows="4" class="form-control"
                      maxlength="5000"
                      placeholder="{{ $kind === \App\Enums\JournalKind::Price
                          ? 'Còn hàng không? Cây to cỡ nào?'
                          : 'Hôm nay quan sát thấy gì? Đã làm gì với cây?' }}">{{ old('body') }}</textarea>
            <x-form-error name="body"/>
        </div>
    @endif

    @if($kind->hasField('condition'))
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
    @endif

    @if($kind->hasField('care'))
        <div class="mb-3">
            <span class="form-label d-block">Hôm nay đã làm gì</span>

            <div class="care-picker">
                @foreach(\App\Enums\JournalSticker::forKind($kind) as $viec)
                    @if($viec->group() === 'Việc đã làm')
                        <label class="care-picker__option">
                            <input type="checkbox" name="care[]" value="{{ $viec->value }}"
                                   @checked(in_array($viec->value, (array) old('care', []), true))>
                            <span class="care-picker__box">
                                <x-journal.sticker :sticker="$viec" :size="18" decorative />
                                {{ $viec->label() }}
                            </span>
                        </label>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    @if($kind->hasField('photo'))
        <div class="mb-3">
            <label class="form-label" for="photo">Ảnh</label>
            <input type="file" name="photo" id="photo" class="form-control"
                   accept="image/png,image/jpeg,image/webp">
            <x-form-error name="photo"/>
            <p class="form-text">Chụp cùng một góc mỗi lần thì xem lại sẽ thấy rõ cây lớn thế nào.</p>
        </div>
    @endif

    @if($kind->hasField('sticker') && $nhanDan)
        <div class="mb-3">
            <span class="form-label d-block">Nhãn dán</span>

            <div class="sticker-picker">
                <label class="sticker-picker__option">
                    <input type="radio" name="sticker" value="" @checked(! old('sticker'))>
                    <span class="sticker-picker__box sticker-picker__box--none" title="Không dán nhãn">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                             stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                             aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </span>
                    <span class="sticker-picker__label">Không</span>
                </label>

                @foreach($nhanDan as $nhan)
                    <label class="sticker-picker__option">
                        <input type="radio" name="sticker" value="{{ $nhan->value }}"
                               @checked(old('sticker') === $nhan->value)>
                        <span class="sticker-picker__box" title="{{ $nhan->meaning() }}">
                            <x-journal.sticker :sticker="$nhan" :size="22" decorative />
                        </span>
                        <span class="sticker-picker__label">{{ $nhan->label() }}</span>
                    </label>
                @endforeach
            </div>
            <x-form-error name="sticker"/>
        </div>
    @endif

    @if($kind->hasField('metrics'))
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
                Tự đặt tên chỉ số nào cũng được — "số nụ", "đường kính thân".
                Bỏ trống hàng không dùng.
            </p>
        </div>
    @endif

    <button type="submit" class="btn btn-primary-brand w-100">{{ $tu['add'] }}</button>
</form>
