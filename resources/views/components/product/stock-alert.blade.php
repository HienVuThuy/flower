@props(['product'])

{{-- Hết hàng thì mời khách để lại lời nhắn "báo tôi khi có hàng" thay vì để họ đi luôn. --}}
@php
    $dichVu = app(\App\Services\Catalog\StockAlertService::class);
    $moi = $dichVu->dangKyDuoc($product);
    $nguoi = auth()->user();
    $daDangKy = $moi && $nguoi && $dichVu->daDangKy($nguoi, $product);
@endphp

@if($moi)
    <div class="stock-alert" data-bao-hang-ve>
        @if($daDangKy)
            <p class="stock-alert__text">
                <x-site.icon name="check-circle" />
                Cửa hàng sẽ báo bạn ngay khi <strong>{{ $product->name }}</strong> có hàng lại.
            </p>

            <form method="POST" action="{{ route('shop.stock-alerts.destroy', $product) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm">Bỏ nhận báo</button>
            </form>
        @elseif($nguoi && $nguoi->hasVerifiedEmail())
            <p class="stock-alert__text">
                Hết hàng rồi, nhưng hàng theo mùa thường về lại sau vài ngày.
            </p>

            <form method="POST" action="{{ route('shop.stock-alerts.store', $product) }}">
                @csrf
                <button type="submit" class="btn btn-secondary-brand btn-sm">Báo tôi khi có hàng</button>
            </form>
        @else
            <p class="stock-alert__text">
                Hết hàng rồi. <a href="{{ route('login') }}">Đăng nhập</a> để cửa hàng báo bạn khi hàng về.
            </p>
        @endif
    </div>
@endif
