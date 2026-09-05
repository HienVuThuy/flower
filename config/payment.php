<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CỔNG THANH TOÁN
    |--------------------------------------------------------------------------
    |
    | HIỆN CHƯA CÓ CỔNG NÀO. Mảng dưới đây là chỗ để khai, không phải một
    | danh sách cổng đang chạy — thấy tên "momo" ở đây KHÔNG có nghĩa là
    | hệ thống thanh toán được bằng MoMo.
    |
    | Cách một cổng được bật:
    |
    |   1. thêm `case` vào App\Enums\PaymentMethod và trỏ `gatewayKey()`
    |      vào khoá tương ứng ở đây;
    |   2. viết lớp implements App\Services\Payment\Contracts\PaymentGateway;
    |   3. điền khoá bí mật vào `.env` — `enabled` chỉ bật khi có đủ.
    |
    | `enabled` KHÔNG phải một công tắc bật/tắt do người gõ tay: nó được
    | TÍNH từ việc có đủ khoá hay không. Để admin tự bật một cổng chưa có
    | khoá là dựng ra một lựa chọn hỏng giữa đường, sau khi khách đã điền
    | hết địa chỉ.
    |
    | KHÔNG BAO GIỜ ghi khoá bí mật thẳng vào tệp này. Tệp này nằm trong
    | Git; `.env` thì không.
    |
    */

    'gateways' => [

        /*
         * MoMo — DỰ KIẾN, chưa tích hợp.
         *
         * Giữ khối này để tuần sau chỉ phải điền `.env` và viết lớp
         * MomoGateway, không phải nghĩ lại cấu trúc cấu hình. Vì
         * `enabled` tính từ ba khoá bên dưới và cả ba đang rỗng, MoMo
         * KHÔNG hiện ra ở bước thanh toán — xem PaymentMethod::available().
         *
         * Ba tham số này là bộ tối thiểu MoMo yêu cầu; endpoint để ở
         * config chứ không viết cứng trong mã để chuyển giữa môi trường
         * thử và môi trường thật mà không phải sửa mã nguồn.
         */
        'momo' => [
            'partner_code' => env('MOMO_PARTNER_CODE'),
            'access_key' => env('MOMO_ACCESS_KEY'),
            'secret_key' => env('MOMO_SECRET_KEY'),
            'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),

            'enabled' => (bool) (
                env('MOMO_PARTNER_CODE')
                && env('MOMO_ACCESS_KEY')
                && env('MOMO_SECRET_KEY')
            ),
        ],

    ],

];
