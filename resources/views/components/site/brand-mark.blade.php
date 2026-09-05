@props(['size' => 24])

{{--
    DẤU HIỆU THƯƠNG HIỆU — mầm cây trong khung tròn.
    ============================================================
    VÌ SAO VẼ LẠI: bản trước là ba nét mảnh 2px rời nhau trong khung
    48×48. Ở cỡ thật trên thanh header (28px), ba nét đó co lại còn hơn
    1px mỗi nét và cách nhau vài pixel — mắt không ghép chúng thành một
    hình, nên logo trông như mấy vệt xước hơn là một biểu tượng.

    Ba thay đổi, mỗi cái chữa một vấn đề cụ thể:

    1. THÊM KHUNG TRÒN. Một hình khép kín có đường bao rõ ràng thì nhận
       ra được ở cỡ nhỏ, còn một cụm nét rời thì không. Khung tròn cũng
       cho logo một chỗ đứng ổn định cạnh chữ, thay vì lơ lửng.

    2. LÁ TÔ ĐẶC, KHÔNG VIỀN. Mảng đặc giữ được hình ở mọi cỡ; nét viền
       mảnh thì mất khi thu nhỏ. Hai lá lệch nhau về kích thước để hình
       có nhịp, không đối xứng cứng.

    3. THÂN CÂY CONG NHẸ. Đường thẳng đứng trông như một cái gạch; nét
       cong đọc ra là "đang lớn" — đúng thứ một cửa hàng cây cảnh muốn
       nói.

    currentColor cho MỌI nét: logo tự đổi theo màu chữ xung quanh, nên
    dùng được trên nền sáng lẫn nền tối mà không cần bản thứ hai.
--}}
<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 48 48"
    fill="none"
    width="{{ $size }}"
    height="{{ $size }}"
    {{ $attributes }}
>
    {{--
        Khung tròn mảnh hơn phần bên trong: nó là cái nền, không được
        tranh chú ý với mầm cây.
    --}}
    <circle cx="24" cy="24" r="21" stroke="currentColor" stroke-width="2.5" opacity="0.35"/>

    {{-- Thân: cong nhẹ sang trái rồi vươn thẳng lên. --}}
    <path
        d="M24 37C24 30 22.5 25 20 21"
        stroke="currentColor"
        stroke-width="2.6"
        stroke-linecap="round"
    />

    {{--
        Lá lớn bên phải — mảng đặc, đầu nhọn hướng lên.

        Đặt bên phải và cao hơn để mắt đọc hình từ dưới trái lên trên
        phải, đúng hướng đọc tự nhiên.
    --}}
    <path
        d="M24 26C24 26 25 14 35 11C35 11 36 23 24 26Z"
        fill="currentColor"
    />

    {{-- Lá nhỏ bên trái, thấp hơn — tạo nhịp thay vì đối xứng cứng. --}}
    <path
        d="M22 30C22 30 20 22 13 21C13 21 13 30 22 30Z"
        fill="currentColor"
        opacity="0.75"
    />
</svg>
