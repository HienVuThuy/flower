@props([
    // Đặt cả `data-bs-theme` để các thành phần Bootstrap (bảng, ô nhập, thông
    // báo) cũng đổi theo. Chỉ bật ở trang quản trị — trang cửa hàng đã có bộ
    // màu tối riêng trong core/dark.css cho từng component của nó.
    'bootstrap' => false,
])

{{--
    ĐỔI "auto" THÀNH SÁNG HOẶC TỐI TRƯỚC KHUNG HÌNH ĐẦU TIÊN.
    ============================================================
    Khi người dùng để "theo hệ thống", máy chủ không biết máy họ đang để sáng
    hay tối — thông tin đó chỉ có ở trình duyệt. Nên máy chủ ghi "auto", và
    đoạn này đổi nó thành "sang" hoặc "toi" ngay tại chỗ.

    ĐẶT INLINE TRONG <head>, chạy đồng bộ TRƯỚC khung hình đầu tiên. Để trong
    app.js thì trang vẽ nền sáng rồi mới nháy sang tối.

    MỘT BẢN cho cả trang cửa hàng lẫn trang quản trị: hai bản chép của cùng
    một đoạn khởi động là hai cách hiểu "auto", và bản bị quên khi sửa sẽ nháy
    sáng ở đúng một nửa hệ thống.

    ĐÁNH ĐỔI, nói rõ: người TẮT JavaScript và để "theo hệ thống" sẽ thấy nền
    sáng dù máy họ đang để tối. Họ vẫn chọn tay được, và lựa chọn tay thì máy
    chủ xử lý hoàn toàn.
--}}
<script>
    (function () {
        var el = document.documentElement;

        if (el.dataset.scheme === 'auto') {
            el.dataset.scheme =
                window.matchMedia &&
                window.matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'toi'
                    : 'sang';

            // Nhớ lại là "auto", để nút chuyển chế độ biết người dùng đang ở
            // trạng thái tự động chứ không phải tự tay chọn.
            el.dataset.schemeAuto = '1';
        }

        @if($bootstrap)
            el.setAttribute('data-bs-theme', el.dataset.scheme === 'toi' ? 'dark' : 'light');
        @endif
    })();
</script>
