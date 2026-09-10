<!DOCTYPE html>
{{--
    KHU QUẢN TRỊ KHÔNG MANG THEME MÙA VỤ.
    Trước đây layout này gắn data-theme của cửa hàng, nên bật Tết là cả
    trang quản trị chuyển đỏ, bật Noel thì chuyển xanh lá. Admin là công
    cụ làm việc: màu sắc phải ổn định để người dùng nhớ được vị trí và
    ý nghĩa (đỏ = nguy hiểm, xanh = thành công), không đổi theo mùa.

    data-admin thay cho data-theme; hiệu ứng mùa vụ cũng không nạp ở đây.
--}}
<html lang="vi" data-admin>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Quản trị') - {{ \App\Services\Shop\StoreProfile::name() }}</title>

    {{--
        ĐÁNH DẤU "TRANG NÀY CÓ JAVASCRIPT".

        CSS dùng `html:not(.has-js)` để ẩn những nút chỉ hoạt động nhờ
        JavaScript (ví dụ nút "Chép" số tài khoản). Bày ra một cái nút mà
        bấm vào không có gì xảy ra còn tệ hơn không có nút.

        ĐẶT INLINE TRONG <head>, chạy TRƯỚC khi trình duyệt vẽ khung hình
        đầu tiên. Để trong app.js thì nút hiện muộn một nhịp và người dùng
        thấy nó nhấp nháy.
    --}}
    <script>document.documentElement.classList.add('has-js');</script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

<x-site.icon-sprite />

