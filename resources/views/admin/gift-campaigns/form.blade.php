@extends('layouts.admin')

@section('title', $ct->exists ? 'Sửa chương trình quà' : 'Tạo chương trình quà')

@section('content')

<x-admin.promo-tabs />

<div class="mb-4">
    <h1 class="admin-page-title">{{ $ct->exists ? 'Sửa: ' . $ct->name : 'Tạo chương trình quà' }}</h1>
    <p class="admin-page-subtitle mb-0">
        Quà tặng một bộ mỗi đơn khi đơn đạt điều kiện. Muốn tặng mặc định khi mua một sản phẩm cụ thể thì dùng
        <a data-admin-link href="{{ route('admin.product-gifts.index') }}">Quà tặng kèm sản phẩm</a>.
    </p>
</div>

@if($vatPham->isEmpty())
    <div class="alert alert-warning">
        Chưa có vật phẩm quà nào. <a data-admin-link href="{{ route('admin.gift-items.create') }}">Thêm vật phẩm</a> trước.
    </div>
@endif

<form method="POST" action="{{ $ct->exists ? route('admin.gift-campaigns.update', $ct) : route('admin.gift-campaigns.store') }}">
    @csrf
    @if($ct->exists)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="admin-panel p-4">
                <div class="mb-3">
                    <label class="form-label" for="qc-name">Tên chương trình <span aria-hidden="true">*</span></label>
                    <input type="text" id="qc-name" name="name" maxlength="150" required
                           class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $ct->name) }}"
                           placeholder="Quà Trung thu cho đơn từ 500.000đ">
                    <x-form-error name="name" />
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label" for="qc-gift">Quà <span aria-hidden="true">*</span></label>
                        <select id="qc-gift" name="gift_item_id" required class="form-select @error('gift_item_id') is-invalid @enderror">
                            @foreach($vatPham as $vat)
                                <option value="{{ $vat->id }}" @selected((string) old('gift_item_id', $ct->gift_item_id) === (string) $vat->id)>
                                    {{ $vat->name }}{{ $vat->is_active ? '' : ' (đang ngừng)' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text"><a data-admin-link href="{{ route('admin.gift-items.index') }}">Kho vật phẩm quà</a></div>
                        <x-form-error name="gift_item_id" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="qc-gift-qty">Số lượng mỗi đơn</label>
                        <input type="number" id="qc-gift-qty" name="gift_quantity" min="1" max="100" required
                               class="form-control @error('gift_quantity') is-invalid @enderror" value="{{ old('gift_quantity', $ct->gift_quantity) }}">
                        <x-form-error name="gift_quantity" />
                    </div>
                </div>

                <h2 class="h6 fw-bold mt-4 mb-2">Điều kiện</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="qc-min-order">Đơn từ (đồng)</label>
                        <input type="number" id="qc-min-order" name="min_order_amount" min="0" step="1"
                               class="form-control @error('min_order_amount') is-invalid @enderror"
                               value="{{ old('min_order_amount', $ct->min_order_amount !== null ? (int) $ct->min_order_amount : '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="qc-tier">Dành cho hạng</label>
                        <select id="qc-tier" name="min_member_tier_id" class="form-select">
                            <option value="">— Mọi khách —</option>
                            @foreach($cacHang as $h)
                                <option value="{{ $h->id }}" @selected((string) old('min_member_tier_id', $ct->min_member_tier_id) === (string) $h->id)>Từ hạng {{ $h->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="qc-per-user">Mỗi tài khoản nhận tối đa (lần)</label>
                        <input type="number" id="qc-per-user" name="per_user_limit" min="1" max="1000"
                               class="form-control @error('per_user_limit') is-invalid @enderror" value="{{ old('per_user_limit', $ct->per_user_limit) }}">
                        <div class="form-text">Có giới hạn này thì khách phải đăng nhập mới nhận được.</div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <label class="d-flex align-items-center gap-2 mb-2">
                            <input type="checkbox" class="form-check-input" name="first_order_only" value="1"
                                   @checked(old('first_order_only', $ct->first_order_only))>
                            <span>Chỉ đơn đầu tiên của tài khoản</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="admin-panel p-4">
                <div class="mb-3">
                    <label class="form-label" for="qc-total">Tổng số suất quà</label>
                    <input type="number" id="qc-total" name="total_limit" min="1"
                           class="form-control @error('total_limit') is-invalid @enderror" value="{{ old('total_limit', $ct->total_limit) }}">
                    <div class="form-text">
                        Bỏ trống là không giới hạn (vẫn dừng khi quà hết tồn kho).
                        @if($ct->exists) Đã phát: <strong>{{ $ct->used_count }}</strong>. @endif
                    </div>
                    <x-form-error name="total_limit" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="qc-start">Bắt đầu</label>
                    <input type="datetime-local" id="qc-start" name="starts_at" class="form-control"
                           value="{{ old('starts_at', \App\Services\Time\Gio::choO($ct->starts_at)) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="qc-end">Kết thúc</label>
                    <input type="datetime-local" id="qc-end" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror"
                           value="{{ old('ends_at', \App\Services\Time\Gio::choO($ct->ends_at)) }}">
                    <x-form-error name="ends_at" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="qc-status">Trạng thái</label>
                    <select id="qc-status" name="status" class="form-select">
                        @foreach(\App\Enums\PromotionStatus::cases() as $st)
                            <option value="{{ $st->value }}" @selected(old('status', $ct->status?->value) === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-primary-brand w-100">Lưu</button>
            </div>
        </div>
    </div>
</form>

@endsection
