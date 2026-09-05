@extends('shop.pages._layout')

@section('page')

<p class="lead">
    Trang này nói đúng những gì hệ thống thực sự lưu, lưu ở đâu, và bạn xoá
    được cái gì. Không có mục nào ở đây là điều khoản chung chung.
</p>

<h2>Dữ liệu được lưu</h2>

<table class="static-page__table">
    <thead>
        <tr>
            <th>Loại</th>
            <th>Gồm những gì</th>
            <th>Vì sao cần</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Tài khoản</td>
            <td>Tên, email, mật khẩu đã băm</td>
            <td>Đăng nhập, gửi thư về đơn hàng</td>
        </tr>
        <tr>
            <td>Đơn hàng</td>
            <td>Tên người nhận, số điện thoại, địa chỉ giao, danh sách hàng</td>
            <td>Giao hàng và đối soát khi có khiếu nại</td>
        </tr>
        <tr>
            <td>Sổ địa chỉ</td>
            <td>Địa chỉ bạn tự lưu</td>
            <td>Không phải nhập lại mỗi lần mua</td>
        </tr>
        <tr>
            <td>Giỏ hàng</td>
            <td>Sản phẩm và số lượng</td>
            <td>Giữ giỏ khi bạn quay lại</td>
        </tr>
        <tr>
            <td>Hành vi</td>
            <td>Sản phẩm đã xem, đã thêm vào giỏ, đã mua</td>
            <td>Gợi ý sản phẩm và thống kê của cửa hàng</td>
        </tr>
    </tbody>
</table>

<h2>Mật khẩu</h2>

{{--
    NÓI RÕ MẬT KHẨU ĐƯỢC BĂM, và nói ra hệ quả có ích cho người đọc:
    "chúng tôi không đọc được" là câu duy nhất đáng nói ở đây.
--}}
<p>
    Mật khẩu được <strong>băm một chiều</strong> trước khi lưu.
    Cửa hàng <strong>không đọc được</strong> mật khẩu của bạn, kể cả người
    quản trị. Quên mật khẩu thì đặt lại bằng liên kết gửi qua email, không
    ai gửi lại mật khẩu cũ cho bạn được.
</p>

<p>
    Mã xác thực email 6 chữ số cũng được lưu dạng băm, chỉ sống 15 phút, và
    bị xoá ngay khi dùng xong.
</p>

<h2>Cookie</h2>

<p>Trang này chỉ dùng cookie cần thiết cho việc vận hành:</p>

<ul>
    <li><strong>Phiên làm việc</strong> — giữ đăng nhập và giỏ hàng của khách chưa đăng nhập.</li>
    <li><strong>Chống giả mạo biểu mẫu (CSRF)</strong> — chặn trang khác gửi lệnh thay bạn.</li>
</ul>

<p>
    <strong>Không có cookie quảng cáo, không có mã theo dõi của bên thứ ba.</strong>
    Cửa hàng không bán, không chia sẻ dữ liệu của bạn cho ai.
</p>

<h2>Bạn kiểm soát được gì</h2>

<ul>
    <li><strong>Sửa hồ sơ</strong> — tên, email, mật khẩu, tuỳ chọn nhận thư.</li>
    <li><strong>Tắt thư</strong> — thư báo trạng thái đơn và thư nhắc chăm cây tắt riêng được.</li>
    <li><strong>Xoá địa chỉ</strong> trong sổ địa chỉ bất cứ lúc nào.</li>
    <li><strong>Gỡ đánh giá</strong> của chính mình.</li>
    <li>
        {{--
            Trước đây mục này bảo khách gửi thư tay cho cửa hàng. Nay tự
            làm được ngay trong trang Hồ sơ, nên phải sửa lại — một trang
            chính sách mô tả quy trình đã không còn đúng là một lời hứa
            sai, và người đọc không có cách nào biết.
        --}}
        <strong>Xoá tài khoản</strong> — tự làm trong
        <a href="{{ route('shop.profile.edit') }}">Hồ sơ tài khoản</a>.
        Cửa hàng gửi một thư xác nhận về đúng email của bạn; phải mở thư và
        xác nhận thêm một lần nữa thì tài khoản mới bị xoá.
    </li>
</ul>

<h2>Dữ liệu không xoá theo tài khoản</h2>

<p>
    <strong>Đơn hàng đã hoàn tất được giữ lại</strong> kể cả khi bạn xoá tài
    khoản. Đó là chứng từ mua bán, cửa hàng phải giữ để đối soát và làm sổ
    sách. Đơn giữ lại sẽ không còn gắn với tài khoản nào.
</p>

<h2>Thư điện tử</h2>

<p>Cửa hàng gửi thư trong đúng những trường hợp sau:</p>

<ul>
    <li>Mã xác thực email khi bạn đăng ký</li>
    <li>Xác nhận đơn hàng và khi đơn đổi trạng thái</li>
    <li>Đặt lại mật khẩu, và báo khi mật khẩu vừa được đổi</li>
    <li>Nhắc lịch chăm cây — chỉ khi bạn bật</li>
</ul>

<p><strong>Không có thư quảng cáo.</strong></p>

<div class="static-page__notice">
    <p class="mb-0">
        Đây là bài tập lớn môn học chạy trên máy cá nhân. Đừng nhập dữ liệu
        cá nhân thật mà bạn không muốn để lại trên một hệ thống thử nghiệm.
    </p>
</div>

@endsection
