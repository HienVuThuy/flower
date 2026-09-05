@extends('shop.pages._layout')

@section('page')

<p class="lead">
    {{ \App\Services\Shop\StoreProfile::name() }} là cửa hàng hoa tươi và cây cảnh đặt tại Hà Nội.
    Chúng tôi bán hoa cắt cành, cây trồng chậu, bonsai và vật tư chăm cây —
    cùng với phần khó hơn nhiều: giúp bạn chọn đúng thứ hợp với chỗ bạn định đặt nó.
</p>

<h2>Cửa hàng bán gì</h2>

<ul>
    <li>
        <strong>Hoa tươi</strong> — bó, hộp, giỏ, lẵng khai trương.
        Hoa cưới và hoa sự kiện làm theo đơn, không có sẵn.
    </li>
    <li>
        <strong>Cây cảnh</strong> — cây để bàn, cây lọc không khí, sen đá,
        xương rồng, bonsai.
    </li>
    <li>
        <strong>Phụ kiện và vật tư</strong> — chậu, đất trồng, phân bón,
        dụng cụ chăm cây.
    </li>
</ul>

<h2>Cách chúng tôi làm việc</h2>

<h3>Hoa cắt cành làm theo ngày</h3>

<p>
    Hoa tươi không có tồn kho theo nghĩa thông thường. Đơn đặt hôm nay được
    gói bằng hoa nhập buổi sáng hôm đó. Vì thế những mẫu hoa cưới và hoa sự
    kiện trên trang không hiện số lượng còn lại — chúng là
    <strong>hàng làm theo đơn</strong>, không phải hàng có sẵn trong kho.
</p>

<h3>Cây trồng chậu có số lượng thật</h3>

<p>
    Ngược lại, cây trồng chậu là hàng đếm được. Số bạn nhìn thấy trên trang
    sản phẩm là số cây thật đang có. Hết là hết, chúng tôi không nhận đơn
    rồi báo lại sau.
</p>

<h3>Mỗi cây đi kèm hồ sơ chăm sóc</h3>

<p>
    Phần lớn cây cảnh chết vì tưới sai, không phải vì thiếu nắng. Mỗi sản
    phẩm cây trên trang đều có hồ sơ chăm sóc: cần bao nhiêu nắng, tưới mấy
    ngày một lần, loại đất, chu kỳ bón phân, và độ khó với người mới. Nếu
    bạn có tài khoản, hệ thống sẽ nhắc lịch tưới và bón theo đúng chu kỳ đó.
</p>

<h2>Thông tin cửa hàng</h2>

<dl class="static-page__facts">
    <div>
        <dt>Địa chỉ</dt>
        <dd>{{ \App\Services\Shop\StoreProfile::address() }}</dd>
    </div>
    <div>
        <dt>Hotline</dt>
        <dd>{{ \App\Services\Shop\StoreProfile::hotline() }}</dd>
    </div>
    <div>
        <dt>Email</dt>
        <dd>{{ \App\Services\Shop\StoreProfile::email() }}</dd>
    </div>
</dl>

{{--
    NÓI THẲNG ĐÂY LÀ BÀI TẬP LỚN.

    Trang này chạy thật, gửi thư thật, tính tiền thật. Nhưng cửa hàng thì
    chưa. Để người đọc tưởng đây là một cửa hàng đang bán rồi họ chuyển
    khoản là chuyện không được phép xảy ra — nói ra một lần, rõ ràng, còn
    hơn để họ tự phát hiện sau khi mất tiền.
--}}
<div class="static-page__notice">
    <h2>Về phiên bản này</h2>
    <p>
        Đây là <strong>bài tập lớn môn học</strong>, không phải cửa hàng đang
        kinh doanh. Sản phẩm, giá và hình ảnh là dữ liệu mẫu. Bạn có thể đặt
        thử để xem quy trình chạy, nhưng <strong>đừng trả tiền thật</strong> —
        sẽ không có ai giao hàng.
    </p>
    <p class="mb-0">
        Ảnh minh hoạ lấy từ Openverse theo giấy phép cho phép dùng thương mại,
        có ghi công đầy đủ ở trang <a href="{{ route('shop.credits') }}">Nguồn ảnh</a>.
    </p>
</div>

@endsection
