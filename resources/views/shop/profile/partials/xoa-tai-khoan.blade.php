{{-- ---------- Xoá tài khoản ---------- --}}
<div class="surface-card surface-card--danger p-4 mt-4">

    <h2 class="text-h4 mb-1">Xoá tài khoản</h2>
    <p class="text-caption mb-3">
        Xoá vĩnh viễn hồ sơ, đánh giá, sổ địa chỉ và danh sách yêu thích của bạn.
        Không khôi phục được.
    </p>

    {{--
        NÓI TRƯỚC ĐIỀU DỄ GÂY HIỂU NHẦM NHẤT.

        Người bấm "xoá tài khoản" thường nghĩ mọi dấu vết
        của mình biến mất. Đơn hàng thì không — cửa hàng
        phải giữ làm chứng từ. Biết điều đó SAU khi xoá là
        quá muộn để họ đổi ý, nên phải nói ngay ở đây, chứ
        không đợi tới trang xác nhận.
    --}}
    <p class="text-caption mb-4">
        Đơn hàng đã đặt <strong>vẫn được giữ lại</strong> trong sổ sách của cửa hàng
        làm chứng từ mua bán, nhưng sẽ không còn gắn với tài khoản nào &mdash; xem
        <a href="{{ route('shop.pages.show', 'chinh-sach-bao-mat') }}">Chính sách bảo mật</a>.
    </p>

    {{--
        BƯỚC ĐẦU CHỈ GỬI THƯ, KHÔNG XOÁ GÌ.

        Nút này nằm ngay trên trang hồ sơ, tức là nằm sau
        đúng một phiên đăng nhập — mà phiên thì có thể là
        của người mượn máy. Bắt đi qua hộp thư là bắt
        chứng minh thêm một điều mà người mượn máy không
        có: quyền đọc email của chủ tài khoản.
    --}}
    <form action="{{ route('shop.profile.delete.request') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-outline-danger">
            <x-site.icon name="trash3" />
            Gửi thư xác nhận xoá tài khoản
        </button>
    </form>

    <p class="text-caption mt-2 mb-0">
        Bấm nút này chưa xoá gì. Cửa hàng sẽ gửi một thư tới
        <strong>{{ $user->email }}</strong>; bạn phải mở thư và xác nhận thêm một lần nữa.
    </p>

</div>
