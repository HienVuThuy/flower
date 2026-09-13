{{--
    Thư NỘI BỘ gửi cửa hàng khi có yêu cầu báo giá số lượng lớn.

    Đưa NGÀY SỰ KIỆN và CÒN BAO NHIÊU NGÀY lên đầu: đó là thứ quyết định
    phải gọi lại ngay hôm nay hay để mai.
--}}
@php
    $conNgay = $inquiry->daysUntilEvent();
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Yêu cầu báo giá từ {{ $inquiry->contact_name }}</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f5f0; font-family:Arial,Helvetica,sans-serif; color:#1e231f;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e4e2da; border-radius:8px;">

    <tr>
        <td style="height:4px; background:#2f6f5e; border-radius:8px 8px 0 0; font-size:0; line-height:0;">&nbsp;</td>
    </tr>

    <tr>
        <td style="padding:20px 24px 8px 24px;">
            <h1 style="margin:0 0 4px 0; font-size:19px;">
                Yêu cầu báo giá từ {{ $inquiry->contact_name }}
            </h1>
            <p style="margin:0; font-size:13px; color:#5d6660;">
                Gửi lúc <x-site.time :at="$inquiry->created_at" />
            </p>
        </td>
    </tr>

    @if($inquiry->event_date)
        <tr>
            <td style="padding:8px 24px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                       style="background:#f1f6f4; border-left:3px solid #2f6f5e;">
                    <tr>
                        <td style="padding:12px 14px; font-size:14px;">
                            <strong>Ngày sự kiện:</strong> {{ $inquiry->event_date->format('d/m/Y') }}
                            @if($conNgay !== null)
                                &middot; {{ $conNgay < 0 ? 'đã qua' : ($conNgay === 0 ? 'HÔM NAY' : 'còn ' . $conNgay . ' ngày') }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endif

    <tr>
        <td style="padding:12px 24px; font-size:14px; line-height:1.6;">
            <strong>Liên hệ:</strong> {{ $inquiry->contact_name }} &mdash; {{ $inquiry->contact_phone }}<br>
            @if($inquiry->contact_email)
                <strong>Email:</strong> {{ $inquiry->contact_email }}<br>
            @endif
            @if($inquiry->preferred_contact)
                <strong>Muốn được liên hệ qua:</strong> {{ $inquiry->preferred_contact->label() }}<br>
            @endif
            @if($inquiry->company_name)
                <strong>Đơn vị:</strong> {{ $inquiry->company_name }}<br>
            @endif
            @if($inquiry->occasion)
                <strong>Dịp:</strong> {{ $inquiry->occasion }}<br>
            @endif
            @if($inquiry->event_location)
                <strong>Địa điểm:</strong> {{ $inquiry->event_location }}<br>
            @endif
            @if($inquiry->product)
                <strong>Sản phẩm quan tâm:</strong> {{ $inquiry->product->name }}<br>
            @endif
            @if($inquiry->quantity_estimate)
                <strong>Số lượng dự kiến:</strong> {{ $inquiry->quantity_estimate }}<br>
            @endif
            @if($inquiry->budgetText())
                <strong>Ngân sách:</strong> {{ $inquiry->budgetText() }}<br>
            @endif
        </td>
    </tr>

    @if($inquiry->message)
        <tr>
            <td style="padding:0 24px 12px 24px; font-size:14px; line-height:1.6;">
                <strong>Lời nhắn:</strong><br>
                {{ $inquiry->message }}
            </td>
        </tr>
    @endif

    <tr>
        <td style="padding:12px 24px 20px 24px; border-top:1px solid #e4e2da; font-size:13px; color:#5d6660;">
            <a href="{{ route('admin.bulk-inquiries.show', $inquiry) }}" style="color:#2f6f5e; font-weight:bold;">Mở yêu cầu trong trang quản trị</a>
            @if($inquiry->contact_email)
                &middot; Bấm <strong>Trả lời</strong> để gửi báo giá thẳng cho khách.
            @endif
        </td>
    </tr>

</table>

</body>
</html>
