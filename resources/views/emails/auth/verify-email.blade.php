{{--
    Thư chứa mã OTP xác thực email.

    Bảng + style nội tuyến, KHÔNG dùng class hay tệp CSS ngoài — Gmail và
    Outlook bỏ <link> và cắt <style> ở <head>. Xem chú thích dài hơn ở
    emails/orders/confirmation.blade.php.
--}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mã xác thực email</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f5f0; font-family:Arial,Helvetica,sans-serif; color:#1e231f;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e4e2da; border-radius:8px;">

    <tr>
        <td style="padding:24px 24px 8px 24px;">
            <div style="font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#5d6660;">
                {{ \App\Services\Shop\StoreProfile::name() }}
            </div>
            <h1 style="margin:8px 0 4px 0; font-size:20px;">Xác thực địa chỉ email</h1>
        </td>
    </tr>

    <tr>
        <td style="padding:8px 24px; font-size:14px; line-height:1.6;">
            <p style="margin:0 0 12px 0;">Chào {{ $name }},</p>
            <p style="margin:0 0 12px 0;">
                Nhập mã bên dưới vào trang xác thực để hoàn tất việc đăng ký.
            </p>
        </td>
    </tr>

    {{--
        MÃ ĐỂ TRONG MỘT Ô LỚN, GIÃN CHỮ.

        Khách sẽ đọc mã trên điện thoại rồi gõ lại trên máy tính. Chữ to,
        giãn ra và dùng phông đều nét để không nhầm 0 với O, 1 với l.
    --}}
    <tr>
        <td style="padding:8px 24px 16px 24px;">
            <div style="padding:18px 12px; border:1px dashed #2f6b46; border-radius:8px; background:#f2f7f3; text-align:center;">
                <div style="font-size:12px; color:#5d6660; margin-bottom:6px;">Mã xác thực của bạn</div>
                <div style="font-family:'Courier New',Consolas,monospace; font-size:32px; font-weight:bold; letter-spacing:8px; color:#1e231f;">
                    {{ $code }}
                </div>
            </div>
        </td>
    </tr>

    <tr>
        <td style="padding:0 24px 8px 24px; font-size:14px; line-height:1.6;">
            <p style="margin:0 0 12px 0;">
                Mã có hiệu lực trong <strong>{{ $minutes }} phút</strong> và chỉ dùng được một lần.
            </p>

            {{--
                Nói rõ phải làm gì nếu KHÔNG phải họ yêu cầu.

                Ai đó có thể gõ nhầm địa chỉ email khi đăng ký, và người
                nhận thư này cần biết mình không phải làm gì cả — im lặng
                ở đây làm người ta lo, hoặc tệ hơn, làm họ bấm bừa.
            --}}
            <p style="margin:0 0 12px 0; color:#5d6660;">
                Nếu bạn không đăng ký tài khoản tại {{ \App\Services\Shop\StoreProfile::name() }}, hãy bỏ qua thư này.
                Không ai có thể dùng địa chỉ email của bạn nếu không có mã ở trên.
            </p>

            <p style="margin:0 0 12px 0; color:#5d6660;">
                Nhân viên cửa hàng <strong>không bao giờ</strong> hỏi bạn mã này. Đừng đọc mã cho ai qua điện thoại.
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:8px 24px 24px 24px; border-top:1px solid #e4e2da; font-size:12px; color:#5d6660;">
            {{ \App\Services\Shop\StoreProfile::name() }} — hoa tươi &amp; cây cảnh
            @if($hotline)
                <br>Hotline: {{ $hotline }}
            @endif
        </td>
    </tr>

</table>

</body>
</html>
