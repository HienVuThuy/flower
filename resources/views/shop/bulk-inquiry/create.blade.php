@extends('layouts.app')

@section('title', 'Sự kiện & số lượng lớn')

@section('content')

@php
    $daTick = fn (string $ma, array $truong) => in_array($ma, array_map('strval', (array) old('them', [])), true)
        || collect($truong)->contains(fn ($t) => filled(old($t)));
@endphp

<section class="section">
    <div class="container-shop">

        <div class="row g-4 g-lg-5">

            <div class="col-lg-5">
                <span class="text-label d-block mb-2">Sự kiện &amp; số lượng lớn</span>
                <h1 class="text-h1 mb-3 text-balance">Bạn đang chuẩn bị một sự kiện?</h1>
                <p class="mb-4">
                    Đám cưới, khai trương, hội nghị, sinh nhật hay quà tặng doanh nghiệp —
                    để lại thông tin bên dưới, đội ngũ tư vấn sẽ liên hệ để trao đổi chi tiết
                    và gửi báo giá phù hợp.
                </p>

                @if($product)
                    <div class="surface-card p-3 d-flex align-items-center gap-3">
                        @if($product->main_image)
                            <img src="{{ asset('storage/' . $product->main_image) }}" alt="{{ $product->name }}" class="admin-thumb" style="width:56px;height:56px;">
                        @else
                            <span class="admin-thumb admin-thumb--placeholder" style="width:56px;height:56px;"><x-site.leaf-placeholder /></span>
                        @endif
                        <div>
                            <div class="text-caption">Đang hỏi về sản phẩm</div>
                            <div class="fw-semibold">{{ $product->name }}</div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-7">

                <div class="surface-card p-4 p-md-5">

                    <form action="{{ route('shop.bulk-inquiry.store') }}" method="POST">

                        @csrf

                        @if($product)
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                        @endif

                        <div class="mb-4">
                            <label class="text-label d-block mb-2" for="bi-occasion">1. Dịp / sự kiện</label>
                            <input
                                type="text"
                                id="bi-occasion"
                                name="occasion"
                                value="{{ old('occasion') }}"
                                class="form-control @error('occasion') is-invalid @enderror"
                                placeholder="Ví dụ: Tiệc cưới, khai trương, hội nghị..."
                            >
                            <x-form-error name="occasion"/>
                        </div>

                        <div class="mb-3">
                            <span class="text-label d-block mb-1">2. Yêu cầu thêm</span>
                            <p class="text-caption mb-2">
                                Tick những gì bạn cần nêu để hiện ô nhập — không cần điền hết.
                                Điều khác thì ghi ở mục Yêu cầu chi tiết bên dưới.
                            </p>

                            <input type="hidden" name="them_form" value="1">

                            <div class="bulk-them-list" data-yeu-cau-them>

                                <div class="bulk-them">
                                    <label class="bulk-them__toggle">
                                        <input type="checkbox" name="them[]" value="ngay" @checked($daTick('ngay', ['event_date']))>
                                        <span>Ngày cần hoa</span>
                                    </label>
                                    <div class="bulk-them__field">
                                        <input type="date" id="bi-event-date" name="event_date" aria-label="Ngày cần hoa"
                                               value="{{ old('event_date') }}" min="{{ now()->toDateString() }}"
                                               class="form-control @error('event_date') is-invalid @enderror">
                                        <div class="form-text">Hoa tươi số lượng lớn cần báo trước vài ngày để gom đủ hàng.</div>
                                        <x-form-error name="event_date"/>
                                    </div>
                                </div>

                                <div class="bulk-them">
                                    <label class="bulk-them__toggle">
                                        <input type="checkbox" name="them[]" value="dia_diem" @checked($daTick('dia_diem', ['event_location']))>
                                        <span>Nơi giao / địa điểm tổ chức</span>
                                    </label>
                                    <div class="bulk-them__field">
                                        <input type="text" id="bi-location" name="event_location" aria-label="Nơi giao / địa điểm tổ chức"
                                               value="{{ old('event_location') }}"
                                               class="form-control @error('event_location') is-invalid @enderror"
                                               placeholder="Ví dụ: Trung tâm hội nghị ABC, quận X">
                                        <x-form-error name="event_location"/>
                                    </div>
                                </div>

                                <div class="bulk-them">
                                    <label class="bulk-them__toggle">
                                        <input type="checkbox" name="them[]" value="so_luong" @checked($daTick('so_luong', ['quantity_estimate']))>
                                        <span>Số lượng dự kiến</span>
                                    </label>
                                    <div class="bulk-them__field">
                                        <input type="number" id="bi-qty" name="quantity_estimate" min="1" aria-label="Số lượng dự kiến"
                                               value="{{ old('quantity_estimate') }}"
                                               class="form-control @error('quantity_estimate') is-invalid @enderror"
                                               placeholder="Ví dụ: 50">
                                        <x-form-error name="quantity_estimate"/>
                                    </div>
                                </div>

                                <div class="bulk-them">
                                    <label class="bulk-them__toggle">
                                        <input type="checkbox" name="them[]" value="ngan_sach" @checked($daTick('ngan_sach', ['budget_min', 'budget_max']))>
                                        <span>Ngân sách</span>
                                    </label>
                                    <div class="bulk-them__field">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="form-label" for="bi-budget-min">Từ (₫)</label>
                                                <input type="number" id="bi-budget-min" name="budget_min" min="0" step="100000"
                                                       value="{{ old('budget_min') }}"
                                                       class="form-control @error('budget_min') is-invalid @enderror" placeholder="5000000">
                                                <x-form-error name="budget_min"/>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label" for="bi-budget-max">Đến (₫)</label>
                                                <input type="number" id="bi-budget-max" name="budget_max" min="0" step="100000"
                                                       value="{{ old('budget_max') }}"
                                                       class="form-control @error('budget_max') is-invalid @enderror" placeholder="10000000">
                                                <x-form-error name="budget_max"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bulk-them">
                                    <label class="bulk-them__toggle">
                                        <input type="checkbox" name="them[]" value="mau" @checked($daTick('mau', ['color_preference']))>
                                        <span>Tông màu mong muốn</span>
                                    </label>
                                    <div class="bulk-them__field">
                                        <input type="text" id="bi-color" name="color_preference" aria-label="Tông màu mong muốn"
                                               value="{{ old('color_preference') }}"
                                               class="form-control @error('color_preference') is-invalid @enderror"
                                               placeholder="Ví dụ: trắng – xanh pastel">
                                        <x-form-error name="color_preference"/>
                                    </div>
                                </div>

                                <div class="bulk-them">
                                    <label class="bulk-them__toggle">
                                        <input type="checkbox" name="them[]" value="loai_hoa" @checked($daTick('loai_hoa', ['flower_preference']))>
                                        <span>Loại hoa / cây ưa thích</span>
                                    </label>
                                    <div class="bulk-them__field">
                                        <input type="text" id="bi-flower" name="flower_preference" aria-label="Loại hoa / cây ưa thích"
                                               value="{{ old('flower_preference') }}"
                                               class="form-control @error('flower_preference') is-invalid @enderror"
                                               placeholder="Ví dụ: hồng Ecuador, cẩm tú cầu, kim tiền">
                                        <x-form-error name="flower_preference"/>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <hr class="my-4" style="border-color: var(--border-soft);">

                        <span class="text-label d-block mb-2">3. Thông tin liên hệ</span>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="bi-name">Họ tên <span class="text-accent">*</span></label>
                                <input type="text" id="bi-name" name="contact_name" value="{{ old('contact_name') }}" class="form-control @error('contact_name') is-invalid @enderror">
                                <x-form-error name="contact_name"/>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="bi-phone">Số điện thoại <span class="text-accent">*</span></label>
                                <input type="text" id="bi-phone" name="contact_phone" value="{{ old('contact_phone') }}" class="form-control @error('contact_phone') is-invalid @enderror">
                                <x-form-error name="contact_phone"/>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="bi-email">Email</label>
                                <input type="email" id="bi-email" name="contact_email" value="{{ old('contact_email') }}" class="form-control @error('contact_email') is-invalid @enderror">
                                <x-form-error name="contact_email"/>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="bi-company">Công ty / đơn vị</label>
                                <input type="text" id="bi-company" name="company_name" value="{{ old('company_name') }}" class="form-control @error('company_name') is-invalid @enderror" placeholder="Bỏ trống nếu đặt cá nhân">
                                <x-form-error name="company_name"/>
                            </div>

                            <div class="col-12">
                                <span class="form-label d-block mb-2">Bạn muốn được liên hệ lại bằng cách nào?</span>

                                <div class="d-flex flex-wrap gap-3">
                                    @foreach(\App\Enums\ContactChannel::cases() as $channel)
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio"
                                                   id="contact-{{ $channel->value }}"
                                                   name="preferred_contact"
                                                   value="{{ $channel->value }}"
                                                   @checked(old('preferred_contact') === $channel->value)>
                                            <label class="form-check-label" for="contact-{{ $channel->value }}">
                                                {{ $channel->label() }}
                                                <span class="d-block text-caption">{{ $channel->hint() }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="form-text">Không chọn cũng được — cửa hàng sẽ gọi điện.</div>
                                <x-form-error name="preferred_contact"/>
                            </div>
                        </div>


                        <div class="mb-4">
                            <label class="text-label d-block mb-2" for="bi-message">4. Yêu cầu chi tiết</label>
                            <textarea id="bi-message" name="message" rows="4" class="form-control @error('message') is-invalid @enderror" placeholder="Yêu cầu riêng, cách trang trí, hoặc câu hỏi của bạn...">{{ old('message') }}</textarea>
                            <x-form-error name="message"/>
                        </div>

                        <button type="submit" class="btn btn-primary-brand btn-lg w-100">
                            Gửi yêu cầu tư vấn
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>
</section>

@endsection
