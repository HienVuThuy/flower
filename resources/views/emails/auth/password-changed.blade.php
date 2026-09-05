{{--
    Thư CẢNH BÁO mật khẩu vừa bị đổi.

    Bảng + style nội tuyến, không dùng CSS ngoài (Gmail/Outlook cắt bỏ).
--}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mật khẩu vừa được thay đổi</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f5f0; font-family:Arial,Helvetica,sans-serif; color:#1e231f;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e4e2da; border-radius:8px;">

    {{-- Dải màu cảnh báo, không phải màu thương hiệu: thư này cần khác
         mắt so với thư khuyến mại để người ta không lướt qua. --}}
    <tr>
        <td style="height:4px; background:#8a2e2e; border-radius:8px 8px 0 0; font-size:0; line-height:0;">&nbsp;</td>
    </tr>

    <tr>
        <td style="padding:24px 24px 8px 24px;">
            <div style="font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#5d6660;">
                {{ \App\Services\Shop\StoreProfile::name() }}
            </div>
            <h1 style="margin:8px 0 4px 0; font-size:20px;">Mật khẩu vừa được thay đổi</h1>
        </td>
    </tr>

    <tr>
        <td style="padding:8px 24px; font-size:14px; line-height:1.6;">
            <p style="margin:0 0 12px 0;">Chào {{ $name }},</p>

            <p style="margin:0 0 12px 0;">
                Mật khẩu tài khoản của bạn vừa được đổi lúc
                <strong>{{ $changedAt }}</strong>@if($ipAddress) từ địa chỉ {{ $ipAddress }}@endif.
                Mọi thiết bị khác đang đăng nhập đã bị đăng xuất.
            </p>

            <p style="margin:0 0 12px 0;">
                <strong>Nếu chính bạn vừa đổi mật khẩu</strong>, bạn không cần làm gì thêm.
            </p>
        </td>
    </tr>

    {{--
        Phần quan trọng nhất của thư. Đóng khung và tô nền để người đang
        hoảng đọc được ngay mà không phải tìm.
    --}}
    <tr>
        <td style="padding:0 24px 16px 24px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                   style="background:#f9f4f4; border-left:3px solid #8a2e2e;">
                <tr>
                    <td style="padding:14px 16px; font-size:14px; line-height:1.6;">
                        <strong style="color:#8a2e2e;">Nếu KHÔNG phải bạn đổi</strong> — tài khoản có thể đã bị người khác chiếm.
                        Hãy làm ngay:
                        <ol style="margin:8px 0 0 0; padding-left:20px;">
                            <li>Dùng chức năng <strong>Quên mật khẩu</strong> để lấy lại quyền kiểm soát.</li>
                            <li>Kiểm tra lại địa chỉ email và số điện thoại trong hồ sơ — kẻ chiếm tài khoản thường đổi chúng trước.</li>
                            <li>Liên hệ cửa hàng
                                @if($hotline)
                                    theo số <strong>{{ $hotline }}</strong>
                                @endif
                                nếu bạn không tự lấy lại được.
                            </li>
                        </ol>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:16px 24px 24px 24px; border-top:1px solid #e4e2da; font-size:13px; color:#5d6660;">
            {{--
                KHÔNG kèm liên kết đặt lại mật khẩu trong thư này.

                Thư cảnh báo có nút bấm là mẫu quen thuộc của thư lừa đảo:
                "tài khoản của bạn bị xâm nhập, bấm vào đây". Dạy khách bấm
                theo là dạy họ mắc bẫy lần sau. Ở đây chỉ nói tên chức năng
                để họ tự vào website.
            --}}
            Vì lý do an toàn, thư này không kèm liên kết đăng nhập hay đặt lại mật khẩu.
            Hãy tự mở website của cửa hàng và dùng chức năng ở đó.
        </td>
    </tr>

</table>

</body>
</html>
