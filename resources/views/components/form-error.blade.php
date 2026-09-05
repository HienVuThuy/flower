@props(['name', 'bag' => 'default', 'array' => false])

{{--
    Thông báo lỗi của MỘT ô nhập.

    THAM SỐ `bag` giải quyết chuyện nhiều biểu mẫu trên cùng một trang.
    Trang Hồ sơ có hai biểu mẫu, và CẢ HAI đều có ô tên `current_password`.
    Mặc định Laravel gom lỗi của mọi biểu mẫu vào chung một túi, nên nhập
    sai mật khẩu ở biểu mẫu này thì câu báo lỗi hiện lên ở CẢ HAI — người
    dùng thấy hai dòng đỏ giống hệt nhau ở hai chỗ khác nhau và không biết
    mình sai ở đâu.

    THAM SỐ `array` cho các ô dạng mảng (weekdays[], traits[...][]).
    Laravel gắn lỗi của phần tử vào khoá CON — "weekdays.0", không phải
    "weekdays". Kiểm tra đúng tên gốc thì lỗi tồn tại mà không bao giờ
    hiện ra: biểu mẫu nạp lại trống trơn, dữ liệu không được lưu, và người
    dùng không có một chữ nào giải thích. Đã gặp thật với ô chọn thứ trong
    tuần của chương trình khuyến mại.
--}}
@php
    $errorBag = $errors->getBag($bag);

    $key = $array
        // Khớp cả tên gốc lẫn mọi khoá con "name.0", "name.1"...
        ? collect($errorBag->keys())->first(
            fn (string $k) => $k === $name || str_starts_with($k, $name.'.')
        )
        : ($errorBag->has($name) ? $name : null);
@endphp

@if($key !== null)
    <div class="text-danger small mt-1">
        {{ $errorBag->first($key) }}
    </div>
@endif
