@php
    /*
     * $promotion CHƯA CHẮC TỒN TẠI.
     *
     * Trang "Tạo chương trình" không truyền biến này — cả biểu mẫu vốn
     * dựa vào toán tử ?? để nuốt biến chưa tồn tại ($promotion->name ?? '').
     * Cách đó chỉ đúng cho tới khi ai đó viết một biểu thức không có ??,
     * và lúc đó trang tạo lỗi 500 trong khi trang sửa vẫn chạy — sai một
     * nửa nên rất dễ lọt qua kiểm thử.
     *
     * Một dòng ở đây dựng sẵn một model rỗng, nên mọi thuộc tính đọc
     * được và trả về null. Bẫy này đã gặp đúng hai lần (ở đây và ở
     * admin/products/_form.blade.php) — dập tận gốc thay vì vá từng chỗ.
     */
    $promotion = $promotion ?? new \App\Models\Promotion();
@endphp

<div class="row g-4">

    <div class="col-lg-8">

        <div class="form-panel">

            <h2 class="form-panel__title">Thông tin chương trình</h2>
            <p class="form-panel__hint">Tên và mô tả sẽ hiển thị cho khách ở banner chiến dịch.</p>

            <div class="mb-3">
                <label class="form-label">Tên chương trình <span class="text-accent">*</span></label>
                <input
                    type="text"
                    name="name"
                    class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $promotion->name ?? '') }}"
                    placeholder="Ví dụ: Giáng sinh an lành – Ưu đãi ngập tràn"
                >
                <x-form-error name="name"/>
            </div>

            <div class="mb-3">
                <label class="form-label">Slug</label>
                <input
                    type="text"
                    name="slug"
                    class="form-control @error('slug') is-invalid @enderror"
                    value="{{ old('slug', $promotion->slug ?? '') }}"
                    placeholder="Bỏ trống để tự tạo từ tên"
                >
                <x-form-error name="slug"/>
            </div>

            <div class="mb-3">
                <label class="form-label">Mô tả ngắn</label>
                <input
                    type="text"
                    name="short_description"
                    class="form-control @error('short_description') is-invalid @enderror"
                    value="{{ old('short_description', $promotion->short_description ?? '') }}"
                    placeholder="Một câu tóm tắt hiển thị trên banner"
                >
                <x-form-error name="short_description"/>
            </div>

            <div>
                <label class="form-label">Mô tả chi tiết</label>
                <textarea
                    name="description"
                    rows="5"
                    class="form-control @error('description') is-invalid @enderror"
                >{{ old('description', $promotion->description ?? '') }}</textarea>
                <x-form-error name="description"/>
            </div>

        </div>

        <div class="form-panel">

            <h2 class="form-panel__title">Hình thức khuyến mại</h2>
            <p class="form-panel__hint">
                Đây là mức áp dụng chung. Ở phần "Sản phẩm áp dụng" bên dưới
                vẫn có thể ghi đè riêng cho từng sản phẩm.
            </p>

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">Kiểu giảm giá <span class="text-accent">*</span></label>
                    @php $typeOld = old('type', $promotion->type->value ?? 'percent'); @endphp
                    <select name="type" class="form-select @error('type') is-invalid @enderror">
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" @selected($typeOld === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="type"/>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Mức giảm <span class="text-accent">*</span></label>
                    <input
                        type="number"
                        name="discount_value"
                        min="0"
                        step="any"
                        class="form-control @error('discount_value') is-invalid @enderror"
                        value="{{ old('discount_value', $promotion->discount_value ?? '') }}"
                    >
                    <div class="form-text">
                        Giảm %: nhập 20 nghĩa là −20%. Giảm tiền / giá cố định: nhập số tiền (VNĐ).
                    </div>
                    <x-form-error name="discount_value"/>
                </div>

            </div>

        </div>

    </div>

    <div class="col-lg-4">

        <div class="form-panel">

            <h2 class="form-panel__title">Thời gian &amp; trạng thái</h2>

            <div class="mb-3">
                <label class="form-label">Bắt đầu</label>
                <input
                    type="datetime-local"
                    name="starts_at"
                    class="form-control @error('starts_at') is-invalid @enderror"
                    value="{{ old('starts_at', optional($promotion->starts_at ?? null)->format('Y-m-d\TH:i')) }}"
                >
                <x-form-error name="starts_at"/>
            </div>

            <div class="mb-3">
                <label class="form-label">Kết thúc</label>
                <input
                    type="datetime-local"
                    name="ends_at"
                    class="form-control @error('ends_at') is-invalid @enderror"
                    value="{{ old('ends_at', optional($promotion->ends_at ?? null)->format('Y-m-d\TH:i')) }}"
                >
                <div class="form-text">Bỏ trống nghĩa là không giới hạn.</div>
                <x-form-error name="ends_at"/>
            </div>

            {{--
                ============ GIÁ LINH HOẠT THEO THỜI ĐIỂM ============

                Hai ô trên khai KHOẢNG NGÀY ("từ 1/2 tới 14/2"). Khối này
                khai chu kỳ LẶP LẠI bên trong khoảng đó.

                Trường hợp thật của cửa hàng hoa: hoa tươi còn trên kệ lúc
                20h tối sáng mai không bán được nữa, nên giảm 30% từ 19h là
                thu về 70% thay vì mất trắng. Không có khối này thì chương
                trình đó phải tạo tay lại mỗi ngày.
            --}}
            <hr class="my-4">

            <p class="form-label mb-1">Giá linh hoạt theo thời điểm</p>
            <p class="text-muted small mb-3">
                Bỏ trống hết = chương trình chạy suốt trong khoảng ngày ở trên.
            </p>

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label" for="daily_start_time">Từ giờ</label>
                    <input
                        type="time"
                        id="daily_start_time"
                        name="daily_start_time"
                        class="form-control @error('daily_start_time') is-invalid @enderror"
                        value="{{ old('daily_start_time', $promotion->daily_start_time ? substr($promotion->daily_start_time, 0, 5) : '') }}"
                    >
                    <x-form-error name="daily_start_time"/>
                </div>

                <div class="col-6">
                    <label class="form-label" for="daily_end_time">Đến giờ</label>
                    <input
                        type="time"
                        id="daily_end_time"
                        name="daily_end_time"
                        class="form-control @error('daily_end_time') is-invalid @enderror"
                        value="{{ old('daily_end_time', $promotion->daily_end_time ? substr($promotion->daily_end_time, 0, 5) : '') }}"
                    >
                    <x-form-error name="daily_end_time"/>
                </div>

                <div class="col-12">
                    <div class="form-text">
                        {{-- Nói rõ khung qua nửa đêm vẫn dùng được: admin
                             thường tưởng phải nhập ngược lại hoặc tạo hai
                             chương trình. --}}
                        Ví dụ <strong>19:00 → 22:00</strong> cho ưu đãi xả hàng cuối ngày.
                        Khung qua nửa đêm (<strong>22:00 → 02:00</strong>) cũng hợp lệ.
                        Phải điền cả hai ô thì mới có tác dụng.
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Chỉ áp dụng các thứ</label>

                @php
                    $weekdayNames = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5',
                                     5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'Chủ nhật'];
                    $pickedDays = array_map('intval', (array) old('weekdays', $promotion->weekdays ?? []));
                @endphp

                <div class="d-flex flex-wrap gap-3">
                    @foreach($weekdayNames as $iso => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   id="weekday-{{ $iso }}" name="weekdays[]" value="{{ $iso }}"
                                   @checked(in_array($iso, $pickedDays, true))>
                            <label class="form-check-label" for="weekday-{{ $iso }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>

                <div class="form-text">Không tích ô nào = áp dụng mọi ngày.</div>
                <x-form-error name="weekdays" :array="true"/>
            </div>

            <hr class="my-4">

            <div class="mb-3">
                <label class="form-label">Trạng thái <span class="text-accent">*</span></label>
                @php $statusOld = old('status', $promotion->status->value ?? 'draft'); @endphp
                <select name="status" class="form-select @error('status') is-invalid @enderror">
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected($statusOld === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">
                    Chỉ trạng thái "Đang diễn ra" + đúng khoảng thời gian mới thực sự áp dụng cho khách.
                </div>
                <x-form-error name="status"/>
            </div>

            <div>
                <label class="form-label">Độ ưu tiên <span class="text-accent">*</span></label>
                <input
                    type="number"
                    name="priority"
                    min="0"
                    max="9999"
                    class="form-control @error('priority') is-invalid @enderror"
                    value="{{ old('priority', $promotion->priority ?? 0) }}"
                >
                <div class="form-text">
                    Khi một sản phẩm thuộc nhiều chương trình, chương trình có số lớn hơn được áp dụng.
                </div>
                <x-form-error name="priority"/>
            </div>

        </div>

        <div class="form-panel">

            <h2 class="form-panel__title">Giao diện</h2>

            <div class="mb-3">
                <label class="form-label">Theme gợi ý</label>
                @php $themeOld = old('theme_key', $promotion->theme_key ?? ''); @endphp
                <select name="theme_key" class="form-select @error('theme_key') is-invalid @enderror">
                    <option value="">— Không gắn theme —</option>
                    @foreach($themeOptions as $themeKey => $themeLabel)
                        <option value="{{ $themeKey }}" @selected($themeOld === $themeKey)>
                            {{ $themeLabel }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">
                    Chỉ để ghi nhận chương trình thuộc mùa nào. Việc bật theme cho
                    website vẫn nằm ở <a href="{{ route('admin.settings.edit') }}">Cài đặt</a>.
                </div>
                <x-form-error name="theme_key"/>
            </div>

            <div>
                <label class="form-label">Banner</label>
                <input
                    type="file"
                    name="banner"
                    class="form-control @error('banner') is-invalid @enderror"
                    accept=".jpg,.jpeg,.png,.webp"
                >
                <div class="form-text">JPG, PNG hoặc WEBP. Tối đa 4MB.</div>
                <x-form-error name="banner"/>

                @if(isset($promotion) && $promotion->banner)
                    <img
                        src="{{ asset('storage/' . $promotion->banner) }}"
                        alt="Banner {{ $promotion->name }}"
                        class="img-fluid rounded mt-3"
                    >
                @endif
            </div>

        </div>

    </div>

</div>
