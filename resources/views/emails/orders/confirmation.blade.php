{{--
    Email xác nhận đơn hàng.

    Dùng bảng và style nội tuyến, KHÔNG dùng class hay tệp CSS ngoài:
    phần lớn ứng dụng email (Gmail, Outlook) bỏ <link>, cắt <style> ở
    <head>, và không hỗ trợ flexbox hay grid. Đây là lý do email trông
    "cổ" so với web — không phải cẩu thả.

    Mọi con số đọc từ BẢN CHỤP trong đơn, không tính lại.
--}}
@php
    $money = fn ($v) => \App\Services\Shop\Money::format($v);

    $address = collect([
        $order->shipping_address,
        $order->shipping_ward,
        $order->shipping_district,
        $order->shipping_province,
    ])->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xác nhận đơn hàng {{ $order->order_number }}</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f5f0; font-family:Arial,Helvetica,sans-serif; color:#1e231f;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e4e2da; border-radius:8px;">

    <tr>
        <td style="padding:24px 24px 8px 24px;">
            <div style="font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#5d6660;">
                {{ \App\Services\Shop\StoreProfile::name() }}
            </div>
            <h1 style="margin:8px 0 4px 0; font-size:20px;">Cửa hàng đã xác nhận đơn của bạn</h1>
            <p style="margin:0; font-size:14px; color:#5d6660;">
                Mã đơn <strong style="color:#1e231f;">{{ $order->order_number }}</strong>
                &middot; đặt lúc <x-site.time :at="$order->created_at" />
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:16px 24px;">
            <p style="margin:0 0 4px 0; font-size:14px;">
                Trạng thái hiện tại: <strong>{{ $order->status->label() }}</strong>
            </p>
            <p style="margin:0; font-size:14px; color:#5d6660;">
                {{--
                    Câu cũ là "Cửa hàng sẽ liên hệ ... để xác nhận trước khi
                    giao" — đúng khi thư này gửi ngay lúc khách đặt hàng.
                    Nay thư chỉ đi SAU khi cửa hàng đã xác nhận, nên câu đó
                    thành sai: nó hẹn một việc vừa xong rồi.
                --}}
                Cửa hàng sẽ liên hệ số {{ $order->recipient_phone }} khi giao hàng.
            </p>
        </td>
    </tr>

    {{-- ============ SẢN PHẨM ============ --}}
    <tr>
        <td style="padding:8px 24px;">
            <h2 style="margin:0 0 8px 0; font-size:15px;">Sản phẩm</h2>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:14px;">
                @foreach($order->items as $item)
                    <tr>
                        <td style="padding:8px 0; border-bottom:1px solid #eeece4;">
                            {{-- Đọc từ bản chụp trong đơn, không từ bảng products --}}
                            {{ $item->product_name }}
                            @if($item->variant_name)
                                <span style="color:#5d6660;">({{ $item->variant_name }})</span>
                            @endif
                            <br>
                            <span style="color:#5d6660; font-size:13px;">
                                {{ $money($item->unit_price) }} &times; {{ $item->quantity }}
                                @if($item->promotion_name)
                                    &middot; {{ $item->promotion_name }}
                                @endif
                            </span>
                        </td>
                        <td align="right" style="padding:8px 0; border-bottom:1px solid #eeece4; white-space:nowrap;">
                            <strong>{{ $money($item->line_total) }}</strong>
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>

    {{-- ============ TIỀN ============ --}}
    <tr>
        <td style="padding:8px 24px 16px 24px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:14px;">

                <tr>
                    <td style="padding:4px 0; color:#5d6660;">Tạm tính</td>
                    <td align="right" style="padding:4px 0;">{{ $money($order->subtotal) }}</td>
                </tr>

                @if((float) $order->discount_total > 0)
                    <tr>
                        <td style="padding:4px 0; color:#5d6660;">Giảm giá khuyến mại</td>
                        <td align="right" style="padding:4px 0;">&minus;{{ $money($order->discount_total) }}</td>
                    </tr>
                @endif

                @if($order->coupon_code)
                    <tr>
                        <td style="padding:4px 0; color:#5d6660;">Mã {{ $order->coupon_code }}</td>
                        <td align="right" style="padding:4px 0;">&minus;{{ $money($order->coupon_discount) }}</td>
                    </tr>
                @endif

                <tr>
                    <td style="padding:4px 0; color:#5d6660;">Phí giao hàng</td>
                    <td align="right" style="padding:4px 0;">
                        {{ (float) $order->shipping_fee === 0.0 ? 'Miễn phí' : $money($order->shipping_fee) }}
                    </td>
                </tr>

                <tr>
                    <td style="padding:10px 0 0 0; border-top:1px solid #e4e2da; font-size:16px;"><strong>Tổng thanh toán</strong></td>
                    <td align="right" style="padding:10px 0 0 0; border-top:1px solid #e4e2da; font-size:16px;">
                        <strong>{{ $money($order->grand_total) }}</strong>
                    </td>
                </tr>

            </table>
        </td>
    </tr>

    {{-- ============ GIAO HÀNG ============ --}}
    <tr>
        <td style="padding:0 24px 16px 24px; font-size:14px;">
            <h2 style="margin:0 0 8px 0; font-size:15px;">Giao tới</h2>

            <p style="margin:0; line-height:1.6;">
                <strong>{{ $order->recipient_name }}</strong> &mdash; {{ $order->recipient_phone }}<br>
                {{ $address }}<br>
                <span style="color:#5d6660;">
                    Ngày nhận: {{ $order->delivery_date?->format('d/m/Y') ?? 'Sớm nhất có thể' }}<br>
                    Thanh toán: {{ $order->payment_method->label() }} &middot; {{ $order->payment_status->label() }}
                </span>
                @if($order->delivery_note)
                    <br><span style="color:#5d6660;">Ghi chú: {{ $order->delivery_note }}</span>
                @endif
            </p>
        </td>
    </tr>


    <tr>
        <td style="padding:16px 24px 24px 24px; border-top:1px solid #e4e2da; font-size:13px; color:#5d6660;">
            {{--
                Khách vãng lai không có tài khoản; email này là thứ duy nhất
                giữ lại mã đơn. Kèm luôn đường dẫn tra cứu để họ tự xem được
                tình trạng đơn mà không phải gọi điện.
            --}}
            Xem tình trạng đơn:
            <a href="{{ route('shop.orders.lookup') }}" style="color:#1e231f;">{{ route('shop.orders.lookup') }}</a>
            &mdash; nhập mã <strong style="color:#1e231f;">{{ $order->order_number }}</strong>
            và số điện thoại {{ $order->recipient_phone }}.
            <br><br>
            Cần thay đổi đơn hàng?
            @if($hotline)
                Gọi <strong style="color:#1e231f;">{{ $hotline }}</strong> hoặc trả lời email này.
            @else
                Trả lời email này để được hỗ trợ.
            @endif
        </td>
    </tr>

</table>

</body>
</html>
