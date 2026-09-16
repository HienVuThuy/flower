{{-- Thư nhắc chăm cây. --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nhắc chăm cây</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f5f0; font-family:Arial,Helvetica,sans-serif; color:#1e231f;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e4e2da; border-radius:8px;">

    <tr>
        <td style="height:4px; background:#628857; border-radius:8px 8px 0 0; font-size:0; line-height:0;">&nbsp;</td>
    </tr>

    <tr>
        <td style="padding:22px 24px 6px 24px;">
            <h1 style="margin:0 0 6px 0; font-size:19px;">
                @if($reminders->count() === 1)
                    {{ $reminders->first()->kind->headline() }}
                @else
                    {{ $reminders->count() }} việc chăm cây cần làm
                @endif
            </h1>
            <p style="margin:0; font-size:14px; color:#5d6660;">
                Chào {{ $name }}, đây là lịch chăm cho cây bạn đã mua ở cửa hàng.
            </p>
        </td>
    </tr>

    @foreach($reminders as $reminder)
        @php
            $product = $reminder->product;
            $care = is_array($product?->care_info) ? $product->care_info : [];
            $advice = trim((string) ($care[$reminder->kind->adviceKey()] ?? ''));
        @endphp

        <tr>
            <td style="padding:10px 24px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                       style="background:#f7f9f6; border-left:3px solid #628857; border-radius:0 4px 4px 0;">
                    <tr>
                        <td style="padding:13px 15px;">

                            <p style="margin:0 0 3px 0; font-size:15px; font-weight:bold;">
                                {{ $reminder->kind->label() }}: {{ $product?->name ?? 'Cây của bạn' }}
                            </p>

                            @if($advice !== '')
                                <p style="margin:0 0 3px 0; font-size:13.5px; line-height:1.55;">
                                    {{ $advice }}
                                </p>
                            @endif

                            <p style="margin:0; font-size:12.5px; color:#5d6660;">
                                Chu kỳ mỗi {{ $reminder->interval_days }} ngày
                                @if($reminder->isOverdue())
                                    &middot; <strong style="color:#b5813a;">đã quá hạn {{ abs($reminder->daysUntilDue()) }} ngày</strong>
                                @endif
                            </p>

                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endforeach

    <tr>
        <td style="padding:14px 24px 22px 24px; border-top:1px solid #e4e2da; font-size:12.5px; color:#5d6660; line-height:1.6;">
            Cây đã chết, đã tặng đi, hoặc bạn không muốn nhận nhắc nữa?
            Vào <a href="{{ route('shop.care.index') }}" style="color:#1b2f22;">Lịch chăm cây</a>
            để tắt riêng từng cây, hoặc tắt hết trong Hồ sơ tài khoản.

            @if($hotline)
                <br>Cần tư vấn thêm: {{ $hotline }}
            @endif
        </td>
    </tr>

</table>

</body>
</html>
