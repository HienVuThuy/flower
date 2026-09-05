@props([
    // Route của chính trang đang đứng — biểu mẫu gửi GET về đây.
    'action',

    // Gợi ý trong ô tìm kiếm. Nói RÕ tìm được theo cái gì, vì mỗi trang
    // tìm theo cột khác nhau và admin không có cách nào đoán.
    'placeholder' => 'Tìm kiếm…',

    // Đặt false ở trang chỉ cần bộ lọc, không cần ô tìm.
    'searchable' => true,

    // Số kết quả, để nói "tìm thấy N" thay vì im lặng.
    'total' => null,
])

@php
    /*
     * Có đang lọc gì không — quyết định việc hiện nút "Xoá lọc".
     *
     * BỎ QUA `page`: đứng ở trang 3 của danh sách không lọc gì cả thì
     * không phải là "đang lọc", và hiện nút xoá ở đó chỉ gây bối rối.
     */
    $active = collect(request()->query())->except('page')->filter(fn ($v) => $v !== '' && $v !== null);
@endphp

{{--
    THANH TÌM KIẾM + LỌC dùng chung cho mọi trang danh sách của quản trị.
    ============================================================
    MỘT COMPONENT, KHÔNG PHẢI TÁM ĐOẠN HTML GIỐNG NHAU.

    Tám trang danh sách đều cần đúng ba thứ: một ô tìm, vài ô lọc, một
    nút xoá lọc. Chép tay tám lần thì tám lần có cơ hội quên
    `withQueryString()` ở phân trang, quên giữ tham số cũ khi lọc tiếp,
    hoặc quên nút xoá — và mỗi trang lệch một kiểu.

    GỬI BẰNG GET, KHÔNG PHẢI POST. Kết quả lọc phải nằm trong URL để
    admin gửi được đường dẫn cho đồng nghiệp, lưu dấu trang, và bấm Back
    ra đúng danh sách vừa xem.

    KHÔNG CẦN JAVASCRIPT. Bấm "Lọc" là gửi biểu mẫu; JS chỉ để gửi tự
    động khi đổi ô chọn — thiếu nó thì vẫn dùng được bình thường.
--}}
<form method="GET" action="{{ $action }}" class="admin-filters" data-admin-filters>

    @if($searchable)
        <div class="admin-filters__search">
            <x-site.icon name="search" class="admin-filters__icon" />
            <input
                type="search"
                name="q"
                value="{{ request('q') }}"
                class="form-control"
                placeholder="{{ $placeholder }}"
                aria-label="{{ $placeholder }}"
            >
        </div>
    @endif

    {{-- Các ô lọc riêng của từng trang. --}}
    {{ $slot }}

    <button type="submit" class="btn btn-secondary-brand">Lọc</button>

    @if($active->isNotEmpty())
        <a href="{{ $action }}" class="btn btn-ghost">Xoá lọc</a>
    @endif

    @if($total !== null)
        {{--
            NÓI SỐ KẾT QUẢ.

            Lọc xong ra một bảng ngắn mà không có con số thì admin không
            biết đó là "chỉ có 3 kết quả" hay "trang này chỉ hiện 3".
        --}}
        <span class="admin-filters__count">
            {{ number_format($total, 0, ',', '.') }} kết quả
        </span>
    @endif

</form>
