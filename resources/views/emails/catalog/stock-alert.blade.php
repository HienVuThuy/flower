{{-- Thư báo hàng về. --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hàng đã về</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f5f0; font-family:Arial,Helvetica,sans-serif; color:#1e231f;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e4e2da; border-radius:8px;">

    <tr>
        <td style="height:4px; background:#628857; border-radius:8px 8px 0 0; font-size:0; line-height:0;">&nbsp;</td>
    </tr>

    <tr>
        <td style="padding:22px 24px 6px 24px;">
            <h1 style="margin:0 0 6px 0; font-size:19px;">{{ $product->name }} đã có hàng lại</h1>
            <p style="margin:0; font-size:14px; color:#5d6660;">
                Chào {{ $name }}, món bạn nhờ báo tin đã về kho.
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:12px 24px 4px 24px; font-size:14px;">
            <p style="margin:0 0 14px 0;">
                Hàng theo mùa thường hết nhanh, nên nếu cần thì đặt sớm giúp cửa hàng nhé.
            </p>

            <a href="{{ $url }}"
               style="display:inline-block; padding:10px 18px; background:#628857; color:#ffffff; text-decoration:none; border-radius:6px; font-size:14px;">
                Xem sản phẩm
            </a>
        </td>
    </tr>

    <tr>
        <td style="padding:16px 24px 22px 24px; font-size:13px; color:#5d6660;">
            <p style="margin:0;">
                Bạn nhận thư này vì đã bấm "Báo tôi khi có hàng" ở trang sản phẩm. Mỗi lần đăng ký chỉ báo một lần.
                @if($hotline)
                    Cần gấp thì gọi {{ $hotline }}.
                @endif
            </p>
        </td>
    </tr>

</table>

</body>
</html>
