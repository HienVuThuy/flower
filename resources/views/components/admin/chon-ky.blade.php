@props(['ky', 'periods', 'route'])

{{--
    Ô chọn kỳ: mấy mốc dựng sẵn, cộng một khoảng ngày tự chọn.
    ============================================================
    MỘT BẢN dùng chung cho trang Tổng quan và cả năm trang con Phân tích.
    Mỗi trang tự dựng thì chỉ cần một trang quên mang theo `tu`/`den` là
    bấm sang tab khác lặng lẽ nhảy về "30 ngày qua" trong khi tiêu đề vẫn
    ghi khoảng cũ.

    ============================================================
    BIỂU MẪU GET, KHÔNG PHẢI POST.

    Chọn kỳ là một thao tác ĐỌC. Dùng GET thì địa chỉ kết quả chép và lưu
    dấu trang được — "doanh thu tháng 8" thành một liên kết gửi cho kế
    toán, và bấm Quay lại của trình duyệt hoạt động đúng.

    ============================================================
    KHÔNG CÓ JAVASCRIPT VẪN DÙNG ĐƯỢC.

    Hai ô ngày và một nút Xem — không phụ thuộc script nào. Nút "Xem" chỉ
    ẩn đi khi có JS (xem CSS `.has-js`), lúc đó đổi ngày là tự gửi.
--}}
<div class="chon-ky">

    <div class="chon-ky__moc">
        @foreach($periods as $value => $label)
            <a data-admin-link href="{{ route($route, ['ky' => $value]) }}"
               class="btn btn-sm {{ ! $ky->laTuyChon() && $ky->ma === (string) $value ? 'btn-primary-brand' : 'btn-outline-admin' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{--
        DẤU HIỆU "ĐANG CHỌN" NẰM TRÊN CẢ NHÓM, không phải trên nút Xem.

        Nút Xem bị ẩn khi trang có JavaScript. Đặt dấu hiệu ở đó thì lúc
        đang xem một khoảng tự chọn, ba mốc dựng sẵn đều nhạt và không có
        gì sáng lên — người xem không biết mình đang ở đâu.
    --}}
    <form method="GET" action="{{ route($route) }}" data-chon-ky
          @class(['chon-ky__khoang', 'is-active' => $ky->laTuyChon()])>
        <input type="hidden" name="ky" value="{{ \App\Services\Analytics\ChonKy::TUY_CHON }}">

        <label class="chon-ky__nhan" for="ky-tu">Từ</label>
        <input type="date" id="ky-tu" name="tu" class="form-control form-control-sm"
               value="{{ $ky->oTu() }}"
               max="{{ \App\Services\Time\Gio::choONgay(now()) }}"
               data-chon-ky-o>

        <label class="chon-ky__nhan" for="ky-den">đến</label>
        <input type="date" id="ky-den" name="den" class="form-control form-control-sm"
               value="{{ $ky->oDen() }}"
               max="{{ \App\Services\Time\Gio::choONgay(now()) }}"
               data-chon-ky-o>

        <button type="submit" class="btn btn-sm {{ $ky->laTuyChon() ? 'btn-primary-brand' : 'btn-outline-admin' }} chon-ky__xem">
            Xem
        </button>
    </form>

</div>
