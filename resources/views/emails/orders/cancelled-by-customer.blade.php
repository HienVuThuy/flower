{{-- Thư NỘI BỘ gửi cửa hàng khi khách tự huỷ đơn. --}}
@php
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
    <title>Khách huỷ đơn {{ $order->order_number }}</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f5f0; font-family:Arial,Helvetica,sans-serif; color:#1e231f;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e4e2da; border-radius:8px;">

    <tr>
        <td style="height:4px; background:#8a2e2e; border-radius:8px 8px 0 0; font-size:0; line-height:0;">&nbsp;</td>
    </tr>

    <tr>
        <td style="padding:20px 24px 8px 24px;">
            <h1 style="margin:0 0 4px 0; font-size:19px;">
                Khách đã tự huỷ đơn {{ $order->order_number }}
            </h1>
            <p style="margin:0; font-size:13px; color:#5d6660;">
                Huỷ lúc <x-site.time :at="$order->cancelled_at ?? now()" />
                &middot; đặt lúc <x-site.time :at="$order->created_at" />
            </p>
        </td>
    </tr>

    @if($order->cancel_reason)
        <tr>
            <td style="padding:8px 24px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                       style="background:#f9f4f4; border-left:3px solid #8a2e2e;">
                    <tr>
                        <td style="padding:12px 14px; font-size:14px;">
                            <strong>Lý do:</strong> {{ $order->cancel_reason }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endif

    <tr>
        <td style="padding:12px 24px; font-size:14px; line-height:1.6;">
            <strong>Người đặt:</strong> {{ $order->recipient_name }} &mdash; {{ $order->recipient_phone }}<br>
            @if($order->recipient_email)
                <strong>Email:</strong> {{ $order->recipient_email }}<br>
            @endif
            <strong>Giao tới:</strong> {{ $address }}<br>
            <strong>Giá trị đơn:</strong> <x-site.money :amount="(float) $order->grand_total" />
        </td>
    </tr>

    <tr>
        <td style="padding:0 24px 12px 24px;">
            <h2 style="margin:0 0 8px 0; font-size:15px;">Hàng trong đơn</h2>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:14px;">
                @foreach($order->items as $item)
                    <tr>
                        <td style="padding:6px 0; border-bottom:1px solid #eeece4;">
                            {{ $item->product_name }}
                            @if($item->variant_name)
                                <span style="color:#5d6660;">({{ $item->variant_name }})</span>
                            @endif
                        </td>
                        <td align="right" style="padding:6px 0; border-bottom:1px solid #eeece4; white-space:nowrap;">
                            &times; {{ $item->quantity }}
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:12px 24px 20px 24px; border-top:1px solid #e4e2da; font-size:13px; color:#5d6660;">
            Tồn kho đã được hoàn tự động. Nếu đã cắt hoa hoặc đã hẹn shipper,
            hãy kiểm tra và xử lý riêng — hệ thống không tự làm việc đó.
            Bấm <strong>Trả lời</strong> để liên hệ thẳng với khách.
        </td>
    </tr>

</table>

</body>
</html>
