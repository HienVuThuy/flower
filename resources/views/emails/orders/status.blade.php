{{--
    Email báo đơn hàng đổi trạng thái.

    Bảng + style nội tuyến, KHÔNG dùng class hay tệp CSS ngoài — Gmail và
    Outlook bỏ <link> và cắt <style> ở <head>.

    Mọi con số đọc từ BẢN CHỤP trong đơn, không tính lại.
--}}
@php
    $status = $order->status;

    $address = collect([
        $order->shipping_address,
        $order->shipping_ward,
        $order->shipping_district,
        $order->shipping_province,
    ])->filter()->implode(', ');

    /*
     * Màu viền theo ý nghĩa của trạng thái. Chỉ dùng ba màu: đã huỷ là
     * tin xấu, đã giao là kết thúc tốt, còn lại là đang tiến triển.
     */
    $accent = match ($status->value) {
        'cancelled' => '#8a2e2e',
        'completed' => '#2f6b3f',
        default => '#2f4a37',
    };
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status->customerHeadline() }}</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f5f0; font-family:Arial,Helvetica,sans-serif; color:#1e231f;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e4e2da; border-radius:8px;">

    {{-- Dải màu trên cùng thay cho ảnh: ảnh trong email hay bị chặn. --}}
    <tr>
        <td style="height:4px; background:{{ $accent }}; border-radius:8px 8px 0 0; font-size:0; line-height:0;">&nbsp;</td>
    </tr>

    <tr>
        <td style="padding:24px 24px 8px 24px;">
            <div style="font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#5d6660;">
                {{ \App\Services\Shop\StoreProfile::name() }}
            </div>

            <h1 style="margin:8px 0 6px 0; font-size:20px;">{{ $status->customerHeadline() }}</h1>

            <p style="margin:0; font-size:14px; color:#5d6660;">
                Đơn <strong style="color:#1e231f;">{{ $order->order_number }}</strong>
                &middot; đặt lúc {{ $order->created_at->format('H:i d/m/Y') }}
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:8px 24px 16px 24px; font-size:14px; line-height:1.6;">
            <p style="margin:0 0 12px 0;">{{ $status->customerMessage() }}</p>

            @if($status->value === 'cancelled' && $order->cancel_reason)
                <p style="margin:0 0 12px 0; padding:10px 12px; background:#f7f2f2; border-left:3px solid {{ $accent }};">
                    Lý do: {{ $order->cancel_reason }}
                </p>
            @endif
        </td>
    </tr>

    {{-- ============ SẢN PHẨM ============ --}}
    <tr>
        <td style="padding:0 24px 8px 24px;">
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
                                <x-site.money :amount="(float) $item->unit_price" />
                                &times; {{ $item->quantity }}
                            </span>
                        </td>
                        <td align="right" style="padding:8px 0; border-bottom:1px solid #eeece4; white-space:nowrap;">
                            <strong><x-site.money :amount="(float) $item->line_total" /></strong>
                        </td>
                    </tr>
                @endforeach

                <tr>
                    <td style="padding:10px 0 0 0; font-size:16px;"><strong>Tổng thanh toán</strong></td>
                    <td align="right" style="padding:10px 0 0 0; font-size:16px;">
                        <strong><x-site.money :amount="(float) $order->grand_total" /></strong>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Địa chỉ giao chỉ còn ý nghĩa khi đơn chưa huỷ. --}}
    @if($status->value !== 'cancelled')
        <tr>
            <td style="padding:16px 24px; font-size:14px; line-height:1.6;">
                <h2 style="margin:0 0 8px 0; font-size:15px;">Giao tới</h2>
                <strong>{{ $order->recipient_name }}</strong> &mdash; {{ $order->recipient_phone }}<br>
                <span style="color:#5d6660;">{{ $address }}</span>
            </td>
        </tr>
    @endif

    <tr>
        <td style="padding:16px 24px 24px 24px; border-top:1px solid #e4e2da; font-size:13px; color:#5d6660;">
            Xem tình trạng đơn:
            <a href="{{ route('shop.orders.lookup') }}" style="color:#1e231f;">{{ route('shop.orders.lookup') }}</a>
            &mdash; nhập mã <strong style="color:#1e231f;">{{ $order->order_number }}</strong>
            và số điện thoại {{ $order->recipient_phone }}.
            <br><br>
            Cần hỗ trợ?
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
