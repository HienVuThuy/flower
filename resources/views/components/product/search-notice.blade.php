@props(['terms', 'relaxed' => false, 'total' => null])

{{--
    Nói cho khách biết hệ thống đã làm gì với từ khoá của họ.

    NGUYÊN TẮC: không bao giờ đổi từ khoá sau lưng người dùng.
    Tìm kiếm mờ mà im lặng là kiểu khó chịu nhất — khách gõ "hoaa", thấy
    một trang đầy hoa, và tưởng cửa hàng có đúng thứ tên "hoaa". Đến lúc
    họ gõ đúng thứ cửa hàng KHÔNG có mà vẫn ra hàng thì niềm tin vào ô
    tìm kiếm mất hẳn.

    Nên mỗi lần hệ thống can thiệp đều phải hiện một dòng nói rõ đã can
    thiệp gì. Ba tình huống, loại trừ nhau theo thứ tự từ nặng tới nhẹ.
--}}

@if($terms->isNotEmpty())

    @if($terms->wasCorrected())

        {{--
            Đã tự sửa từ khoá. Chỉ xảy ra khi từ gốc KHÔNG ra sản phẩm
            nào, nên ở đây không có nút "tìm đúng chữ tôi gõ": bấm vào chỉ
            dẫn tới một trang trống mà khách vừa được cứu khỏi.
        --}}
        <div class="search-notice search-notice--corrected">
            <x-site.icon name="search" class="search-notice__icon" />
            <p class="search-notice__text">
                Không có sản phẩm nào tên
                <strong>&laquo;{{ $terms->original }}&raquo;</strong>.
                Đang hiển thị kết quả cho
                <strong>&laquo;{{ $terms->suggestion() }}&raquo;</strong>.
            </p>
        </div>

    @elseif($relaxed)

        {{--
            Không sản phẩm nào chứa đủ mọi từ khoá. Phải nói rõ, vì kết
            quả bên dưới trông như thể khách gõ thiếu từ.
        --}}
        <div class="search-notice search-notice--relaxed">
            <x-site.icon name="search" class="search-notice__icon" />
            @php
                /*
                 * Ghép chuỗi trong PHP thay vì dùng @foreach trong Blade.
                 *
                 * Vòng lặp Blade sẽ nhả ra một dấu cách sau từ cuối cùng
                 * (khoảng trắng giữa các directive đều lọt ra HTML), và
                 * dấu cách đó rơi ngay trước dấu chấm câu — nhìn thấy
                 * được trên màn hình. Dồn vào một biểu thức thì kiểm soát
                 * được từng ký tự.
                 *
                 * e() gọi TAY và bắt buộc: chuỗi này in bằng {!! !!} nên
                 * Blade không tự thoát hộ nữa.
                 */
                $quotedTokens = collect($terms->tokens)
                    ->map(fn (string $token) => '<strong>&laquo;'.e($token).'&raquo;</strong>')
                    ->implode(' và ');
            @endphp

            <p class="search-notice__text">
                Không có sản phẩm nào khớp đồng thời {!! $quotedTokens !!}.
                Dưới đây là những sản phẩm khớp một phần từ khoá.
            </p>
        </div>

    @elseif($terms->alternative)

        {{--
            Từ khoá vẫn ra hàng, chỉ là có một từ gần giống phổ biến hơn
            hẳn. Đây là gợi ý, không phải sửa lỗi — kết quả bên dưới vẫn
            đúng theo chữ khách gõ, và họ tự quyết có đổi hay không.
        --}}
        <div class="search-notice search-notice--hint">
            <x-site.icon name="search" class="search-notice__icon" />
            <p class="search-notice__text">
                @if($total !== null)
                    {{ $total }} kết quả cho
                    <strong>&laquo;{{ $terms->original }}&raquo;</strong>.
                @endif
                Bạn có muốn tìm
                <a href="{{ request()->fullUrlWithQuery(['q' => $terms->alternative, 'page' => null]) }}">&laquo;{{ $terms->alternative }}&raquo;</a>?
            </p>
        </div>

    @endif

@endif
