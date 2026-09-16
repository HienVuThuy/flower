@extends('layouts.admin')

@php $isEdit = $coupon->exists; @endphp

@section('title', $isEdit ? 'Sửa mã ' . $coupon->code : 'Thêm mã giảm giá')

@section('content')

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="admin-page-title">{{ $isEdit ? 'Sửa mã ' . $coupon->code : 'Thêm mã giảm giá' }}</h1>
            <p class="admin-page-subtitle">Khách nhập mã này ở bước 2 của thanh toán.</p>
        </div>

        <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-admin">Về danh sách</a>
    </div>

    <form method="POST"
          action="{{ $isEdit ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="row g-3">

            <div class="col-lg-7">

                <div class="admin-panel p-4 mb-3">

                    <h2 class="h6 fw-bold mb-3">Thông tin mã</h2>

                    <div class="row g-3">

                        <div class="col-md-5">
                            <label class="form-label" for="code">Mã *</label>
                            <input type="text" id="code" name="code"
                                   value="{{ old('code', $coupon->code) }}"
                                   class="form-control text-uppercase @error('code') is-invalid @enderror"
                                   maxlength="32" placeholder="NOEL2026" required>
                            <div class="form-text">Chỉ chữ không dấu và số.</div>
                            <x-form-error name="code" />
                        </div>

                        <div class="col-md-7">
                            <label class="form-label" for="name">Tên chương trình *</label>
                            <input type="text" id="name" name="name"
                                   value="{{ old('name', $coupon->name) }}"
                                   class="form-control @error('name') is-invalid @enderror" required>
                            <x-form-error name="name" />
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="description">Mô tả ngắn</label>
                            <input type="text" id="description" name="description"
                                   value="{{ old('description', $coupon->description) }}"
                                   class="form-control" maxlength="255">
                        </div>

                    </div>

                </div>

                <div class="admin-panel p-4">

                    <h2 class="h6 fw-bold mb-3">Mức giảm &amp; điều kiện</h2>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label" for="type">Kiểu giảm giá *</label>
                            @php $typeOld = old('type', $coupon->type->value ?? 'percent'); @endphp
                            <select name="type" id="type" class="form-select @error('type') is-invalid @enderror">
                                @foreach($types as $type)
                                    <option value="{{ $type->value }}" @selected($typeOld === $type->value)>
                                        {{ $type->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-form-error name="type" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="value">Giá trị giảm *</label>
                            <input type="number" id="value" name="value" step="0.01" min="0.01"
                                   value="{{ old('value', $coupon->value) }}"
                                   class="form-control @error('value') is-invalid @enderror" required>
                            <div class="form-text">Phần trăm thì nhập 15; số tiền thì nhập 100000.</div>
                            <x-form-error name="value" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="min_order_amount">Đơn tối thiểu</label>
                            <input type="number" id="min_order_amount" name="min_order_amount" min="0"
                                   value="{{ old('min_order_amount', $coupon->min_order_amount) }}"
                                   class="form-control">
                            <div class="form-text">Bỏ trống nếu không đặt điều kiện.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="max_discount_amount">Giảm tối đa</label>
                            <input type="number" id="max_discount_amount" name="max_discount_amount" min="0"
                                   value="{{ old('max_discount_amount', $coupon->max_discount_amount) }}"
                                   class="form-control">
                            <div class="form-text">Chỉ có tác dụng với kiểu phần trăm.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="usage_limit">Giới hạn lượt dùng</label>
                            <input type="number" id="usage_limit" name="usage_limit" min="1"
                                   value="{{ old('usage_limit', $coupon->usage_limit) }}"
                                   class="form-control">
                            <div class="form-text">
                                Bỏ trống là không giới hạn.
                                @if($isEdit) Đã dùng: <strong>{{ $coupon->used_count }}</strong> lượt. @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="per_user_limit">Giới hạn mỗi tài khoản</label>
                            <input type="number" id="per_user_limit" name="per_user_limit" min="1" max="65535"
                                   value="{{ old('per_user_limit', $coupon->per_user_limit) }}"
                                   class="form-control">
                            <div class="form-text">
                                Khác với ô bên trái: ô kia là tổng lượt của cả chương trình,
                                ô này là số lần <strong>một khách</strong> được dùng.
                                Bỏ trống là không giới hạn riêng.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="promotion_id">Thuộc chương trình</label>
                            @php
                                $promotions = \App\Models\Promotion::orderByDesc('id')->get(['id', 'name']);
                                $pickedPromotion = old('promotion_id', $coupon->promotion_id);
                            @endphp
                            <select id="promotion_id" name="promotion_id" class="form-select">
                                <option value="">— Mã chung, không thuộc sự kiện nào —</option>
                                @foreach($promotions as $promotion)
                                    <option value="{{ $promotion->id }}" @selected((string) $pickedPromotion === (string) $promotion->id)>
                                        {{ $promotion->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Gắn vào chương trình thì mã chỉ hiện ở <strong>trang sự kiện</strong>
                                đó, không hiện ở trang Voucher chung.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Chỉ áp dụng khi thanh toán bằng</label>
                            @php
                                $pickedMethods = (array) old('payment_methods', $coupon->payment_methods ?? []);
                            @endphp

                            <div class="d-flex flex-wrap gap-3 pt-1">
                                @foreach(\App\Enums\PaymentMethod::available() as $method)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               id="pm-{{ $method->value }}"
                                               name="payment_methods[]"
                                               value="{{ $method->value }}"
                                               @checked(in_array($method->value, $pickedMethods, true))>
                                        <label class="form-check-label" for="pm-{{ $method->value }}">
                                            {{ $method->label() }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <div class="form-text">
                                Không tích ô nào = mọi hình thức. Điều kiện này được
                                <strong>kiểm tra thật</strong> lúc đặt hàng.
                            </div>
                            <x-form-error name="payment_methods" :array="true"/>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="min_member_tier_id">Dành cho hạng thành viên</label>
                            @php
                                $cacHangMa = \App\Models\MemberTier::orderBy('min_spend')->get(['id', 'name']);
                                $hangDaChon = (string) old('min_member_tier_id', $coupon->min_member_tier_id);
                            @endphp
                            <select id="min_member_tier_id" name="min_member_tier_id" class="form-select">
                                <option value="">— Mọi khách —</option>
                                @foreach($cacHangMa as $hangMa)
                                    <option value="{{ $hangMa->id }}" @selected($hangDaChon === (string) $hangMa->id)>Từ hạng {{ $hangMa->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Khách dưới hạng này không áp được mã — kể cả khi nhập tay.</div>
                            <x-form-error name="min_member_tier_id" />
                        </div>

                        <div class="col-md-6">
                            <div class="form-check pt-md-4">
                                <input class="form-check-input" type="checkbox"
                                       id="stack_with_member" name="stack_with_member" value="1"
                                       @checked(old('stack_with_member', $coupon->stack_with_member))>
                                <label class="form-check-label" for="stack_with_member">
                                    Cộng dồn với ưu đãi hạng thành viên
                                </label>
                            </div>
                            <div class="form-text">
                                Tắt (mặc định): khách dùng mã này thì không được giảm theo hạng nữa.
                                Bật: giảm theo hạng trước, mã tính trên phần còn lại.
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       id="is_public" name="is_public" value="1"
                                       @checked(old('is_public', $coupon->is_public))>
                                <label class="form-check-label" for="is_public">
                                    Hiện ở trang Voucher để khách tự lưu
                                </label>
                            </div>
                            <div class="form-text">
                                Tắt thì mã vẫn dùng được khi khách <strong>nhập tay</strong>,
                                chỉ là không ai tự tìm thấy. Mã in trên phiếu mua hàng hoặc
                                gửi riêng cho một khách nên để tắt.
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-lg-5">

                <div class="admin-panel p-4 mb-3">

                    <h2 class="h6 fw-bold mb-3">Hiệu lực</h2>

                    <div class="mb-3">
                        <label class="form-label" for="starts_at">Bắt đầu</label>
                        <input type="datetime-local" id="starts_at" name="starts_at"
                               value="{{ old('starts_at', \App\Services\Time\Gio::choO($coupon->starts_at)) }}"
                               class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="ends_at">Kết thúc</label>
                        <input type="datetime-local" id="ends_at" name="ends_at"
                               value="{{ old('ends_at', \App\Services\Time\Gio::choO($coupon->ends_at)) }}"
                               class="form-control @error('ends_at') is-invalid @enderror">
                        <div class="form-text">Bỏ trống cả hai là không giới hạn thời gian.</div>
                        <x-form-error name="ends_at" />
                    </div>

                    <div>
                        <label class="form-label" for="status">Trạng thái *</label>
                        @php $statusOld = old('status', $coupon->status->value ?? 'draft'); @endphp
                        <select name="status" id="status" class="form-select">
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" @selected($statusOld === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Chỉ mã đang bật và trong thời hạn mới dùng được.</div>
                    </div>

                </div>

                <div class="admin-panel p-4">
                    <p class="admin-page-subtitle mb-0">
                        <strong>Mã giảm giá khác Khuyến mại.</strong>
                        Khuyến mại do cửa hàng áp sẵn cho một nhóm sản phẩm, khách
                        không phải làm gì. Mã giảm giá thì khách phải tự nhập, và
                        giảm trên tổng tiền hàng của đơn. Hai thứ có thể cùng áp
                        trên một đơn.
                    </p>
                </div>

            </div>

        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-admin">Huỷ</a>
            <button type="submit" class="btn btn-primary-brand px-4">
                {{ $isEdit ? 'Lưu thay đổi' : 'Tạo mã' }}
            </button>
        </div>

    </form>

@endsection
