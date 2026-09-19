@php
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

        <div class="form-panel" data-km-hinh-thuc>

            <h2 class="form-panel__title">Hình thức ưu đãi</h2>
            <p class="form-panel__hint">
                Giảm giá: áp cho các sản phẩm chọn ở phần bên dưới, từng sản phẩm vẫn ghi đè được mức riêng.
                Tặng quà: đơn đạt điều kiện được thêm quà 0đ; nếu chọn sản phẩm thì đơn phải có một trong số đó.
            </p>

            @php $typeOld = old('type', $promotion->type->value ?? 'percent'); @endphp

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label" for="km-type">Hình thức <span class="text-accent">*</span></label>
                    <select id="km-type" name="type" class="form-select @error('type') is-invalid @enderror" data-km-type>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" @selected($typeOld === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="type"/>
                </div>

                <div class="col-md-6" data-km-khi="giam">
                    <label class="form-label" for="km-value">Mức giảm <span class="text-accent">*</span></label>
                    <input
                        type="number"
                        id="km-value"
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

            <div class="row g-3 mt-1" data-km-khi="qua">

                @if($vatPham->isEmpty())
                    <div class="col-12">
                        <div class="alert alert-warning mb-0">
                            Chưa có vật phẩm quà nào. <a data-admin-link href="{{ route('admin.gift-items.create') }}">Thêm vật phẩm</a> trước.
                        </div>
                    </div>
                @endif

                <div class="col-md-8">
                    <label class="form-label" for="km-gift">Quà tặng <span class="text-accent">*</span></label>
                    <select id="km-gift" name="gift_item_id" class="form-select @error('gift_item_id') is-invalid @enderror">
                        <option value="">— Chọn quà —</option>
                        @foreach($vatPham as $vat)
                            <option value="{{ $vat->id }}" @selected((string) old('gift_item_id', $promotion->gift_item_id) === (string) $vat->id)>
                                {{ $vat->name }}{{ $vat->is_active ? '' : ' (đang ngừng)' }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text"><a data-admin-link href="{{ route('admin.gift-items.index') }}">Kho vật phẩm quà</a></div>
                    <x-form-error name="gift_item_id"/>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="km-gift-qty">Số lượng mỗi đơn</label>
                    <input type="number" id="km-gift-qty" name="gift_quantity" min="1" max="100"
                           class="form-control @error('gift_quantity') is-invalid @enderror"
                           value="{{ old('gift_quantity', $promotion->gift_quantity ?? 1) }}">
                    <x-form-error name="gift_quantity"/>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="km-min-order">Đơn từ (đồng)</label>
                    <input type="number" id="km-min-order" name="min_order_amount" min="0" step="1"
                           class="form-control @error('min_order_amount') is-invalid @enderror"
                           value="{{ old('min_order_amount', $promotion->min_order_amount !== null ? (int) $promotion->min_order_amount : '') }}">
                    <x-form-error name="min_order_amount"/>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="km-tier">Dành cho hạng</label>
                    <select id="km-tier" name="min_member_tier_id" class="form-select @error('min_member_tier_id') is-invalid @enderror">
                        <option value="">— Mọi khách —</option>
                        @foreach($cacHang as $h)
                            <option value="{{ $h->id }}" @selected((string) old('min_member_tier_id', $promotion->min_member_tier_id) === (string) $h->id)>Từ hạng {{ $h->name }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="min_member_tier_id"/>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="km-per-user">Mỗi tài khoản nhận tối đa (lần)</label>
                    <input type="number" id="km-per-user" name="per_user_limit" min="1" max="1000"
                           class="form-control @error('per_user_limit') is-invalid @enderror"
                           value="{{ old('per_user_limit', $promotion->per_user_limit) }}" placeholder="Không giới hạn">
                    <x-form-error name="per_user_limit"/>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="km-total">Tổng số suất</label>
                    <input type="number" id="km-total" name="total_limit" min="1"
                           class="form-control @error('total_limit') is-invalid @enderror"
                           value="{{ old('total_limit', $promotion->total_limit) }}" placeholder="Không giới hạn">
                    @if($promotion->exists && $promotion->used_count > 0)
                        <div class="form-text">Đã phát {{ $promotion->used_count }} suất.</div>
                    @endif
                    <x-form-error name="total_limit"/>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="first_order_only" value="0">
                        <input class="form-check-input" type="checkbox" id="km-first" name="first_order_only" value="1"
                               @checked(old('first_order_only', $promotion->first_order_only))>
                        <label class="form-check-label" for="km-first">Chỉ cho đơn đầu tiên của tài khoản (khách phải đăng nhập)</label>
                    </div>
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
                    value="{{ old('starts_at', \App\Services\Time\Gio::choO($promotion->starts_at ?? null)) }}"
                >
                <x-form-error name="starts_at"/>
            </div>

            <div class="mb-3">
                <label class="form-label">Kết thúc</label>
                <input
                    type="datetime-local"
                    name="ends_at"
                    class="form-control @error('ends_at') is-invalid @enderror"
                    value="{{ old('ends_at', \App\Services\Time\Gio::choO($promotion->ends_at ?? null)) }}"
                >
                <div class="form-text">Bỏ trống nghĩa là không giới hạn.</div>
                <x-form-error name="ends_at"/>
            </div>

            <hr class="my-4">

            <p class="form-label mb-1">Chỉ chạy vào khung giờ / thứ</p>
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
