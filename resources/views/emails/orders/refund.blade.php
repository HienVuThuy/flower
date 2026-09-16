{{-- Thư báo hoàn tiền. --}}
@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
    $cach = $refund->method;
    $daHoanDu = $order->payment_status === \App\Enums\PaymentStatus::Refunded;
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cửa hàng đã hoàn tiền</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f5f0; font-family:Arial,Helvetica,sans-serif; color:#1e231f;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e4e2da; border-radius:8px;">

    <tr>
        <td style="height:4px; background:#2f4a37; border-radius:8px 8px 0 0; font-size:0; line-height:0;">&nbsp;</td>
    </tr>

    <tr>
        <td style="padding:24px 24px 8px 24px;">
            <div style="font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#5d6660;">
                {{ \App\Services\Shop\StoreProfile::name() }}
            </div>

            <h1 style="margin:8px 0 6px 0; font-size:20px;">Cửa hàng đã hoàn tiền cho bạn</h1>

            <p style="margin:0; font-size:14px; color:#5d6660;">
                Đơn <strong style="color:#1e231f;">{{ $order->order_number }}</strong>
                &middot; mã hoàn tiền <strong style="color:#1e231f;">{{ $refund->code }}</strong>
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:16px 24px; font-size:14px; line-height:1.6;">
            <p style="margin:0 0 4px 0; font-size:13px; color:#5d6660;">Số tiền hoàn</p>
            <p style="margin:0 0 16px 0; font-size:24px;"><strong>{{ $tien($refund->amount) }}</strong></p>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:14px;">
                <tr>
                    <td style="padding:6px 0; color:#5d6660; width:40%;">Lý do</td>
                    <td style="padding:6px 0;">{{ $refund->reason->label() }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0; color:#5d6660;">Hoàn qua</td>
                    <td style="padding:6px 0;">
                        @switch($cach)
                            @case(\App\Enums\RefundMethod::Momo) MoMo, về đúng nguồn bạn đã dùng để thanh toán @break
                            @case(\App\Enums\RefundMethod::BankTransfer) Chuyển khoản ngân hàng @break
                            @default Tiền mặt
                        @endswitch
                    </td>
                </tr>
                @if($refund->reference)
                    <tr>
                        <td style="padding:6px 0; color:#5d6660;">Mã giao dịch</td>
                        <td style="padding:6px 0;"><strong>{{ $refund->reference }}</strong></td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:6px 0; color:#5d6660;">Thời điểm</td>
                    <td style="padding:6px 0;"><x-site.time :at="$refund->completed_at" /></td>
                </tr>
            </table>

            <p style="margin:16px 0 0 0; padding:10px 12px; background:#f4f5f0; border-left:3px solid #2f4a37;">
                @if($cach === \App\Enums\RefundMethod::Momo)
                    Tiền về ví MoMo thường nhanh; nếu bạn thanh toán bằng thẻ ngân hàng, thời gian tiền về tài khoản do ngân hàng phát hành thẻ quyết định.
                    Chưa thấy tiền, bạn gửi mã <strong>{{ $refund->reference ?? $refund->code }}</strong> cho MoMo hoặc ngân hàng để tra.
                @elseif($cach === \App\Enums\RefundMethod::BankTransfer)
                    Thời gian tiền về tài khoản do ngân hàng quyết định. Chưa thấy tiền, bạn gửi mã giao dịch ở trên cho ngân hàng để tra.
                @else
                    Cửa hàng đã trả tiền mặt trực tiếp cho bạn. Nếu thông tin này không đúng, hãy trả lời email này ngay.
                @endif
            </p>
        </td>
    </tr>

    @if($refund->items->isNotEmpty())
        <tr>
            <td style="padding:0 24px 16px 24px; font-size:14px;">
                <h2 style="margin:0 0 8px 0; font-size:15px;">Hàng cửa hàng đã nhận lại</h2>
                @foreach($refund->items as $d)
                    <div style="padding:6px 0; border-bottom:1px solid #eeece4;">
                        {{ $d->quantity }} &times; {{ $d->orderItem->product_name }}
                        @if($d->orderItem->variant_name)
                            <span style="color:#5d6660;">({{ $d->orderItem->variant_name }})</span>
                        @endif
                    </div>
                @endforeach
            </td>
        </tr>
    @endif

    <tr>
        <td style="padding:0 24px 16px 24px; font-size:14px; line-height:1.6;">
            @if($daHoanDu)
                Đơn này đã được hoàn đủ số tiền bạn đã trả ({{ $tien($order->grand_total) }}).
            @else
                Tổng cửa hàng đã hoàn cho đơn này: <strong>{{ $tien($order->refundedAmount()) }}</strong>
                trên {{ $tien($order->grand_total) }} bạn đã trả.
            @endif
        </td>
    </tr>

    <tr>
        <td style="padding:16px 24px 24px 24px; border-top:1px solid #e4e2da; font-size:13px; color:#5d6660;">
            Xem đơn:
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
