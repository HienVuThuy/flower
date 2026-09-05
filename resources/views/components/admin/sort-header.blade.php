@props([
    // Khoá trên URL, phải khớp danh sách trắng của controller.
    'khoa',

    // Chữ hiện trên đầu cột.
    'nhan',

    /*
     * Hướng cho LẦN BẤM ĐẦU TIÊN.
     *
     * Cột chữ thì A→Z là tự nhiên. Cột số thì ngược lại: bấm "Tồn kho"
     * là muốn xem hàng SẮP HẾT, bấm "Doanh thu" là muốn xem đơn LỚN
     * NHẤT. Bắt bấm hai lần mới ra thứ mình cần là bắt làm thừa một
     * bước, mỗi lần.
     */
    'dau' => 'tang',
])

@php
    $dangSap = request('sap') === $khoa;
    $huongHienTai = request('huong') === 'giam' ? 'giam' : 'tang';

    // Bấm lại vào cột đang sắp thì ĐẢO hướng — đó là điều ai cũng chờ đợi.
    $huongTiepTheo = $dangSap
        ? ($huongHienTai === 'giam' ? 'tang' : 'giam')
        : $dau;

    /*
     * GIỮ NGUYÊN MỌI THAM SỐ KHÁC.
     *
     * Đang lọc "danh mục Hoa, còn hàng" rồi bấm sắp xếp mà mất bộ lọc
     * thì admin phải lọc lại từ đầu. Bỏ `page` vì sắp xếp lại thì trang
     * 3 cũ không còn nghĩa gì.
     */
    $duongDan = request()->fullUrlWithQuery([
        'sap' => $khoa,
        'huong' => $huongTiepTheo,
        'page' => null,
    ]);
@endphp

{{--
    ĐẦU CỘT BẤM ĐƯỢC ĐỂ SẮP XẾP.

    LÀ MỘT THẺ <a>, KHÔNG PHẢI <th onclick>. Nhờ vậy: mở tab mới được,
    copy link được, bàn phím Tab tới được, và trình đọc màn hình đọc ra
    "liên kết". Một ô <th> gắn sự kiện click thì không có gì trong số đó.

    aria-sort là thuộc tính chuẩn của HTML cho đúng việc này — trình đọc
    màn hình sẽ đọc "sắp tăng dần" mà không cần ta tự chế thêm chữ.
--}}
<th @if($dangSap) aria-sort="{{ $huongHienTai === 'giam' ? 'descending' : 'ascending' }}" @endif
    {{ $attributes }}>
    <a href="{{ $duongDan }}" class="admin-sort {{ $dangSap ? 'is-active' : '' }}">
        <span>{{ $nhan }}</span>

        {{--
            Mũi tên CHỈ hiện ở cột đang sắp.

            Hiện mờ ở mọi cột thì hàng tiêu đề đầy ký hiệu và không còn
            nói được cột nào đang có tác dụng — mà đó là thông tin duy
            nhất người dùng cần từ mũi tên này.
        --}}
        @if($dangSap)
            <x-site.icon
                name="chevron-down"
                class="admin-sort__icon {{ $huongHienTai === 'tang' ? 'is-up' : '' }}" />
        @endif
    </a>
</th>
