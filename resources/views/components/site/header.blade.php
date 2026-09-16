<header class="site-header">

    <div class="container-shop">

        <div class="site-header__bar">

            {{--
                Khối chữ có class riêng, KHÔNG còn là <span> trần.

                Nó là một flex item, và flex item mặc định KHÔNG co nhỏ
                hơn nội dung của mình (min-width: auto). Không đặt tên
                thì không đặt được `min-width: 0` cho nó, và khi ấy hai
                dòng chữ bên trong tràn ra ngoài khối logo — đo được ở
                1280px: dòng phụ dài quá khối 71px và ĐÈ LÊN chữ "Trang
                chủ" 40px.

                `overflow: hidden` trên chính hai dòng chữ không cứu được:
                chúng chỉ cắt phần vượt quá CHÍNH NÓ, mà chính nó đã được
                cấp thừa chỗ.
            --}}
            <a href="{{ route('welcome') }}" class="site-header__brand">
                <x-site.brand :size="28" />
            </a>

            {{--
                THANH ĐIỀU HƯỚNG — BỐN MỤC PHẲNG + MỘT MENU THẢ XUỐNG.

                Trước đây có năm liên kết ngang hàng, mục cuối dài tới ba
                chữ ("Sự kiện & số lượng lớn"), và nay còn thêm "Phụ kiện
                & vật tư" nữa là sáu. Thanh header không đủ chỗ.

                Gom hai mục ÍT DÙNG NHẤT vào một menu "Khác": phụ kiện là
                hàng phụ trợ, đặt số lượng lớn là nhu cầu của một nhóm nhỏ.
                Bốn mục còn lại là đường đi chính của phần lớn khách nên
                giữ phẳng — giấu chúng sau một lần bấm là làm chậm mọi
                người để tiết kiệm chỗ cho thiểu số.

                DÙNG <details> chứ không dùng dropdown của Bootstrap, cùng
                lý do với menu tài khoản bên dưới: nó mở được khi không có
                JavaScript. Ở đây điều đó còn quan trọng hơn, vì bên trong
                là LỐI ĐI DUY NHẤT tới trang phụ kiện.
            --}}
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
                {{--
                    "CHỌN CÂY" VÀO MENU KHÁC — đổi ngày 07/09/2026.

                    Nó và "Hoa & cây cảnh" từng là hai mục nghe như hai
                    chức năng, trong khi sáu trong bảy tiêu chí của nó
                    trùng với bộ lọc trang sản phẩm. Nay nó là CỬA VÀO có
                    hướng dẫn cho chính trang đó (QĐ-172), nên đứng cạnh
                    trang đó ở thanh chính là mời khách chọn giữa hai lối
                    dẫn tới cùng một nơi.

                    Lối vào vẫn rõ: nút ở khung hero, khối mời ngay đầu
                    trang sản phẩm, menu Khác, và ngăn kéo di động.
                --}}
                {{--
                    CẨM NANG NẰM TRONG MENU "KHÁC" — đổi ngày 07/09/2026.

                    Trước đây nó ở ngoài thanh chính với lý do SEO: khách
                    đến từ Google đọc bài rồi mới biết cửa hàng, nên lối
                    vào đó đáng được thấy ngay.

                    Nhưng đo trên máy thật: ở khung 1280px, sáu mục ngoài
                    thanh chính làm nội dung rộng 1419px — TRÀN NGANG 139px,
                    và cả trang trượt sang hai bên. Một lối vào đẹp trên lý
                    thuyết mà làm hỏng thanh điều hướng ở độ phân giải phổ
                    biến nhất thì không đáng.

                    Vào menu Khác nó vẫn bấm được sau một cú bấm, và khách
                    đến từ Google thì vốn đã ở TRONG bài rồi — họ không cần
                    thanh điều hướng để tìm ra nó.
                --}}
                @php
                    /*
                     * Menu "Khác" tự sáng khi trang hiện tại nằm bên trong —
                     * không có dấu đó thì khách đang ở trang phụ kiện mà
                     * thanh điều hướng trông như chưa chọn gì.
                     */
                    $inMore = request()->routeIs('shop.supplies.*')
                        || request()->routeIs('shop.bulk-inquiry.*')
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
                        {{--
                            Cẩm nang đứng ĐẦU menu: trong sáu mục ở đây, đây
                            là mục nhiều người vào nhất, và nó vừa từ thanh
                            chính chuyển vào — người quen vị trí cũ tìm thấy
                            ngay dòng đầu tiên.
                        --}}
                        <a class="dropdown-item" href="{{ route('shop.advisor.index') }}">
                            <x-site.icon name="sliders" /> Chọn cây theo nhu cầu
                        </a>
                        <a class="dropdown-item" href="{{ route('shop.blog.index') }}">
                            <x-site.icon name="list" /> Cẩm nang
                        </a>

                        {{--
                            "Cây theo loài" đặt trong menu Khác, KHÔNG đặt
                            ngoài thanh chính.

                            Đây là cách tìm của người đã biết cây — số ít
                            trong khách hàng, dù họ mua nhiều và mua đúng.
                            Đưa ra thanh chính là chiếm chỗ của "Danh mục"
                            và "Hoa & cây cảnh", hai lối đi mà phần đông
                            dùng, để đổi lấy một lối đi ít người dùng. Nhưng
                            KHÔNG có lối vào nào cả thì cả cây phân loại
                            thành ra chỉ tồn tại trong cơ sở dữ liệu.
                        --}}
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
                        <a class="dropdown-item" href="{{ route('shop.bulk-inquiry.create') }}">
                            <x-site.icon name="people" /> Sự kiện &amp; số lượng lớn
                        </a>
                    </div>

                </details>
            </nav>

            <div class="site-header__actions">

                {{--
                    Ô TÌM KIẾM — CỐ ĐỊNH, KHÔNG BUNG RA NỮA.

                    Bản trước là một <details> bung ra phủ NGANG CẢ THANH
                    header. Hai vấn đề đo được:
                      - lúc đóng: nút bé nằm lệch bên phải trong khi giữa
                        thanh còn cả một khoảng trống lớn;
                      - lúc mở: ô nhập rộng 1121px cho một dòng chữ ngắn,
                        và nó che sạch logo lẫn menu điều hướng.

                    Nay ô nhập nằm sẵn, rộng cố định 260px (280px trên màn
                    hình rất rộng). Không cần bấm để mở, không che thứ gì,
                    và khoảng trống giữa thanh được lấp đúng chỗ.

                    KHÔNG có nút "Tìm" rời: Enter là đủ, và bỏ nút tiết
                    kiệm ~70px cho chính ô nhập.

                    Dưới 992px ô này ẩn hẳn — thanh header ở khổ đó phải
                    nhường chỗ cho menu, và trang danh sách sản phẩm đã có
                    ô tìm kiếm riêng trong bộ lọc.
                --}}
                <form
                    class="header-search"
                    method="GET"
                    action="{{ route('shop.products.index') }}"
                    role="search"
                    data-search
                >
                    <div class="header-search__field">
                        <x-site.icon name="search" class="header-search__icon" />

                        {{--
                            autocomplete="off" tắt danh sách gợi ý của TRÌNH
                            DUYỆT (những gì đã gõ lần trước), vì nó đè lên
                            đúng chỗ danh sách gợi ý sản phẩm hiện ra.

                            Các thuộc tính aria-* dựng nên mẫu "combobox":
                            trình đọc màn hình cần biết ô này có danh sách
                            kèm theo, đang mở hay đóng, và mục nào đang được
                            chọn.
                        --}}
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

                {{--
                    Nút đổi nền sáng/tối đứng TRƯỚC giỏ hàng.

                    Nhóm bên phải đọc từ trái sang là: tuỳ chọn hiển thị
                    → giỏ hàng → tài khoản. Giỏ và tài khoản là việc mua
                    bán, nút này là tuỳ chọn xem — để nó chen vào giữa
                    hai thứ kia làm đứt mạch.
                --}}
                <x-site.scheme-toggle />

                {{-- Icon giỏ hàng chỉ tồn tại khi module giỏ hàng đang bật --}}
                @if(config('features.cart'))
                    @php($cartCount = app(\App\Services\Cart\CartService::class)->count())

                    {{--
                        data-cart-link / data-cart-badge — chỗ bám cho
                        JavaScript cập nhật số món sau khi thêm vào giỏ mà
                        không tải lại trang. Xem resources/js/add-to-cart.js.

                        Huy hiệu LUÔN CÓ TRONG DOM, chỉ ẩn khi giỏ trống,
                        thay vì @if bỏ hẳn thẻ. JavaScript không phải tự
                        dựng thẻ mới (và tự nhớ đúng tên lớp) cho trường
                        hợp món đầu tiên — chỉ đổi số rồi bỏ thuộc tính
                        hidden.
                    --}}
                    <a href="{{ route('shop.cart.index') }}" class="btn-icon btn-icon--cart"
                       data-cart-link
                       aria-label="Giỏ hàng{{ $cartCount ? ' (' . $cartCount . ' sản phẩm)' : ' (trống)' }}">
                        <x-site.icon name="bag" />
                        <span class="btn-icon__badge"
                              data-cart-badge
                              @if($cartCount < 1) hidden @endif>{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
                    </a>
                @endif

                {{--
                    CHUÔNG THÔNG BÁO — chỉ cho người đã đăng nhập.

                    Số chưa đọc dựng ở MÁY CHỦ: không có JavaScript thì con số vẫn
                    đúng, chỉ là phải tải lại trang mới đổi. Huy hiệu luôn nằm trong
                    DOM và chỉ ẩn khi không còn gì — cùng cách với huy hiệu giỏ hàng.
                --}}
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
                    {{--
                        MỌI THỨ THUỘC VỀ TÀI KHOẢN GOM VÀO MỘT CHỖ.

                        Trước đây "Quản trị", "Đơn hàng", "Sổ địa chỉ",
                        "Yêu thích", tên người dùng và "Đăng xuất" là SÁU
                        phần tử nằm ngang cạnh nhau. Thanh header không đủ
                        chỗ nên phải giấu bớt bằng d-xl-*, mà giấu thì khách
                        không tìm ra, còn hiện thì chữ trong menu chính bị
                        bẻ dòng.

                        Nay tất cả nằm sau một nút duy nhất — thanh header
                        thoáng ra, và không mục nào bị ẩn ở bất kỳ độ rộng
                        màn hình nào nữa.
                    --}}
                    {{--
                        DÙNG <details> CHỨ KHÔNG DÙNG DROPDOWN CỦA BOOTSTRAP.

                        Dropdown Bootstrap cần JavaScript mới mở được. Ở đây
                        cái nằm sau nút không phải hiệu ứng trang trí mà là
                        LỐI ĐI DUY NHẤT tới Đơn hàng, Sổ địa chỉ và Yêu thích
                        — JavaScript hỏng hoặc chưa tải xong là khách mất
                        đường vào, không còn chỗ nào khác để bấm.

                        <details> mở/đóng được bằng chính trình duyệt, có sẵn
                        bàn phím (Enter/Space) và trạng thái đóng-mở cho trình
                        đọc màn hình. JS ở resources/js/account-menu.js chỉ
                        THÊM hai tiện nghi: bấm ra ngoài và bấm Esc thì đóng.
                        Không có JS thì menu vẫn dùng được, chỉ là phải bấm
                        lại vào nút để đóng.
                    --}}
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

                            {{--
                                TRANG CÁ NHÂN Ở GÓC CÂY — khác "Hồ sơ tài khoản":
                                hồ sơ là chỗ sửa tên, mật khẩu, địa chỉ; trang cá nhân
                                là chỗ xem lại bài mình đã đăng và tự quản lý chúng.
                            --}}
                            <a class="dropdown-item" href="{{ route('shop.community.profile', Auth::id()) }}">
                                <x-site.icon name="person" /> Trang cá nhân
                            </a>

                            {{-- Điểm hiện ngay trong menu tài khoản: thấy số tăng mới là thứ kéo người ta quay lại. --}}
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
                                {{--
                                    NHẬT KÝ CÁ NHÂN — đặt ngay trên lịch chăm cây.

                                    Hai thứ này đi cùng nhau: lịch nhắc bảo
                                    "hôm nay tưới cây nào", nhật ký ghi lại
                                    "tưới xong thì cây thế nào".
                                --}}
                                <a class="dropdown-item" href="{{ route('shop.journals.index') }}">
                                    <x-site.icon name="journal" /> Nhật ký của tôi
                                </a>

                                <a class="dropdown-item" href="{{ route('shop.care.index') }}">
                                    <x-site.icon name="droplet" /> Lịch chăm cây
                                </a>
                            @endif

                            {{--
                                "Ví voucher" ĐÃ GỠ khỏi menu tài khoản.

                                Nó trỏ tới đúng trang mà menu "Khác" đã
                                có ("Voucher"), nên hai mục khác tên cùng
                                một đích — người dùng bấm cả hai để xem
                                có gì khác nhau, và không có gì khác nhau.
                                Giữ lại một mục, tên "Voucher", ở chỗ ai
                                cũng vào được kể cả khách chưa đăng nhập.
                            --}}

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

    {{--
        NGĂN KÉO PHẢI CÓ ĐỦ MỌI MỤC CỦA THANH ĐIỀU HƯỚNG.
        ============================================================
        Dưới 1200px thanh điều hướng ẩn hẳn, nên đây là LỐI ĐI DUY NHẤT.
        Thiếu một mục ở đây nghĩa là mục đó không tồn tại với người dùng
        điện thoại — không phải "khó tìm hơn", mà là không có đường nào.

        Bản trước chỉ có bốn liên kết và thiếu sáu: Chọn cây, Cẩm nang,
        Cây theo loài, Phụ kiện, Góc cây, Voucher. "Sản phẩm" thì lệch
        tên với thanh chính (ở đó gọi là "Hoa & cây cảnh") — hai tên cho
        cùng một trang làm người ta tưởng là hai chỗ khác nhau.

        Chia hai nhóm có tiêu đề thay vì một dãy mười liên kết phẳng:
        mười dòng giống hệt nhau thì phải đọc hết mới tìm được dòng cần.
    --}}
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
