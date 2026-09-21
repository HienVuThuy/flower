<!DOCTYPE html>
{{-- KHU QUẢN TRỊ KHÔNG MANG THEME MÙA VỤ. --}}
@php $cheDo = \App\Services\Shop\DisplayScheme::current(); @endphp
<html lang="vi" data-admin
      data-scheme="{{ $cheDo }}"
      @if($cheDo !== 'auto') data-bs-theme="{{ $cheDo === 'toi' ? 'dark' : 'light' }}" @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Quản trị') - {{ \App\Services\Shop\StoreProfile::name() }}</title>

    <script>document.documentElement.classList.add('has-js');</script>

    <x-site.scheme-boot :bootstrap="true" />

    @vite(['resources/css/admin.css', 'resources/js/app.js'])
</head>

<body>

<x-site.icon-sprite />

<div class="admin-shell d-xl-flex">

    <aside class="admin-sidebar collapse d-xl-block" id="adminNav">

        <div class="admin-brand">
            <x-site.brand :size="24" :show-text="false" />
            <div>
                <div class="admin-brand__title">{{ \App\Services\Shop\StoreProfile::name() }}</div>
                <div class="admin-brand__subtitle">Administration</div>
            </div>
        </div>

    @can('bao-cao')
        <nav class="d-flex flex-column gap-1">
            <a data-admin-link href="{{ route('admin.dashboard') }}" class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
                <x-site.icon name="speedometer2" />
                <span>Tổng quan</span>
            </a>
        </nav>
    @endcan

        @canany(['san-pham', 'kho'])
        <div class="admin-nav-heading">Cửa hàng</div>
        <nav class="d-flex flex-column gap-1">
            @can('san-pham')
            <a data-admin-link href="{{ route('admin.categories.index') }}" class="admin-nav-link {{ request()->routeIs('admin.categories.*') ? 'is-active' : '' }}">
                <x-site.icon name="tags" />
                <span>Danh mục</span>
            </a>
            <a data-admin-link href="{{ route('admin.products.index') }}" class="admin-nav-link {{ request()->routeIs('admin.products.*') ? 'is-active' : '' }}">
                <x-site.icon name="flower1" />
                <span>Sản phẩm</span>
            </a>
            @endcan

            @can('kho')

            <a data-admin-link href="{{ route('admin.inventory.index') }}" class="admin-nav-link {{ request()->routeIs('admin.inventory.*') ? 'is-active' : '' }}">
                <x-site.icon name="box-arrow-right" />
                <span>Tồn kho</span>
            </a>

            <a data-admin-link href="{{ route('admin.stock-receipts.index') }}" class="admin-nav-link {{ request()->routeIs('admin.stock-receipts.*', 'admin.opening-stock.*', 'admin.supplier-returns.*') ? 'is-active' : '' }}">
                <x-site.icon name="plus" />
                <span>Nhập kho</span>
            </a>

            <a data-admin-link href="{{ route('admin.stock-counts.index') }}" class="admin-nav-link {{ request()->routeIs('admin.stock-counts.*') ? 'is-active' : '' }}">
                <x-site.icon name="check-circle" />
                <span>Kiểm kê kho</span>
            </a>

            <a data-admin-link href="{{ route('admin.suppliers.index') }}" class="admin-nav-link {{ request()->routeIs('admin.suppliers.*') ? 'is-active' : '' }}">
                <x-site.icon name="people" />
                <span>Nhà cung cấp</span>
            </a>

            <a data-admin-link href="{{ route('admin.flower-lots.index') }}" class="admin-nav-link {{ request()->routeIs('admin.flower-lots.*', 'admin.flower-kinds.*') ? 'is-active' : '' }}">
                <x-site.icon name="flower1" />
                <span>Lô hoa</span>
            </a>
            @endcan
        </nav>
        @endcanany

        @canany(['don-hang', 'san-pham', 'danh-gia', 'khuyen-mai'])
        <div class="admin-nav-heading">Bán hàng</div>
        <nav class="d-flex flex-column gap-1">
            @can('don-hang')
            <a data-admin-link href="{{ route('admin.bulk-inquiries.index') }}" class="admin-nav-link {{ request()->routeIs('admin.bulk-inquiries.*') ? 'is-active' : '' }}">
                <x-site.icon name="envelope-paper" />
                <span>Yêu cầu số lượng lớn</span>
                @if($pendingInquiryCount ?? 0)
                    <span class="admin-nav-link__count">{{ $pendingInquiryCount }}</span>
                @endif
            </a>
            @if(config('features.cart'))
                <a data-admin-link href="{{ route('admin.orders.index') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.orders.*') ? 'is-active' : '' }}">
                    <x-site.icon name="bag" />
                    <span>Đơn hàng</span>
                </a>
            @else
                <a href="#" class="admin-nav-link is-disabled" aria-disabled="true"
                   title="Bật FEATURE_CART để dùng">
                    <x-site.icon name="bag" />
                    <span>Đơn hàng</span>
                </a>
            @endif

            <a data-admin-link href="{{ route('admin.exchanges.index') }}"
               class="admin-nav-link {{ request()->routeIs('admin.exchanges.*') ? 'is-active' : '' }}">
                <x-site.icon name="arrow-repeat" />
                <span>Đổi hàng</span>
            </a>

            <a data-admin-link href="{{ route('admin.boarding.index') }}"
               class="admin-nav-link {{ request()->routeIs('admin.boarding.*', 'admin.boarding-rates.*', 'admin.boarding-windows.*') ? 'is-active' : '' }}">
                <x-site.icon name="flower1" />
                <span>Chăm cây hộ</span>
            </a>

            @can('tai-chinh')
            <a data-admin-link href="{{ route('admin.refunds.index') }}"
               class="admin-nav-link {{ request()->routeIs('admin.refunds.index') ? 'is-active' : '' }}">
                <x-site.icon name="arrow-counterclockwise" />
                <span>Hoàn tiền</span>
            </a>

            <a data-admin-link href="{{ route('admin.installments.index') }}"
               class="admin-nav-link {{ request()->routeIs('admin.installments.*') ? 'is-active' : '' }}">
                <x-site.icon name="clock-history" />
                <span>Trả góp</span>
            </a>
            @endcan
            @endcan

            @can('san-pham')
            <a data-admin-link href="{{ route('admin.blog.index') }}" class="admin-nav-link {{ request()->routeIs('admin.blog.*', 'admin.blog-categories.*') ? 'is-active' : '' }}">
                <x-site.icon name="list" />
                <span>Cẩm nang</span>
            </a>

            @endcan

            @can('danh-gia')
            <a data-admin-link href="{{ route('admin.community.index') }}" class="admin-nav-link {{ request()->routeIs('admin.community.*') ? 'is-active' : '' }}">
                <x-site.icon name="people" />
                <span>Góc cây của bạn</span>
            </a>
            @endcan

            @can('khuyen-mai')

            <a data-admin-link href="{{ route('admin.promotions.index') }}" class="admin-nav-link {{ request()->routeIs('admin.promotions.*', 'admin.coupons.*', 'admin.member-tiers.*', 'admin.product-gifts.*', 'admin.gift-items.*') ? 'is-active' : '' }}">
                <x-site.icon name="megaphone" />
                <span>Khuyến mại</span>
            </a>

            <a data-admin-link href="{{ route('admin.pricing-advisor.index') }}" class="admin-nav-link {{ request()->routeIs('admin.pricing-advisor.*') ? 'is-active' : '' }}">
                <x-site.icon name="speedometer2" />
                <span>Đề xuất giá</span>
            </a>
            @endcan
        </nav>
        @endcanany

        @canany(['he-thong', 'danh-gia'])
        <div class="admin-nav-heading">Khách hàng</div>
        <nav class="d-flex flex-column gap-1">
            @can('he-thong')
            <a data-admin-link href="{{ route('admin.users.index') }}" class="admin-nav-link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
                <x-site.icon name="people" />
                <span>Người dùng</span>
            </a>
            @endcan
            @can('danh-gia')
            <a data-admin-link href="{{ route('admin.reviews.index') }}" class="admin-nav-link {{ request()->routeIs('admin.reviews.*') ? 'is-active' : '' }}">
                <x-site.icon name="star" />
                <span>Đánh giá</span>
            </a>
            @endcan
        </nav>
        @endcanany

        @canany(['bao-cao', 'tai-chinh', 'he-thong'])
        <div class="admin-nav-heading">Hệ thống</div>
        <nav class="d-flex flex-column gap-1">
            @can('bao-cao')
            <a data-admin-link href="{{ route('admin.analytics.index') }}" class="admin-nav-link {{ request()->routeIs('admin.analytics.*') ? 'is-active' : '' }}">
                <x-site.icon name="bar-chart" />
                <span>Phân tích</span>
            </a>
            @endcan

            @can('tai-chinh')
            <a data-admin-link href="{{ route('admin.expenses.index') }}" class="admin-nav-link {{ request()->routeIs('admin.expenses.*') ? 'is-active' : '' }}">
                <x-site.icon name="journal" />
                <span>Sổ thu chi</span>
            </a>
            @endcan

            @can('he-thong')
            <a data-admin-link href="{{ route('admin.activity-logs.index') }}" class="admin-nav-link {{ request()->routeIs('admin.activity-logs.*') ? 'is-active' : '' }}">
                <x-site.icon name="clock-history" />
                <span>Nhật ký</span>
            </a>
            <a data-admin-link href="{{ route('admin.settings.edit') }}" class="admin-nav-link {{ request()->routeIs('admin.settings.*', 'admin.page-contents.*', 'admin.business-params.*') ? 'is-active' : '' }}">
                <x-site.icon name="gear" />
                <span>Cài đặt</span>
            </a>
            @endcan
        </nav>
        @endcanany

    </aside>

    <a href="#noi-dung" class="skip-link">Bỏ qua tới nội dung</a>

    <main class="admin-main flex-fill" id="noi-dung" tabindex="-1">

        <header class="admin-topbar">
            <div class="d-flex justify-content-between align-items-center gap-2 w-100">

                <div class="d-flex align-items-center gap-2 admin-topbar__trai">
                    <button
                        class="admin-nav-toggle d-xl-none"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#adminNav"
                        aria-controls="adminNav"
                        aria-expanded="false"
                        aria-label="Mở menu quản trị"
                    >
                        <x-site.icon name="list" />
                    </button>

                    <span class="fw-semibold d-xl-none admin-topbar__tieu-de">@yield('title', 'Khu vực quản trị')</span>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('welcome') }}" class="text-muted small text-nowrap" target="_blank">
                        <x-site.icon name="box-arrow-up-right" />
                        <span class="admin-topbar__label">Xem trang chủ</span>
                    </a>

                    <x-site.scheme-toggle />

                    <span class="text-muted small admin-topbar__label">{{ Auth::user()->name }}</span>

                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            Đăng xuất
                        </button>
                    </form>
                </div>

            </div>
        </header>

        <section class="admin-content" data-admin-content>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert" data-auto-dismiss="success">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert" data-auto-dismiss="error">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show" role="alert" data-auto-dismiss="success">
                    {{ session('info') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')

        </section>

    </main>

</div>

@can('ho-tro')
    <x-admin.chat-popup />
@endcan

@stack('scripts')

</body>
</html>
