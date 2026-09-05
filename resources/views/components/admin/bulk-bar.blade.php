@props([
    // Route nhận biểu mẫu hàng loạt.
    'action',

    // ['ma-viec' => 'Nhãn hiện cho người dùng']
    'viec',

    // Câu hỏi xác nhận cho những việc không lùi lại được.
    // Chuỗi {so} được thay bằng số dòng đang chọn.
    'canhBao' => [],

    // id của form — các ô tích trong bảng trỏ về đây.
    'id' => 'bulk-form',
])

{{--
    THANH THAO TÁC HÀNG LOẠT.
    ============================================================
    KHÔNG BỌC BẢNG TRONG FORM NÀY, và đó là điểm mấu chốt.

    Cách hiển nhiên là cho cả bảng vào trong <form>. Nhưng mỗi dòng đã
    có sẵn một <form> riêng cho nút Xoá, và HTML CẤM form lồng form —
    trình duyệt sẽ tự đóng thẻ ngoài ở chỗ nó gặp thẻ trong, làm hỏng
    cả hai. Không có lỗi nào hiện ra; chỉ là các nút thôi hoạt động.

    Thay vào đó dùng thuộc tính `form="..."` của HTML: một ô tích nằm ở
    bất kỳ đâu trên trang vẫn thuộc về biểu mẫu có id tương ứng. Không
    lồng nhau, không cần JavaScript gom dữ liệu, và trình duyệt tự gửi
    đúng những ô đang tích.

    CHẠY ĐƯỢC KHI KHÔNG CÓ JAVASCRIPT: tích tay từng ô rồi bấm nút là
    xong. JS chỉ thêm ô "chọn tất cả", bộ đếm, và lời hỏi lại.
--}}
<form method="POST" action="{{ $action }}" id="{{ $id }}" data-bulk-form>
    @csrf

    {{--
        LUÔN HIỆN, KHÔNG ẨN RỒI CHỜ JAVASCRIPT GỠ RA.

        Bản đầu tôi để `hidden` và định cho JS gỡ khi có dòng được chọn.
        Sai hai lần:

        1. Không có JavaScript thì KHÔNG GÌ gỡ nó ra, và cả tính năng
           biến mất — đúng thứ mà cách làm này lẽ ra phải tránh.
        2. Kể cả có JavaScript: ô tích thuộc về form qua thuộc tính
           form="...", nên chúng KHÔNG phải con cháu của thẻ <form>
           trong cây DOM. Sự kiện `change` lan theo cây DOM, nên nó
           không bao giờ tới được <form> — listener gắn ở đó không chạy.

        Luôn hiện còn có cái lợi: người dùng NHÌN THẤY là có thao tác
        hàng loạt. Ẩn cho tới khi tích một ô thì phải đoán ra trước rồi
        mới thấy — mà không ai đoán một tính năng mình chưa biết là có.
    --}}
    <div class="admin-bulk" data-bulk-bar>

        {{--
            SỐ DÒNG ĐANG CHỌN, hiện trước mọi thứ khác.

            Người dùng vừa tích mười lăm ô rải rác qua hai lần cuộn
            trang; con số này là thứ duy nhất xác nhận họ tích đúng bằng
            ấy. Thiếu nó thì trước khi bấm một việc như "Xoá", họ phải
            cuộn ngược lên đếm lại.
        --}}
        {{--
            Câu mặc định là lời HƯỚNG DẪN, không phải "Đã chọn 0 dòng" —
            con số 0 nói đúng nhưng không nói phải làm gì tiếp. JavaScript
            thay nó bằng bộ đếm ngay khi có dòng được chọn.
        --}}
        <span class="admin-bulk__count" data-bulk-count>
            Tích chọn các dòng rồi chọn thao tác
        </span>

        <select name="viec" class="form-select form-select-sm w-auto" required
                aria-label="Chọn thao tác">
            <option value="">— Chọn thao tác —</option>
            @foreach($viec as $ma => $nhan)
                <option value="{{ $ma }}"
                        @isset($canhBao[$ma]) data-canh-bao="{{ $canhBao[$ma] }}" @endisset>
                    {{ $nhan }}
                </option>
            @endforeach
        </select>

        <button type="submit" class="btn btn-sm btn-primary-brand">Thực hiện</button>

        <button type="button" class="btn btn-sm btn-ghost" data-bulk-clear>Bỏ chọn</button>

    </div>
</form>
