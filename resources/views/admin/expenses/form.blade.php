@extends('layouts.admin')

@section('title', $khoan->exists ? 'Sửa khoản chi' : 'Ghi khoản chi')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">{{ $khoan->exists ? 'Sửa: ' . $khoan->description : 'Ghi khoản chi' }}</h1>
    <p class="admin-page-subtitle mb-0">
        Chỉ những khoản hệ thống chưa có số. Tiền nhập hàng để bán, cước GHN, tiền hoàn cho khách đã tự có — ghi lại là trừ hai lần.
    </p>
</div>

<form method="POST"
      action="{{ $khoan->exists ? route('admin.expenses.update', $khoan) : route('admin.expenses.store') }}">
    @csrf
    @if($khoan->exists)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="admin-panel p-4">

                <div class="mb-3">
                    <label class="form-label" for="cp-description">Nội dung <span aria-hidden="true">*</span></label>
                    <input type="text" id="cp-description" name="description" maxlength="200" required
                           class="form-control @error('description') is-invalid @enderror"
                           value="{{ old('description', $khoan->description) }}"
                           placeholder="Ví dụ: tiền điện tháng 9">
                    <x-form-error name="description" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="cp-category">Loại chi phí</label>
                    <select id="cp-category" name="category" class="form-select @error('category') is-invalid @enderror">
                        @foreach(\App\Enums\ExpenseCategory::cases() as $loai)
                            <option value="{{ $loai->value }}" @selected(old('category', $khoan->category?->value) === $loai->value)>
                                {{ $loai->label() }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="category" />

                    <ul class="form-text mb-0 ps-3">
                        @foreach(\App\Enums\ExpenseCategory::cases() as $loai)
                            @if($loai->hint() !== '')
                                <li><strong>{{ $loai->label() }}</strong> — {{ $loai->hint() }}</li>
                            @endif
                        @endforeach
                    </ul>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="cp-note">Ghi chú</label>
                    <textarea id="cp-note" name="note" rows="3" maxlength="2000"
                              class="form-control @error('note') is-invalid @enderror">{{ old('note', $khoan->note) }}</textarea>
                    <x-form-error name="note" />
                </div>

            </div>
        </div>

        <div class="col-lg-5">
            <div class="admin-panel p-4">

                <div class="mb-3">
                    <label class="form-label" for="cp-amount">Số tiền (đồng) <span aria-hidden="true">*</span></label>
                    <input type="number" id="cp-amount" name="amount" min="1" step="1" required inputmode="numeric"
                           class="form-control @error('amount') is-invalid @enderror"
                           value="{{ old('amount', $khoan->amount !== null ? (int) $khoan->amount : '') }}">
                    <x-form-error name="amount" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="cp-spent-on">Ngày chi <span aria-hidden="true">*</span></label>
                    <input type="date" id="cp-spent-on" name="spent_on" required
                           max="{{ now(\App\Services\Time\Gio::mui())->endOfMonth()->toDateString() }}"
                           class="form-control @error('spent_on') is-invalid @enderror"
                           value="{{ old('spent_on', $khoan->spent_on?->toDateString()) }}">
                    <x-form-error name="spent_on" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="cp-payment">Cách trả</label>
                    <select id="cp-payment" name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                        <option value="">Không ghi</option>
                        @foreach(\App\Http\Controllers\Admin\ExpenseController::PHUONG_THUC as $ma => $nhan)
                            <option value="{{ $ma }}" @selected(old('payment_method', $khoan->payment_method) === $ma)>{{ $nhan }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="payment_method" />
                </div>

                <label class="d-flex align-items-start gap-2 mb-3">
                    <input type="checkbox" class="form-check-input mt-1" name="is_fixed" value="1"
                           @checked(old('is_fixed', $khoan->is_fixed))>
                    <span>
                        Khoản cố định hằng tháng
                        <span class="d-block admin-page-subtitle small">Lương, mặt bằng, server. Tháng sau chép sang bằng một nút rồi sửa số nếu khác.</span>
                    </span>
                </label>

                <button type="submit" class="btn btn-primary-brand w-100">Lưu</button>
            </div>
        </div>
    </div>
</form>

@endsection
