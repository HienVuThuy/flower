@props(['at', 'format' => 'H:i d/m/Y', 'relative' => false])

@php $moc = \App\Services\Time\Gio::hien($at); @endphp

{{--
    Hiện một mốc thời gian THEO GIỜ NGƯỜI ĐỌC.
    ============================================================
    DÙNG COMPONENT NÀY, KHÔNG GỌI ->format() TRỰC TIẾP.

    Ứng dụng lưu giờ UTC. Gọi thẳng `$order->created_at->format(...)` là
    in ra giờ UTC — đo trên đơn thật: đơn đặt lúc 18:42 giờ Hà Nội hiện
    ra 11:42. Trước bản này có 90 chỗ trong 55 tệp gọi thẳng như vậy, tức
    là 90 chỗ cùng sai một kiểu.

    ============================================================
    IN RA THẺ <time> CHỨ KHÔNG PHẢI CHỮ TRẦN.

    `datetime` mang mốc đầy đủ kèm múi giờ, nên trình đọc màn hình đọc
    đúng ngày và máy móc đọc lại được. Phần chữ thì viết theo kiểu người
    Việt đọc.

    `:at` là null thì in phần thân của thẻ — chỗ gọi tự nói "chưa có",
    "chưa giao"... Null nghĩa là CHƯA CÓ MỐC NÀO; in bừa giờ hiện tại là
    bịa ra một sự kiện chưa xảy ra.
--}}
@if($moc)
    <time datetime="{{ $moc->toIso8601String() }}" {{ $attributes }}>{{
        $relative ? $moc->diffForHumans() : $moc->format($format)
    }}</time>
@else
    {{ $slot }}
@endif
