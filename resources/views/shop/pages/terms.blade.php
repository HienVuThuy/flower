@extends('shop.pages._layout')

@section('page')

<p class="lead">
    Điều khoản áp dụng khi bạn dùng trang này để xem hàng, đặt hàng hoặc
    tạo tài khoản.
</p>

<h2>1. Tài khoản</h2>

<ul>
    <li>
        Bạn <strong>không cần tài khoản để mua hàng</strong>. Tài khoản chỉ
        thêm sổ địa chỉ, lịch sử đơn, ví voucher, danh sách yêu thích và
        lịch nhắc chăm cây.
    </li>
    <li>
        Đăng ký xong phải <strong>xác thực email bằng mã 6 chữ số</strong>
        thì mới dùng được các mục cá nhân. Việc mua hàng thì không bị chặn.
    </li>
    <li>
        Bạn chịu trách nhiệm giữ mật khẩu của mình. Cửa hàng
        <strong>không bao giờ hỏi mật khẩu hay mã xác thực</strong> qua điện
        thoại, email hay tin nhắn.
    </li>
</ul>

<h2>2. Giá và đơn hàng</h2>

<ul>
    <li>
        Giá hiển thị đã gồm thuế, chưa gồm phí giao. Phí giao tính theo tỉnh
        nhận hàng và chốt ở bước thanh toán.
    </li>
    <li>
        Đơn được ghi nhận khi bạn bấm "Đặt hàng", nhưng chỉ
        <strong>có hiệu lực khi cửa hàng xác nhận</strong>. Cửa hàng có
        quyền từ chối đơn nếu hết hàng hoặc thông tin liên hệ không đúng.
    </li>
    <li>
        Giá và khuyến mại có thể thay đổi bất cứ lúc nào. Đơn đã đặt giữ
        nguyên giá tại thời điểm đặt — cửa hàng lưu lại tên và giá sản phẩm
        vào đơn nên thay đổi về sau không ảnh hưởng tới đơn cũ.
    </li>
</ul>

<h2>3. Mã giảm giá</h2>

<ul>
    <li>Mỗi mã có điều kiện riêng, ghi rõ trên thẻ mã.</li>
    <li>
        Mã phải <strong>lưu vào ví</strong> thì hệ thống mới tự chọn giúp
        bạn. Mã in trên tờ rơi hoặc nhận riêng thì nhập tay ở bước thanh
        toán.
    </li>
    <li>Mỗi đơn dùng được một mã.</li>
    <li>
        Cửa hàng có quyền huỷ mã và huỷ đơn nếu phát hiện dùng mã gian lận —
        ví dụ tạo nhiều tài khoản bằng email không có thật để lấy mã dành
        cho khách mới.
    </li>
</ul>

<h2>4. Nội dung bạn đăng</h2>

<ul>
    <li>
        Bạn chỉ đánh giá được sản phẩm <strong>đã mua và đã nhận</strong>,
        mỗi đơn một đánh giá.
    </li>
    <li>
        Cửa hàng có thể <strong>ẩn</strong> đánh giá vi phạm: xúc phạm, quảng
        cáo, nội dung không liên quan tới sản phẩm, hoặc chứa thông tin cá
        nhân của người khác.
    </li>
    <li>
        Cửa hàng <strong>không sửa nội dung đánh giá</strong> và không xoá
        đánh giá chỉ vì nó cho điểm thấp.
    </li>
</ul>

<h2>5. Giới hạn trách nhiệm</h2>

<p>
    Cửa hàng chịu trách nhiệm về hàng hoá theo
    <a href="{{ route('shop.pages.show', 'chinh-sach-doi-tra') }}">chính sách đổi trả</a>.
    Với thiệt hại gián tiếp — ví dụ hoa tới muộn làm lỡ một buổi lễ — mức bồi
    thường tối đa là giá trị của chính đơn hàng đó.
</p>

<div class="static-page__notice">
    <p class="mb-0">
        Đây là bài tập lớn môn học, không phải hợp đồng thương mại.
        Không có giao dịch thật nào được xử lý.
    </p>
</div>

@endsection
