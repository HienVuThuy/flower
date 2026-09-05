@extends('layouts.app')

@section('title', 'Sổ địa chỉ')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Sổ địa chỉ']]" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">Tài khoản</span>
                <h1 class="text-h2 section-header__title">Sổ địa chỉ</h1>
            </div>

            <a href="{{ route('shop.addresses.create') }}" class="btn btn-primary-brand">
                + Thêm địa chỉ
            </a>
        </div>

        @if($addresses->isEmpty())

            <x-site.empty-state
                title="Chưa có địa chỉ nào"
                text="Lưu sẵn địa chỉ để những lần đặt hàng sau không phải nhập lại."
            >
                <x-slot:actions>
                    <a href="{{ route('shop.addresses.create') }}" class="btn btn-primary-brand">
                        Thêm địa chỉ đầu tiên
                    </a>
                </x-slot:actions>
            </x-site.empty-state>

        @else

            <div class="address-list">
                @foreach($addresses as $address)
                    <div class="address-card {{ $address->is_default ? 'is-default' : '' }}">

                        <div class="address-card__body">

                            <div class="address-card__head">
                                <strong>{{ $address->recipient_name }}</strong>
                                <span class="text-muted">{{ $address->recipient_phone }}</span>
                                <span class="address-option__tag">{{ $address->label->label() }}</span>
                                @if($address->is_default)
                                    <span class="address-option__tag address-option__tag--default">Mặc định</span>
                                @endif
                            </div>

                            <div class="address-card__line">{{ $address->fullAddress() }}</div>

                            @if($address->recipient_email)
                                <div class="address-card__line text-muted">{{ $address->recipient_email }}</div>
                            @endif

                        </div>

                        <div class="address-card__actions">

                            @unless($address->is_default)
                                <form method="POST" action="{{ route('shop.addresses.make-default', $address) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-ghost btn-sm">Đặt mặc định</button>
                                </form>
                            @endunless

                            <a href="{{ route('shop.addresses.edit', $address) }}" class="btn btn-ghost btn-sm">Sửa</a>

                            {{--
                                Xoá là thao tác không lấy lại được nên hỏi
                                xác nhận trước. Không dùng link GET để xoá.
                            --}}
                            <form method="POST" action="{{ route('shop.addresses.destroy', $address) }}"
                                  onsubmit="return confirm('Xoá địa chỉ này khỏi sổ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm text-danger">Xoá</button>
                            </form>

                        </div>

                    </div>
                @endforeach
            </div>

        @endif

    </div>
</section>

@endsection
