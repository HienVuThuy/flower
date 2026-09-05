@extends('layouts.app')

@php $isEdit = $address->exists; @endphp

@section('title', $isEdit ? 'Sửa địa chỉ' : 'Thêm địa chỉ')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[
            ['label' => 'Sổ địa chỉ', 'url' => route('shop.addresses.index')],
            ['label' => $isEdit ? 'Sửa địa chỉ' : 'Thêm địa chỉ'],
        ]" />

        <h1 class="text-h2 mb-4">{{ $isEdit ? 'Sửa địa chỉ' : 'Thêm địa chỉ' }}</h1>

        <div class="row">
            <div class="col-lg-7">

                <form
                    method="POST"
                    action="{{ $isEdit ? route('shop.addresses.update', $address) : route('shop.addresses.store') }}"
                    class="checkout-panel"
                >
                    @csrf
                    @if($isEdit) @method('PUT') @endif

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label" for="recipient_name">Họ và tên *</label>
                            <input type="text" id="recipient_name" name="recipient_name"
                                   value="{{ old('recipient_name', $address->recipient_name) }}"
                                   class="form-control @error('recipient_name') is-invalid @enderror" required>
                            <x-form-error name="recipient_name" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="recipient_phone">Số điện thoại *</label>
                            <input type="tel" id="recipient_phone" name="recipient_phone"
                                   value="{{ old('recipient_phone', $address->recipient_phone) }}"
                                   class="form-control @error('recipient_phone') is-invalid @enderror"
                                   placeholder="0901234567" required>
                            <x-form-error name="recipient_phone" />
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="recipient_email">Email (không bắt buộc)</label>
                            <input type="email" id="recipient_email" name="recipient_email"
                                   value="{{ old('recipient_email', $address->recipient_email) }}"
                                   class="form-control @error('recipient_email') is-invalid @enderror">
                            <x-form-error name="recipient_email" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="province">Tỉnh/Thành phố *</label>
                            <x-form.province-select
                                name="province"
                                :selected="$address->province ?? ''" />
                            <x-form-error name="province" />
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="district">Quận/Huyện</label>
                            <input type="text" id="district" name="district"
                                   value="{{ old('district', $address->district) }}" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="ward">Phường/Xã</label>
                            <input type="text" id="ward" name="ward"
                                   value="{{ old('ward', $address->ward) }}" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="address_line">Tên đường, toà nhà, số nhà *</label>
                            <input type="text" id="address_line" name="address_line"
                                   value="{{ old('address_line', $address->address_line) }}"
                                   class="form-control @error('address_line') is-invalid @enderror" required>
                            <x-form-error name="address_line" />
                        </div>

                        <div class="col-12">
                            <span class="form-label d-block">Loại địa chỉ</span>

                            @php $labelOld = old('label', $address->label->value ?? 'home'); @endphp

                            <div class="d-flex gap-2">
                                @foreach(\App\Enums\AddressLabel::cases() as $case)
                                    <label class="address-label-option">
                                        <input type="radio" name="label" value="{{ $case->value }}"
                                               class="visually-hidden" @checked($labelOld === $case->value)>
                                        <span>{{ $case->label() }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <x-form-error name="label" />
                        </div>

                        <div class="col-12">
                            {{--
                                Địa chỉ đầu tiên trong sổ luôn thành mặc định
                                dù không tích ô này (xử lý ở controller) — nếu
                                không, bước thanh toán không biết chọn cái nào.
                            --}}
                            <div class="form-check">
                                <input type="checkbox" name="is_default" value="1" id="is_default"
                                       class="form-check-input"
                                       @checked(old('is_default', $address->is_default))
                                       @disabled($address->is_default)>
                                <label class="form-check-label" for="is_default">
                                    Đặt làm địa chỉ mặc định
                                </label>
                            </div>
                        </div>

                    </div>

                    <div class="checkout-panel__actions">
                        <a href="{{ route('shop.addresses.index') }}" class="btn btn-ghost">Huỷ</a>
                        <button type="submit" class="btn btn-primary-brand">
                            {{ $isEdit ? 'Lưu thay đổi' : 'Thêm địa chỉ' }}
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>
</section>

@endsection
