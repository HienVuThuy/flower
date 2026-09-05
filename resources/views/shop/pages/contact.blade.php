@extends('shop.pages._layout')

@section('page')

@php
    $hotline = \App\Services\Shop\StoreProfile::hotline();
    $email = \App\Services\Shop\StoreProfile::email();
    $address = \App\Services\Shop\StoreProfile::address();
@endphp

<p class="lead">
    Có ba việc khách hay cần, và mỗi việc có một đường đi nhanh nhất.
    Chọn đúng đường thì không phải chờ.
</p>

{{--
    ĐƯA VIỆC LÊN TRƯỚC, THÔNG TIN LIÊN HỆ XUỐNG SAU.

    Phần lớn người mở trang Liên hệ không muốn "liên hệ" — họ muốn biết
    đơn của mình tới đâu, hoặc muốn hỏi giá cho một lẵng hoa lớn. Đưa số
    điện thoại lên đầu là bắt họ gọi cho việc mà trang web tự làm được.
--}}
<h2>Bạn đang cần gì?</h2>

<dl class="static-page__facts">
    <div>
        <dt>Xem đơn hàng đã đặt</dt>
        <dd>
            <a href="{{ route('shop.orders.lookup') }}">Tra cứu đơn hàng</a>
            bằng mã đơn và số điện thoại — không cần đăng nhập.
        </dd>
    </div>
    <div>
        <dt>Đặt hoa số lượng lớn, hoa sự kiện</dt>
        <dd>
            <a href="{{ route('shop.bulk-inquiry.create') }}">Gửi yêu cầu báo giá</a>.
            Cửa hàng trả lời trong ngày làm việc.
        </dd>
    </div>
    <div>
        <dt>Không biết chọn cây nào</dt>
        <dd>
            <a href="{{ route('shop.advisor.index') }}">Chọn cây theo nhu cầu</a> —
            lọc theo chỗ đặt, mức nắng và kinh nghiệm chăm cây của bạn.
        </dd>
    </div>
</dl>

<h2>Liên hệ trực tiếp</h2>

<dl class="static-page__facts">
    <div>
        <dt>Hotline</dt>
        <dd>{{ $hotline }}</dd>
    </div>
    <div>
        <dt>Email</dt>
        <dd><a href="mailto:{{ $email }}">{{ $email }}</a></dd>
    </div>
    <div>
        <dt>Địa chỉ</dt>
        <dd>{{ $address }}</dd>
    </div>
    <div>
        <dt>Giờ mở cửa</dt>
        <dd>8:00 – 20:00, tất cả các ngày trong tuần.</dd>
    </div>
</dl>

<h2>Giao hàng</h2>

<p>
    Cửa hàng đặt tại Hà Nội và giao đi cả nước. Phí giao tính theo khoảng
    cách từ đây, chốt lại khi bạn nhập địa chỉ ở bước thanh toán:
</p>

<table class="static-page__table">
    <thead>
        <tr>
            <th>Khu vực</th>
            <th>Phí giao</th>
        </tr>
    </thead>
    <tbody>
        {{-- Đọc thẳng từ config/shipping.php: bảng trên trang và số tiền
             khách thực trả không được phép là hai nguồn khác nhau. --}}
        @foreach(config('shipping.zones') as $zone)
            <tr>
                <td>{{ $zone['label'] }}</td>
                <td><x-site.money :amount="(float) $zone['fee']" /></td>
            </tr>
        @endforeach
    </tbody>
</table>

<p>
    Đơn từ
    <strong><x-site.money :amount="(float) config('shipping.free_from')" /></strong>
    tiền hàng được miễn phí giao, áp dụng cho mọi khu vực.
</p>

<div class="static-page__notice">
    <p class="mb-0">
        Đây là bài tập lớn môn học, không phải cửa hàng đang kinh doanh.
        Hotline và địa chỉ ở trên dùng để chấm bài và thử nghiệm.
        Xem thêm ở trang <a href="{{ route('shop.pages.show', 'gioi-thieu') }}">Giới thiệu</a>.
    </p>
</div>

@endsection
