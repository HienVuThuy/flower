@props(['amount', 'symbol' => true])

{{--
    Hiện một số tiền theo cấu hình của cửa hàng.
    ============================================================
    DÙNG COMPONENT NÀY, KHÔNG GỌI number_format() TRỰC TIẾP.

    Trước đây 46 chỗ trong 28 tệp tự định dạng tiền, mỗi chỗ một kiểu —
    ba biến thể chỉ riêng phần ký hiệu (`₫`, `&#8363;`, `đ`), có chỗ có
    dấu cách trước có chỗ không. Hậu quả không phải "trông hơi lệch" mà
    là đơn vị tiền tệ KHÔNG SỬA ĐƯỢC.

    `:symbol="false"` khi ký hiệu đã có sẵn ở chỗ khác trong câu — ví dụ
    một bảng có cột "Đơn vị: ₫" ở tiêu đề.
--}}{{ $symbol
    ? \App\Services\Shop\Money::format($amount)
    : \App\Services\Shop\Money::number($amount) }}
