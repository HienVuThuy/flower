<footer class="site-footer">

    <div class="container-shop">

        <div class="row g-4">

            <div class="col-md-3">
                <div class="site-footer__brand">
                    <x-site.brand-mark :size="22" />
                    <span>{{ \App\Services\Shop\StoreProfile::name() }}</span>
                </div>
                <p>Hoa tươi và cây cảnh cho mọi không gian sống.</p>
            </div>

            <div class="col-6 col-md-4">
                <div class="site-footer__heading">Khám phá</div>
                <p class="mb-1"><a href="{{ route('shop.categories.index') }}">Danh mục</a></p>
                <p class="mb-1"><a href="{{ route('shop.products.index') }}">Sản phẩm</a></p>
                <p class="mb-1"><a href="{{ route('shop.advisor.index') }}">Chọn cây theo nhu cầu</a></p>
                <p class="mb-1"><a href="{{ route('shop.vouchers.index') }}">Voucher</a></p>
                <p class="mb-1"><a href="{{ route('shop.bulk-inquiry.create') }}">Sự kiện &amp; số lượng lớn</a></p>
                <p class="mb-1"><a href="{{ route('shop.orders.lookup') }}">Tra cứu đơn hàng</a></p>
                <p class="mb-0"><a href="{{ route('shop.credits') }}">Nguồn ảnh</a></p>
            </div>

            <div class="col-6 col-md-2">
                <div class="site-footer__heading">Cửa hàng</div>
                @foreach(\App\Http\Controllers\Shop\PageController::all() as $slug => $label)
                    <p class="mb-1">
                        <a href="{{ route('shop.pages.show', $slug) }}">{{ $label }}</a>
                    </p>
                @endforeach
            </div>

            <div class="col-md-3">
                <div class="site-footer__heading">Liên hệ</div>
                @if($hotline = \App\Services\Shop\StoreProfile::hotline())
                    <p class="mb-1">
                        <x-site.icon name="telephone" />
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $hotline) }}">{{ $hotline }}</a>
                    </p>
                @endif
                <p class="mb-1">
                    <x-site.icon name="envelope" />
                    {{ \App\Services\Shop\StoreProfile::email() }}
                </p>
                <p class="mb-2 site-footer__address">
                    <x-site.icon name="geo-alt" />
                    {{ \App\Services\Shop\StoreProfile::address() }}
                </p>
                <a href="{{ route('shop.bulk-inquiry.create') }}" class="btn btn-secondary-brand btn-sm">
                    Đặt số lượng lớn
                </a>
            </div>

        </div>

        <div class="site-footer__bottom">
            &copy; {{ now()->year }} {{ \App\Services\Shop\StoreProfile::name() }}. Đồ án học tập — Phát triển hệ thống thương mại điện tử.
        </div>

    </div>

</footer>
