<header class="site-header">

    <div class="container-shop">

        <div class="site-header__bar">

            {{-- Khối chữ có class riêng, KHÔNG còn là <span> trần. --}}
            <a href="{{ route('welcome') }}" class="site-header__brand">
                <x-site.brand :size="28" />
            </a>

            <nav class="site-header__nav">
                <a href="{{ route('welcome') }}" class="site-header__link {{ request()->routeIs('welcome') ? 'is-active' : '' }}">
                    Trang chủ
                </a>
                <a href="{{ route('shop.products.index') }}" class="site-header__link {{ request()->routeIs('shop.products.*') ? 'is-active' : '' }}">
                    Hoa &amp; cây cảnh
                </a>
                <a href="{{ route('shop.categories.index') }}" class="site-header__link {{ request()->routeIs('shop.categories.*') ? 'is-active' : '' }}">
                    Danh mục
                </a>
                @php
                    $inMore = request()->routeIs('shop.supplies.*')
                        || request()->routeIs('shop.bulk-inquiry.*')
                        || request()->routeIs('shop.boarding.*')
                        || request()->routeIs('shop.taxa.*')
                        || request()->routeIs('shop.vouchers.*')
                        || request()->routeIs('shop.community.*')
                        || request()->routeIs('shop.blog.*')
                        || request()->routeIs('shop.advisor.*');
                @endphp

                <details class="site-header__more {{ $inMore ? 'is-active' : '' }}" data-account-menu>

                    <summary class="site-header__link site-header__more-toggle">
                        Khác
                        <x-site.icon name="chevron-down" class="site-header__user-caret" />
                    </summary>

                    <div class="site-header__menu">
                        <a class="dropdown-item" href="{{ route('shop.advisor.index') }}">
                            <x-site.icon name="sliders" /> Chọn cây theo nhu cầu
                        </a>
                        <a class="dropdown-item" href="{{ route('shop.blog.index') }}">
                            <x-site.icon name="list" /> Cẩm nang
                        </a>

                        <a class="dropdown-item" href="{{ route('shop.taxa.index') }}">
                            <x-site.icon name="diagram-3" /> Cây theo loài
                        </a>
                        <a class="dropdown-item" href="{{ route('shop.supplies.index') }}">
                            <x-site.icon name="bag" /> Phụ kiện &amp; vật tư
                        </a>
                        <a class="dropdown-item" href="{{ route('shop.community.index') }}">
                            <x-site.icon name="people" /> Góc cây của bạn
                        </a>
                        <a class="dropdown-item" href="{{ route('shop.vouchers.index') }}">
                            <x-site.icon name="tags" /> Voucher
                        </a>
                        @if(\App\Models\BoardingRate::dangNhan())
                            <a class="dropdown-item" href="{{ route('shop.boarding.index') }}">
                                <x-site.icon name="flower1" /> Chăm cây hộ
                            </a>
                        @endif
                        <a class="dropdown-item" href="{{ route('shop.bulk-inquiry.create') }}">
                            <x-site.icon name="people" /> Sự kiện &amp; số lượng lớn
                        </a>
                    </div>

                </details>
            </nav>

            <div class="site-header__actions">

                <form
                    class="header-search"
                    method="GET"
                    action="{{ route('shop.products.index') }}"
                    role="search"
                    data-search
                >
                    <div class="header-search__field">
                        <x-site.icon name="search" class="header-search__icon" />

                        <input
                            type="search"
                            name="q"
                            class="form-control header-search__input"
                            value="{{ request('q') }}"
                            placeholder="Tìm hoa, cây cảnh..."
                            maxlength="100"
                            autocomplete="off"
                            role="combobox"
                            aria-expanded="false"
                            aria-controls="header-search-results"
                            aria-autocomplete="list"
                            aria-label="Tìm hoa, cây cảnh"
                            data-search-input
                        >

                        <div
                            class="header-search__results"
                            id="header-search-results"
                            role="listbox"
                            aria-label="Gợi ý sản phẩm"
                            hidden
                            data-search-results
                        ></div>
                    </div>
                </form>

                <x-site.scheme-toggle />

                @if(config('features.cart'))
                    @php($cartCount = app(\App\Services\Cart\CartService::class)->count())

                    <a href="{{ route('shop.cart.index') }}" class="btn-icon btn-icon--cart"
                       data-cart-link
                       aria-label="Giỏ hàng{{ $cartCount ? ' (' . $cartCount . ' sản phẩm)' : ' (trống)' }}">
                        <x-site.icon name="bag" />
                        <span class="btn-icon__badge"
                              data-cart-badge
                              @if($cartCount < 1) hidden @endif>{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
                    </a>
                @endif

                @auth
                    @php($soChuaDoc = app(\App\Services\Notification\NotificationCenter::class)->chuaDoc(Auth::user()))
                    <a href="{{ route('shop.notifications.index') }}" class="btn-icon"
                       data-thong-bao-link
                       aria-label="Thông báo{{ $soChuaDoc ? ' (' . $soChuaDoc . ' chưa đọc)' : ' (đã đọc hết)' }}">
                        <x-site.icon :name="$soChuaDoc ? 'bell-fill' : 'bell'" />
                        <span class="btn-icon__badge" data-thong-bao-badge
                              @if($soChuaDoc < 1) hidden @endif>{{ $soChuaDoc > 99 ? '99+' : $soChuaDoc }}</span>
                    </a>
                @endauth

                @guest
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-sm d-none d-md-inline-flex">
                        Đăng nhập
                    </a>
                    <a href="{{ route('register') }}" class="btn btn-primary-brand btn-sm">
                        Đăng ký
                    </a>
                @else
                    <details class="site-header__account" data-account-menu>

                        <summary class="site-header__user">
                            <span class="site-header__avatar">{{ Str::upper(Str::substr(Auth::user()->name, 0, 1)) }}</span>
                            <span class="d-none d-md-inline">{{ Str::limit(Auth::user()->name, 16) }}</span>
                            <x-site.icon name="chevron-down" class="site-header__user-caret" />
                        </summary>

                        <div class="site-header__menu">

                            <div class="site-header__menu-name">
                                {{ Auth::user()->name }}
                                <span>{{ Auth::user()->email }}</span>
                            </div>

                            <div class="dropdown-divider"></div>

                            <a class="dropdown-item" href="{{ route('shop.profile.edit') }}">
                                <x-site.icon name="gear" /> Hồ sơ tài khoản
                            </a>

                            <a class="dropdown-item" href="{{ route('shop.community.profile', Auth::id()) }}">
                                <x-site.icon name="person" /> Trang cá nhân
                            </a>

                            @if(Auth::user()->isCustomer())
                                <a class="dropdown-item" href="{{ route('shop.chat.index') }}">
                                    <x-site.icon name="chat" /> Tin nhắn với cửa hàng
                                </a>
                            @endif

                            <a class="dropdown-item" href="{{ route('shop.profile.edit', ['muc' => 'diem-thuong']) }}" data-diem-header>
                                <x-site.icon name="star" /> Điểm thưởng: <strong>{{ number_format(app(\App\Services\Points\PointLedger::class)->soDu(Auth::user()), 0, ',', '.') }}</strong>
                            </a>

                            <a class="dropdown-item" href="{{ route('shop.profile.edit', ['muc' => 'hang-thanh-vien']) }}" data-hang-header>
                                <x-site.icon name="flower2" /> Hạng: <strong>{{ app(\App\Services\Loyalty\MemberTierResolver::class)->cua(Auth::user())['hang']?->name ?? '—' }}</strong>
                            </a>

                            @if(config('features.cart'))
                                <a class="dropdown-item" href="{{ route('shop.orders.index') }}">
                                    <x-site.icon name="bag" /> Đơn hàng của tôi
                                </a>
                                <a class="dropdown-item" href="{{ route('shop.addresses.index') }}">
                                    <x-site.icon name="geo-alt" /> Sổ địa chỉ
                                </a>
                            @endif

                            @if(config('features.cart'))
                                <a class="dropdown-item" href="{{ route('shop.journals.index') }}">
                                    <x-site.icon name="journal" /> Nhật ký của tôi
                                </a>

                                <a class="dropdown-item" href="{{ route('shop.care.index') }}">
                                    <x-site.icon name="droplet" /> Lịch chăm cây
                                </a>
                            @endif

                            @if(\App\Models\BoardingRate::dangNhan() || \App\Models\BoardingBooking::where('user_id', Auth::id())->exists())
                                <a class="dropdown-item" href="{{ route('shop.boarding.mine') }}">
                                    <x-site.icon name="flower1" /> Cây gửi chăm hộ
                                </a>
                            @endif

                            <a class="dropdown-item" href="{{ route('shop.wishlist.index') }}">
                                <x-site.icon name="heart" /> Sản phẩm yêu thích
                            </a>

                            @if(Auth::user()->isAdmin())
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="{{ route('admin.dashboard') }}">
                                    <x-site.icon name="sliders" /> Trang quản trị
                                </a>
                            @endif

                            <div class="dropdown-divider"></div>

                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <x-site.icon name="box-arrow-right" /> Đăng xuất
                                </button>
                            </form>

                        </div>

                    </details>
                @endguest

                <button
                    type="button"
                    class="btn-icon site-header__toggle"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#mobileDrawer"
                    aria-label="Mở menu"
                >
                    <x-site.icon name="list" />
                </button>

            </div>

        </div>

    </div>

</header>

<div class="offcanvas offcanvas-end mobile-drawer" tabindex="-1" id="mobileDrawer">

    <div class="offcanvas-header">
        <span class="site-header__brand">
            <x-site.brand-mark :size="22" />
            <span>{{ \App\Services\Shop\StoreProfile::name() }}</span>
        </span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng"></button>
    </div>

    <div class="offcanvas-body">
        <a href="{{ route('welcome') }}" class="site-header__link">Trang chủ</a>
        <a href="{{ route('shop.products.index') }}" class="site-header__link">Hoa &amp; cây cảnh</a>
        <a href="{{ route('shop.categories.index') }}" class="site-header__link">Danh mục</a>
        <a href="{{ route('shop.advisor.index') }}" class="site-header__link">Chọn cây</a>

        <div class="mobile-drawer__heading">Tìm hiểu</div>
        <a href="{{ route('shop.blog.index') }}" class="site-header__link">Cẩm nang</a>
        <a href="{{ route('shop.taxa.index') }}" class="site-header__link">Cây theo loài</a>
        <a href="{{ route('shop.community.index') }}" class="site-header__link">Góc cây của bạn</a>

        <div class="mobile-drawer__heading">Khác</div>
        <a href="{{ route('shop.supplies.index') }}" class="site-header__link">Phụ kiện &amp; vật tư</a>
        <a href="{{ route('shop.vouchers.index') }}" class="site-header__link">Voucher</a>
        <a href="{{ route('shop.bulk-inquiry.create') }}" class="site-header__link">Sự kiện &amp; số lượng lớn</a>
    </div>

</div>
