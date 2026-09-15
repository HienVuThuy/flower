{{--
    KHUYẾN MẠI — MỘT TRANG TỔNG HỢP, các loại ưu đãi là các tab.

    Giảm giá sản phẩm, mã giảm giá, quà theo chương trình và hạng thành viên
    đều trả lời cùng một câu "cửa hàng đang ưu đãi gì cho khách", nên đứng
    chung một chỗ thay vì bốn mục rời trên thanh bên.
--}}
@php
    $cacTab = collect([
        ['admin.promotions.index', 'admin.promotions.*', 'Giảm giá sản phẩm', true],
        ['admin.coupons.index', 'admin.coupons.*', 'Mã giảm giá', (bool) config('features.cart')],
        ['admin.gift-campaigns.index', 'admin.gift-campaigns.*', 'Quà theo chương trình', true],
        ['admin.member-tiers.index', 'admin.member-tiers.*', 'Hạng thành viên', true],
    ])->filter(fn ($tab) => $tab[3]);
@endphp

<nav class="analytics-tabs mb-4" aria-label="Các loại khuyến mại" data-tab-khuyen-mai>
    @foreach($cacTab as $tab)
        @php
            $dangXem = request()->routeIs($tab[1]);
        @endphp
        <a data-admin-link href="{{ route($tab[0]) }}"
           class="analytics-tabs__tab {{ $dangXem ? 'is-active' : '' }}"
           @if($dangXem) aria-current="page" @endif>{{ $tab[2] }}</a>
    @endforeach
</nav>
