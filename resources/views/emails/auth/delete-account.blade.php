{{--
    Thư xác nhận yêu cầu xoá tài khoản.

    Bảng + style nội tuyến, KHÔNG dùng class hay tệp CSS ngoài — Gmail và
    Outlook bỏ <link> và cắt <style> ở <head>. Xem chú thích dài hơn ở
    emails/orders/confirmation.blade.php.

    THƯ NÀY VỪA LÀ BƯỚC XÁC NHẬN VỪA LÀ CẢNH BÁO. Người nhận có thể là
    chủ tài khoản đang muốn xoá, nhưng cũng có thể là người vừa bị kẻ
    khác chiếm tài khoản. Nội dung phải phục vụ được cả hai: đường xoá
    cho người thứ nhất, và lời cảnh báo rõ ràng cho người thứ hai.
--}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xác nhận xoá tài khoản</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f5f0; font-family:Arial,Helvetica,sans-serif; color:#1e231f;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e4e2da; border-radius:8px;">

    <tr>
        <td style="padding:24px 24px 8px 24px;">
            <div style="font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#5d6660;">
                {{ \App\Services\Shop\StoreProfile::name() }}
            </div>
            <h1 style="margin:8px 0 4px 0; font-size:20px;">Xác nhận xoá tài khoản</h1>
        </td>
    </tr>

    <tr>
        <td style="padding:8px 24px; font-size:14px; line-height:1.6;">
            <p style="margin:0 0 12px 0;">Chào {{ $name }},</p>

            <p style="margin:0 0 12px 0;">
                Chúng tôi nhận được yêu cầu xoá vĩnh viễn tài khoản này.
                Bấm nút bên dưới để xem chính xác những gì sẽ bị xoá và xác nhận lần cuối.
            </p>

            <p style="margin:0 0 20px 0;">
                <strong>Bấm vào đây chưa xoá gì cả</strong> — bạn sẽ được đưa tới một trang
                xác nhận và phải tự tay gõ một dòng để đồng ý.
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:0 24px 20px 24px;">
            <a href="{{ $url }}"
               style="display:inline-block; padding:12px 22px; background:#a13f2e; color:#ffffff; text-decoration:none; border-radius:6px; font-size:15px;">
                Tiếp tục xoá tài khoản
            </a>
        </td>
    </tr>

    <tr>
        <td style="padding:0 24px 8px 24px; font-size:13px; line-height:1.6; color:#5d6660;">
            <p style="margin:0 0 12px 0;">
                Liên kết hết hạn sau {{ $minutes }} phút và chỉ dùng được một lần.
            </p>

            {{--
                CẢNH BÁO ĐẶT NGAY DƯỚI NÚT, không nhét xuống chân thư.
                Người đọc thư này mà không hề yêu cầu xoá chính là người
                cần đọc kỹ nhất, và họ sẽ dừng lại ngay khi thấy nút.
            --}}
            <p style="margin:0 0 12px 0; padding:12px; background:#fdf1d8; border-radius:6px; color:#7a5b12;">
                <strong>Bạn không yêu cầu việc này?</strong>
                Vậy có người khác đang đăng nhập được vào tài khoản của bạn.
                Hãy <strong>đổi mật khẩu ngay</strong> và bỏ qua thư này &mdash; không bấm gì thì
                không có gì bị xoá.
            </p>

            <p style="margin:0;">
                @if($hotline)
                    Cần trợ giúp? Gọi {{ $hotline }} hoặc trả lời thư này.
                @else
                    Cần trợ giúp? Trả lời thư này.
                @endif
            </p>
        </td>
    </tr>

</table>

</body>
</html>
