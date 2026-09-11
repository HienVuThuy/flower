<!DOCTYPE html>
{{-- data-theme-effect cho JS biết cần nạp động module hiệu ứng nào
     (rỗng = theme không có chuyển động). --}}
{{--
    data-scheme là TRỤC KHÁC HẲN data-theme:
      data-theme  = Tết / Noel / Valentine — CỬA HÀNG chọn cho mọi khách
      data-scheme = sáng / tối             — TỪNG KHÁCH chọn cho riêng mình

    Đọc từ cookie ngay ở máy chủ nên thẻ <html> có sẵn giá trị đúng từ
    khung hình ĐẦU TIÊN. Để JavaScript đặt sau khi tải thì trang luôn vẽ
    ra nền sáng trước rồi mới nháy sang tối — cú nháy đó chói mắt đúng
    vào ban đêm, tức đúng lúc người ta bật chế độ tối.
--}}
<html lang="vi"
      data-theme="{{ $activeTheme }}"
      data-theme-effect="{{ $activeThemeEffect }}"
      data-scheme="{{ \App\Services\Shop\DisplayScheme::current() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Trang chủ') - {{ \App\Services\Shop\StoreProfile::name() }}</title>

    {{--
        MÔ TẢ CHO KẾT QUẢ TÌM KIẾM.

        Trang nào không khai thì lấy câu giới thiệu chung của cửa hàng —
        vẫn hơn để trống, vì không có mô tả thì Google tự cắt một đoạn
        trong trang, và nó cắt tệ hơn.

        @yield chứ không @stack: mỗi trang có ĐÚNG MỘT mô tả, và stack
        cho phép nhiều mảnh chồng lên nhau — thứ sinh ra hai thẻ
        description trên cùng một trang mà không ai để ý.
    --}}
    <meta name="description"
          content="@yield('meta_description', \App\Services\Shop\StoreProfile::name() . ' — ' . \App\Services\Shop\StoreProfile::get('site_tagline'))">

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

    {{-- Đổi "auto" thành sáng/tối trước khung hình đầu tiên — xem component. --}}
    <x-site.scheme-boot />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Tải trước trang khách sắp bấm. Xem ghi chú trong component. --}}
    <x-site.speculation-rules />
</head>

<body>

<x-site.icon-sprite />

<x-site.announcement-bar />
<x-site.header />

<main>

    <div class="container-shop pt-3">

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
            THÔNG BÁO TRUNG TÍNH — không phải thành công, cũng không phải lỗi.

            Dùng cho những việc hệ thống tự làm mà khách cần biết, ví dụ
            "đã huỷ lượt Mua ngay vì bạn quay lại giỏ hàng". Nhét vào
            `success` thì đọc như một lời khen cho việc khách không làm;
            nhét vào `error` thì làm họ hoảng vì tưởng có gì hỏng.
        --}}
        {{--
            MÃ GIẢM GIÁ VỪA BỊ GỠ.

            pull() chứ không phải session(): đọc xong xoá luôn, nên lời
            nhắn hiện ĐÚNG MỘT LẦN. Mã có thể rụng ngay giữa lúc dựng
            trang này, nên không dùng được flash — flash sẽ hiện thêm một
            lần nữa ở trang kế tiếp, làm khách tưởng mã vừa rụng lần hai.

            Đặt ngay trên khối `info` vì cùng loại: việc hệ thống tự làm
            mà khách cần biết. Nhưng dùng màu cảnh báo, không dùng màu
            trung tính — tổng tiền vừa tăng lên, đó là điều khách phải
            thấy chứ không phải điều để lướt qua.
        --}}
        @if(session()->has(\App\Services\Checkout\CheckoutSource::COUPON_NOTICE_KEY))
            {{--
                data-auto-dismiss="warning" — CHỖ NÀY TỪNG BỊ SÓT.

                Ba khối thông báo phía trên đều tự biến mất, riêng khối
                này thì không, nên nó nằm lại trên đầu trang cho tới lần
                tải trang sau. Khách đã đọc, đã hiểu mã bị gỡ, rồi bấm
                sang trang khác và vẫn thấy đúng dòng đó — và bắt đầu tự
                hỏi có phải vừa bị gỡ thêm một mã nữa không.

                Dùng mức "warning" chứ không phải "success": đây là
                chuyện về tiền, người đọc cần thời gian hiểu vì sao tổng
                tiền vừa tăng. Xem TIMEOUT trong resources/js/flash.js.
            --}}
            <div class="alert alert-warning alert-dismissible fade show" role="alert"
                 data-auto-dismiss="warning">
                {{ session()->pull(\App\Services\Checkout\CheckoutSource::COUPON_NOTICE_KEY) }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('info'))
            <div class="alert alert-info alert-dismissible fade show" role="alert" data-auto-dismiss="success">
                {{ session('info') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

    </div>

    @yield('content')

</main>

<x-site.footer />

</body>
</html>
