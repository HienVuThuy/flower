@php
    use App\Services\Shop\DisplayScheme;

    $hienTai = DisplayScheme::current();
@endphp

{{-- NÚT CHUYỂN NỀN SÁNG / TỐI --}}
<form method="POST" action="{{ route('shop.display-scheme') }}" class="scheme-toggle" data-scheme-toggle>
    @csrf

    <input type="hidden" name="che_do"
           value="{{ $hienTai === DisplayScheme::TOI ? DisplayScheme::SANG : DisplayScheme::TOI }}"
           data-scheme-value>

    <button type="submit"
            class="btn-icon"
            data-scheme-button
            title="Đổi nền sáng / tối"
            aria-label="Đổi nền sáng / tối">
        <x-site.icon name="brightness-high" class="scheme-toggle__icon scheme-toggle__icon--sang" />
        <x-site.icon name="moon" class="scheme-toggle__icon scheme-toggle__icon--toi" />
    </button>
</form>