<div class="admin-shell d-lg-flex">

    {{--
        id="adminNav" + class collapse: dưới 992px sidebar bị thu lại và
        chỉ mở khi bấm nút trong topbar. Trước đây nó luôn hiện, chiếm
        trọn màn hình đầu tiên nên mỗi lần vào trang phải cuộn qua hết
        menu mới nhìn thấy nội dung.
        d-lg-block ghi đè .collapse ở màn lớn để sidebar luôn hiện.
    --}}
    <aside class="admin-sidebar collapse d-lg-block" id="adminNav">

        <div class="admin-brand">
            <x-site.brand :size="24" :show-text="false" />
            <div>
                <div class="admin-brand__title">{{ \App\Services\Shop\StoreProfile::name() }}</div>
                <div class="admin-brand__subtitle">Administration</div>
            </div>
        </div>

        <nav class="d-flex flex-column gap-1">
            <a data-admin-link href="{{ route('admin.dashboard') }}" class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
                <x-site.icon name="speedometer2" />
                <span>Dashboard</span>
            </a>
        </nav>

        <div class="admin-nav-heading">Cửa hàng</div>
        <nav class="d-flex flex-column gap-1">
            <a data-admin-link href="{{ route('admin.categories.index') }}" class="admin-nav-link {{ request()->routeIs('admin.categories.*') ? 'is-active' : '' }}">
                <x-site.icon name="tags" />
                <span>Danh mục</span>
            </a>
            <a data-admin-link href="{{ route('admin.products.index') }}" class="admin-nav-link {{ request()->routeIs('admin.products.*') ? 'is-active' : '' }}">
                <x-site.icon name="flower1" />
                <span>Sản phẩm</span>
            </a>
        </nav>

        <div class="admin-nav-heading">Bán hàng</div>
        <nav class="d-flex flex-column gap-1">
            <a data-admin-link href="{{ route('admin.bulk-inquiries.index') }}" class="admin-nav-link {{ request()->routeIs('admin.bulk-inquiries.*') ? 'is-active' : '' }}">
                <x-site.icon name="envelope-paper" />
                <span>Yêu cầu số lượng lớn</span>
                @if($pendingInquiryCount ?? 0)
                    <span class="admin-nav-link__count">{{ $pendingInquiryCount }}</span>
                @endif
            </a>
            {{-- Module đơn hàng đã chạy; mục này chỉ tắt khi cờ giỏ hàng tắt. --}}
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
            <a data-admin-link href="{{ route('admin.blog.index') }}" class="admin-nav-link {{ request()->routeIs('admin.blog.*') ? 'is-active' : '' }}">
                <x-site.icon name="list" />
                <span>Cẩm nang</span>
            </a>

            <a data-admin-link href="{{ route('admin.community.index') }}" class="admin-nav-link {{ request()->routeIs('admin.community.*') ? 'is-active' : '' }}">
                <x-site.icon name="people" />
                <span>Góc cây của bạn</span>
            </a>

            <a data-admin-link href="{{ route('admin.promotions.index') }}" class="admin-nav-link {{ request()->routeIs('admin.promotions.*') ? 'is-active' : '' }}">
                <x-site.icon name="megaphone" />
                <span>Khuyến mại</span>
            </a>

            {{--
                ĐỀ XUẤT GIÁ đứng ngay dưới Khuyến mại, không nằm trong
                nhóm "Hệ thống" cùng Phân tích.

                Phân tích trả lời "chuyện gì đã xảy ra"; trang này trả lời
                "nên làm gì tiếp" — và việc làm tiếp đó là tạo một chương
                trình khuyến mại, ngay ở mục bên trên.
            --}}
            <a data-admin-link href="{{ route('admin.pricing-advisor.index') }}" class="admin-nav-link {{ request()->routeIs('admin.pricing-advisor.*') ? 'is-active' : '' }}">
                <x-site.icon name="speedometer2" />
                <span>Đề xuất giá</span>
            </a>

            @if(config('features.cart'))
                <a data-admin-link href="{{ route('admin.coupons.index') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.coupons.*') ? 'is-active' : '' }}">
                    <x-site.icon name="tags" />
                    <span>Mã giảm giá</span>
                </a>
            @endif
        </nav>

        <div class="admin-nav-heading">Khách hàng</div>
        <nav class="d-flex flex-column gap-1">
            <a data-admin-link href="{{ route('admin.users.index') }}" class="admin-nav-link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
                <x-site.icon name="people" />
                <span>Người dùng</span>
            </a>
            <a data-admin-link href="{{ route('admin.reviews.index') }}" class="admin-nav-link {{ request()->routeIs('admin.reviews.*') ? 'is-active' : '' }}">
                <x-site.icon name="star" />
                <span>Đánh giá</span>
            </a>
        </nav>

        <div class="admin-nav-heading">Hệ thống</div>
        <nav class="d-flex flex-column gap-1">
            <a data-admin-link href="{{ route('admin.analytics.index') }}" class="admin-nav-link {{ request()->routeIs('admin.analytics.*') ? 'is-active' : '' }}">
                <x-site.icon name="bar-chart" />
                <span>Phân tích</span>
            </a>
            {{--
                NHẬT KÝ đứng trong nhóm "Hệ thống", ngay trên Cài đặt.

                Không đặt cạnh Đơn hàng hay Sản phẩm: nó không thuộc về
                một loại dữ liệu nào cả, nó nói về NGƯỜI DÙNG HỆ THỐNG.
            --}}
            <a data-admin-link href="{{ route('admin.activity-logs.index') }}" class="admin-nav-link {{ request()->routeIs('admin.activity-logs.*') ? 'is-active' : '' }}">
                <x-site.icon name="clock-history" />
                <span>Nhật ký</span>
            </a>
            <a data-admin-link href="{{ route('admin.settings.edit') }}" class="admin-nav-link {{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}">
                <x-site.icon name="gear" />
                <span>Cài đặt</span>
            </a>
        </nav>

    </aside>

    <main class="admin-main flex-fill">

        <header class="admin-topbar">
            <div class="d-flex justify-content-between align-items-center w-100">

                <div class="d-flex align-items-center gap-2">
                    <button
                        class="admin-nav-toggle d-lg-none"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#adminNav"
                        aria-controls="adminNav"
                        aria-expanded="false"
                        aria-label="Mở menu quản trị"
                    >
                        <x-site.icon name="list" />
                    </button>

                    {{--
                        TÊN TRANG Ở TOPBAR CHỈ HIỆN KHI SIDEBAR BỊ THU LẠI.

                        Mọi trang quản trị đều có sẵn <h1 class="admin-page-title">
                        viết đúng chữ này, nằm ngay bên dưới — nên ở màn
                        rộng, "Sản phẩm" hiện hai lần cách nhau chừng 40px.

                        Dưới 992px thì sidebar thu vào sau nút menu, và
                        lúc đó dòng này là thứ DUY NHẤT cho biết đang ở
                        mục nào trong lúc cuộn — nên giữ. Từ 992px trở
                        lên, mục đang xem đã được tô sáng trong sidebar.
                    --}}
                    <span class="fw-semibold d-lg-none">@yield('title', 'Khu vực quản trị')</span>
                </div>

                <div class="d-flex align-items-center gap-3">
                    {{-- Trên màn hẹp chỉ giữ icon: nhãn chữ làm tràn thanh
                         topbar, đẩy tên người dùng và nút Đăng xuất ra ngoài. --}}
                    <a href="{{ route('welcome') }}" class="text-muted small text-nowrap" target="_blank">
                        <x-site.icon name="box-arrow-up-right" />
                        <span class="admin-topbar__label">Xem trang chủ</span>
                    </a>

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

        {{--
            KHUNG THAY RUỘT.

            `data-admin-content` là chỗ resources/js/admin/nav.js đặt
            HTML mới do máy chủ vẽ, thay vì tải lại cả trang. Không có
            JavaScript thì thuộc tính này chỉ nằm im và mọi thứ chạy như
            cũ — mỗi cú bấm là một lần tải trang đầy đủ.
        --}}
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

            {{--
                THÔNG BÁO TRUNG TÍNH — phải có ở đây y như layout khách.

                Trước đây layout quản trị chỉ dựng `success` và `error`. Một
                controller quản trị nào đó flash `info` thì lời nhắn ĐI ĐÂU
                MẤT — không lỗi, không cảnh báo, chỉ là người quản trị không
                bao giờ biết hệ thống vừa nói gì với mình.
            --}}
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

{{--
    CHỖ CHO SCRIPT RIÊNG CỦA TỪNG TRANG.

    Đặt CUỐI <body>, sau khi mọi phần tử đã có mặt: script chạy trước
    khi HTML dựng xong thì querySelector trả về null, và lỗi đó chỉ hiện
    ra trong console — trang trông như bình thường, chỉ là nút không
    làm gì.

    @stack chứ không @yield: một trang có thể đẩy vào nhiều lần từ nhiều
    component khác nhau, còn @yield chỉ nhận đúng một khối.
--}}
@stack('scripts')

</body>
</html>
