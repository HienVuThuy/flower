@php
    use App\Services\Shop\DisplayScheme;

    $hienTai = DisplayScheme::current();
@endphp

{{--
    NÚT CHUYỂN NỀN SÁNG / TỐI
    ============================================================
    LÀ MỘT BIỂU MẪU POST, KHÔNG PHẢI LIÊN KẾT.

    Đặt cookie là thao tác GHI. Với GET thì trình duyệt, trình quét và
    cả phần tải trước (speculation rules) đều được phép tự gọi lại bất
    cứ lúc nào — nghĩa là nền có thể tự đổi mà người dùng không bấm gì.

    CHẠY ĐƯỢC KHI KHÔNG CÓ JAVASCRIPT: bấm là gửi biểu mẫu, trang tải
    lại đúng chỗ cũ với nền mới. Có JavaScript thì đổi ngay tại chỗ rồi
    mới gửi ngầm — xem resources/js/scheme-toggle.js.

    HAI TRẠNG THÁI, KHÔNG PHẢI BA.

    Nút trên thanh header chỉ đảo sáng ↔ tối, vì một cái nút bấm ba lần
    mới quay lại chỗ cũ thì không ai đoán được lần bấm tới sẽ ra gì. Lựa
    chọn "theo hệ thống" nằm ở trang Hồ sơ — nơi người ta vào để chỉnh
    tuỳ chọn, chứ không phải nơi bấm vội một cái giữa lúc đang mua hàng.
--}}
<form method="POST" action="{{ route('shop.display-scheme') }}" class="scheme-toggle" data-scheme-toggle>
    @csrf

    {{--
        Giá trị gửi đi được JavaScript ghi lại theo chế độ ĐANG THẤY.

        Cần vậy vì "auto" là hai thứ khác nhau tuỳ máy: cùng một trang,
        người để máy sáng thì đang xem nền sáng, người để máy tối thì
        đang xem nền tối. Máy chủ không biết họ đang thấy gì, nên không
        đoán được lần bấm này phải chuyển sang đâu.

        Không có JavaScript thì giá trị mặc định dưới đây vẫn đúng cho
        trường hợp thường gặp nhất: người đã tự chọn sáng hoặc tối.
    --}}
    <input type="hidden" name="che_do"
           value="{{ $hienTai === DisplayScheme::TOI ? DisplayScheme::SANG : DisplayScheme::TOI }}"
           data-scheme-value>

    <button type="submit"
            class="btn-icon"
            data-scheme-button
            title="Đổi nền sáng / tối"
            aria-label="Đổi nền sáng / tối">
        {{--
            HAI ICON CÙNG NẰM TRONG DOM, CSS ẩn cái không dùng.

            Đổi icon bằng JavaScript thì ở chế độ không có JS nút sẽ
            hiện sai biểu tượng mãi mãi. Để CSS quyết định theo
            [data-scheme] trên <html> thì icon luôn khớp với nền đang
            hiện, kể cả khi máy chủ dựng sẵn.
        --}}
        <x-site.icon name="brightness-high" class="scheme-toggle__icon scheme-toggle__icon--sang" />
        <x-site.icon name="moon" class="scheme-toggle__icon scheme-toggle__icon--toi" />
    </button>
</form>
