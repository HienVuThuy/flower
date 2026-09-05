@props(['size' => 28, 'showText' => true])

{{--
    KHỐI NHẬN DIỆN: logo + tên + dòng phụ.
    ============================================================
    MỘT NƠI DUY NHẤT quyết định cửa hàng trông như thế nào. Trước đây
    header, footer, năm trang đăng nhập và layout quản trị mỗi chỗ tự
    ghép <x-site.brand-mark> với tên cửa hàng gõ tay.

    LOGO ADMIN TẢI LÊN THÌ DÙNG, KHÔNG THÌ VẼ HÌNH MẶC ĐỊNH.

    Hình mặc định là SVG theo `currentColor`, nên nó tự đúng màu trên nền
    sáng lẫn nền tối và không bao giờ vỡ nét. Một cửa hàng chưa có logo
    riêng vẫn có nhận diện tử tế thay vì một ô ảnh vỡ — đó là lý do ô
    tải logo ở trang Cấu hình không bắt buộc.
--}}
@php
    $logo = \App\Services\Shop\StoreProfile::logoUrl();
    $ten = \App\Services\Shop\StoreProfile::name();
    $tagline = \App\Services\Shop\StoreProfile::tagline();
@endphp

@if($logo)
    {{--
        alt là TÊN CỬA HÀNG, không phải chữ "logo".
        Trình đọc màn hình đọc "logo" thì người nghe không biết là logo
        của ai; đọc tên cửa hàng thì họ biết mình đang ở đâu.
    --}}
    <img src="{{ $logo }}"
         alt="{{ $ten }}"
         width="{{ $size }}"
         height="{{ $size }}"
         class="site-brand__logo"
         style="width: {{ $size }}px; height: {{ $size }}px; object-fit: contain;">
@else
    <x-site.brand-mark :size="$size" />
@endif

@if($showText)
    <span class="site-header__brand-text">
        <span class="site-header__brand-name">{{ $ten }}</span>
        @if($tagline)
            <span class="site-header__brand-tagline">{{ $tagline }}</span>
        @endif
    </span>
@endif
