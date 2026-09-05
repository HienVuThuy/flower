@props([
    // Đường dẫn trong đĩa `public`, ví dụ "products/hoa-hong.jpg".
    'path',

    'alt' => '',

    /*
     * Ảnh này có nằm trong màn hình đầu tiên không.
     *
     * Ảnh trên đầu trang (hero, ảnh chính của sản phẩm) phải tải NGAY:
     * nó thường là phần tử lớn nhất, tức là thứ quyết định mốc LCP mà
     * trình duyệt dùng để chấm điểm "trang đã dùng được chưa". Đặt lazy
     * cho nó là tự làm chậm chính con số đó.
     *
     * Mọi ảnh còn lại thì ngược lại: lazy để không tranh băng thông với
     * phần khách đang nhìn.
     */
    'eager' => false,

    /*
     * Bề ngang ảnh sẽ chiếm trên màn hình, theo cú pháp `sizes` của HTML.
     *
     * Trình duyệt cần con số này để CHỌN bản trong srcset — nó quyết
     * định trước khi CSS chạy xong, nên không tự suy ra được. Khai sai
     * theo hướng quá lớn thì tải bản nặng vô ích; mặc định lấy mức của
     * thẻ sản phẩm vì đó là chỗ dùng nhiều nhất.
     */
    'sizes' => '(max-width: 575px) 45vw, (max-width: 991px) 30vw, 300px',
])

@php
    $anh = app(\App\Services\Media\ResponsiveImage::class);
    $info = $anh->info($path);
    $srcset = $anh->webpSrcset($path);
    $goc = $path ? \Illuminate\Support\Facades\Storage::url($path) : null;
@endphp

@if($goc)
    {{--
        <picture> CHỨ KHÔNG PHẢI <img srcset> ĐƠN THUẦN.

        Trình duyệt cũ không đọc được WebP; đặt WebP thẳng vào `src` là
        ảnh vỡ, không có gì thay thế. Với <picture>, trình duyệt tự bỏ
        qua <source> nó không hiểu và rơi xuống <img> — ảnh JPG gốc.

        Không cần một dòng JavaScript nào, và cũng không cần hỏi trình
        duyệt là ai.
    --}}
    <picture>
        @if($srcset)
            <source type="image/webp" srcset="{{ $srcset }}" sizes="{{ $sizes }}">
        @endif

        <img
            src="{{ $goc }}"
            alt="{{ $alt }}"

            {{--
                width/height LÀ BẮT BUỘC, kể cả khi CSS đã định cỡ.

                Thiếu chúng, trình duyệt không biết ảnh cao bao nhiêu cho
                tới lúc tải xong — nên nó dựng trang với chiều cao 0 rồi
                ĐẨY MỌI THỨ XUỐNG khi ảnh về. Người đang đọc bị nhảy chữ,
                người đang bấm thì bấm nhầm nút vừa dịch chỗ.

                Hai số này chỉ cần đúng TỶ LỆ; CSS vẫn quyết định kích
                thước thật. Lấy từ manifest nên không phải mở tệp ảnh lúc
                dựng trang — xem ResponsiveImage.
            --}}
            @if($info)
                width="{{ $info['width'] }}"
                height="{{ $info['height'] }}"
            @endif

            @if($eager)
                {{-- Ảnh quyết định LCP: tải ngay và ưu tiên cao. --}}
                loading="eager"
                fetchpriority="high"
            @else
                loading="lazy"
                {{--
                    decoding="async": cho phép trình duyệt giải mã ảnh ở
                    luồng khác thay vì chặn việc vẽ trang. Chỉ có ý nghĩa
                    với ảnh không cấp bách.
                --}}
                decoding="async"
            @endif

            {{ $attributes }}
        >
    </picture>
@endif
