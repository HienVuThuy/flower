{{-- ---------- Xoá tài khoản ---------- --}}
<div class="surface-card surface-card--danger p-4 mt-4">

    <h2 class="text-h4 mb-1">Xoá tài khoản</h2>
    <p class="text-caption mb-3">
        Xoá vĩnh viễn hồ sơ, đánh giá, sổ địa chỉ và danh sách yêu thích của bạn.
        Không khôi phục được.
    </p>

    <p class="text-caption mb-4">
        Đơn hàng đã đặt <strong>vẫn được giữ lại</strong> trong sổ sách của cửa hàng
        làm chứng từ mua bán, nhưng sẽ không còn gắn với tài khoản nào &mdash; xem
        <a href="{{ route('shop.pages.show', 'chinh-sach-bao-mat') }}">Chính sách bảo mật</a>.
    </p>

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
