{{--
    Số liệu trên trang này tính lúc nào, và làm mới nó.
    ============================================================
    VÌ SAO CẦN NÓI RA GIỜ TÍNH.

    Một bảng điều khiển mở từ sáng trông y hệt một bảng vừa tải xong. Số
    liệu đứng im cả buổi mà không có gì cho biết, nên người xem tin rằng
    "hôm nay chưa có đơn nào" trong khi thật ra trang đã cũ ba tiếng.

    Đây là phần "thời gian thực" thật sự: không phải làm mới liên tục, mà
    là LUÔN BIẾT con số mình đang nhìn cũ tới đâu.

    ============================================================
    TỰ LÀM MỚI: MẶC ĐỊNH TẮT.

    Trang tự tải lại giữa lúc người ta đang đọc một bảng, hay đang kéo
    chuột chọn một dòng, là cướp việc của họ. Ai cần (mở trên màn hình
    phụ, xem trong ngày bán chạy) thì bật — và lựa chọn đó được nhớ.
--}}
<div class="tuoi-so-lieu" data-tuoi-so-lieu>

    <span class="tuoi-so-lieu__moc">
        Số liệu lúc
        <x-site.time :at="now()" format="H:i" />
        <span class="tuoi-so-lieu__truoc" data-tuoi-truoc hidden></span>
    </span>

    {{--
        Liên kết thật, không phải nút giả: không có JavaScript vẫn làm mới
        được. Có JavaScript thì đi qua điều hướng nội bộ như mọi liên kết
        quản trị khác — nó vẫn hỏi lại máy chủ, chỉ là không vẽ lại khung.
    --}}
    <a data-admin-link href="{{ request()->fullUrl() }}" class="btn btn-sm btn-outline-admin">Làm mới</a>

    {{--
        Công tắc chỉ hiện khi có JavaScript — nó không làm được gì nếu
        không có. Bày một công tắc bấm vào không có chuyện gì xảy ra còn
        tệ hơn không có công tắc.
    --}}
    <label class="tuoi-so-lieu__tu-dong">
        <input type="checkbox" class="form-check-input" data-tuoi-tu-dong>
        <span>Tự làm mới</span>
    </label>

</div>
