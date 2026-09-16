{{-- Thư đặt lại mật khẩu. --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt lại mật khẩu</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f5f0; font-family:Arial,Helvetica,sans-serif; color:#1e231f;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e4e2da; border-radius:8px;">

    <tr>
        <td style="padding:24px 24px 8px 24px;">
            <div style="font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#5d6660;">
                {{ \App\Services\Shop\StoreProfile::name() }}
            </div>
            <h1 style="margin:8px 0 4px 0; font-size:20px;">Đặt lại mật khẩu</h1>
        </td>
    </tr>

    <tr>
        <td style="padding:8px 24px; font-size:14px; line-height:1.6;">
            <p style="margin:0 0 12px 0;">Chào {{ $name }},</p>

            <p style="margin:0 0 12px 0;">
                Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản này.
                Bấm nút bên dưới để chọn mật khẩu mới.
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:8px 24px 16px 24px;">
            <a href="{{ $url }}"
               style="display:inline-block; padding:12px 22px; background:#2f4a37; color:#ffffff; text-decoration:none; border-radius:6px; font-weight:bold; font-size:14px;">
                Đặt lại mật khẩu
            </a>
        </td>
    </tr>

    <tr>
        <td style="padding:0 24px 16px 24px; font-size:13px; line-height:1.6; color:#5d6660;">
            <p style="margin:0 0 10px 0;">
                Liên kết này chỉ dùng được <strong style="color:#1e231f;">một lần</strong>
                và sẽ hết hạn sau <strong style="color:#1e231f;">{{ $minutes }} phút</strong>.
            </p>

            <p style="margin:0 0 10px 0;">
                Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua thư này —
                mật khẩu hiện tại của bạn vẫn giữ nguyên và không ai đổi được nó.
            </p>

            <p style="margin:0;">
                Nút không bấm được? Sao chép đường dẫn sau vào trình duyệt:<br>
                <span style="word-break:break-all; color:#1e231f;">{{ $url }}</span>
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:16px 24px 24px 24px; border-top:1px solid #e4e2da; font-size:13px; color:#5d6660;">
            Cần hỗ trợ?
            @if($hotline)
                Gọi <strong style="color:#1e231f;">{{ $hotline }}</strong> hoặc trả lời thư này.
            @else
                Trả lời thư này để được hỗ trợ.
            @endif
        </td>
    </tr>

</table>

</body>
</html>
