@extends('shop.pages._layout')

@section('page')

<p class="lead">
    Hoa tươi và cây sống không đổi trả giống hàng công nghiệp được — nhưng
    điều đó không có nghĩa là bạn phải chịu khi nhận hàng hỏng. Dưới đây là
    ranh giới rõ ràng giữa hai chuyện đó.
</p>

<h2>Nhận hàng: kiểm trước khi trả tiền</h2>

<p>
    Với đơn COD, bạn có quyền <strong>mở gói kiểm tra trước khi thanh toán</strong>.
    Nếu hàng sai mẫu, sai loại, dập nát hoặc thiếu món, hãy từ chối nhận và
    gọi hotline ngay lúc đó — đừng nhận rồi mới báo.
</p>

<h2>Được đổi hoặc hoàn tiền</h2>

<ul>
    <li><strong>Giao sai sản phẩm</strong> so với đơn đã đặt.</li>
    <li><strong>Thiếu món</strong> trong đơn nhiều sản phẩm.</li>
    <li><strong>Hoa dập, héo, gãy cành</strong> ngay khi nhận.</li>
    <li><strong>Cây gãy thân, vỡ chậu, rụng hết lá</strong> khi mở gói.</li>
    <li><strong>Sai ngày giao</strong> mà cửa hàng không báo trước.</li>
</ul>

<p>
    Trong các trường hợp trên, cửa hàng <strong>đổi hàng mới hoặc hoàn tiền
    đầy đủ</strong>, kể cả phí giao. Bạn không phải trả thêm gì.
</p>

<h2>Không đổi trả</h2>

{{--
    NÓI THẲNG PHẦN KHÔNG ĐƯỢC ĐỔI, và nói VÌ SAO.

    Chính sách chỉ liệt kê phần có lợi cho khách rồi giấu phần còn lại
    vào chữ nhỏ là cách nhanh nhất để mất lòng tin đúng vào lúc khách
    đang bực. Nêu lý do thì người ta còn thấy hợp lý; nêu suông thì họ
    chỉ thấy bị từ chối.
--}}
<ul>
    <li>
        <strong>Hoa tươi đã nhận trên 2 giờ.</strong>
        Hoa cắt cành xuống sắc theo giờ, và sau khoảng đó không còn phân biệt
        được là hoa hỏng lúc giao hay hỏng vì để ngoài nắng.
    </li>
    <li>
        <strong>Cây héo sau vài ngày do chăm sai.</strong>
        Tưới úng là nguyên nhân phổ biến nhất. Mỗi sản phẩm cây đều có hồ sơ
        chăm sóc trên trang; hãy đọc trước khi trồng.
    </li>
    <li>
        <strong>Hoa cưới, hoa sự kiện, hàng đặt riêng.</strong>
        Chúng được làm riêng theo yêu cầu của bạn và không bán lại cho ai
        khác được. Muốn huỷ thì phải báo trước ít nhất 24 giờ.
    </li>
    <li>
        <strong>Đổi ý sau khi đã nhận hàng</strong> mà sản phẩm không có lỗi.
    </li>
</ul>

<h2>Huỷ đơn</h2>

<p>
    Bạn tự huỷ được đơn ở trang chi tiết đơn hàng khi đơn còn ở trạng thái
    <strong>Chờ xác nhận</strong> hoặc <strong>Đã xác nhận</strong>.
    Từ <strong>Đang chuẩn bị</strong> trở đi thì hoa đã cắt và gói rồi — lúc
    này hãy gọi hotline, cửa hàng xử lý theo từng trường hợp.
</p>

<h2>Cách báo</h2>

<ol>
    <li>Chụp ảnh sản phẩm <strong>ngay khi mở gói</strong>, chụp cả gói hàng.</li>
    <li>Gọi hotline hoặc gửi email kèm ảnh và <strong>mã đơn</strong>.</li>
    <li>Cửa hàng trả lời trong ngày làm việc và hẹn đổi hoặc hoàn tiền.</li>
</ol>

<p>
    Hoàn tiền chuyển khoản về đúng tài khoản đã chuyển đến, trong 3–5 ngày
    làm việc kể từ khi hai bên thống nhất.
</p>

<div class="static-page__notice">
    <p class="mb-0">
        Đây là bài tập lớn môn học. Chính sách trên mô tả cách một cửa hàng
        thật vận hành, nhưng hiện <strong>không có giao dịch thật nào</strong>
        được xử lý.
    </p>
</div>

@endsection
