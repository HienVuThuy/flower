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
         * MoMo — môi trường THỬ.
         *
         * Bộ khoá test của MoMo là khoá dùng chung, ai cũng có; nó vẫn
         * nằm trong `.env` chứ không viết vào tệp này, để ngày đổi sang
         * khoá thật không phải sửa mã nguồn và không có nguy cơ khoá
         * thật lọt vào Git.
         *
         * `redirect_url` / `ipn_url` để trống thì lớp MomoGateway tự lấy
         * từ route — chỉ cần điền khi chạy ngrok hoặc tên miền thật.
         */
        'momo' => [
            'partner_code' => env('MOMO_PARTNER_CODE'),
            'access_key' => env('MOMO_ACCESS_KEY'),
            'secret_key' => env('MOMO_SECRET_KEY'),
            'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
            'refund_endpoint' => env('MOMO_REFUND_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/refund'),

            'partner_name' => env('MOMO_PARTNER_NAME', 'Angevil'),
            'store_id' => env('MOMO_STORE_ID', 'AngevilStore'),

            /*
             * CHỈ CÒN LÀ MỨC MẶC ĐỊNH — khách tự chọn ở bước thanh toán.
             *
             * Từ khi có App\Enums\MomoFlow, mỗi lượt thanh toán mang
             * theo cách khách đã chọn. Giá trị ở đây chỉ dùng khi không
             * ai chọn (gọi từ mã nguồn khác, hoặc link cũ).
             *
             * HAI GIÁ TRỊ ĐƯỢC HỖ TRỢ:
             *
             *   captureWallet  mã QR, quét bằng ứng dụng MoMo
             *   payWithCC      thẻ quốc tế (5200 0000 0000 1096)
             *
             * `payWithATM` (thẻ nội địa 9704...) KHÔNG có trong MomoFlow:
             * đo được 10/09/2026 là môi trường thử của MoMo từ chối dịch
             * vụ này — trang mở ra đứng mãi ở "Đang tải dữ liệu giao
             * dịch". Đặt giá trị đó vào đây thì MomoFlow::macDinh() lùi
             * về payWithCC VÀ ghi log cảnh báo, chứ không im lặng.
             */
            'request_type' => env('MOMO_REQUEST_TYPE', 'payWithCC'),

            /*
             * MOMO_VERIFY_SSL=false chỉ dành cho máy học ở nhà, nơi
             * XAMPP thường thiếu bộ chứng chỉ gốc nên cURL từ chối kết
             * nối. Trên máy chủ thật phải để true.
             */
            'verify_ssl' => filter_var(env('MOMO_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),

            'redirect_url' => env('MOMO_REDIRECT_URL'),
            'ipn_url' => env('MOMO_IPN_URL'),

            'enabled' => (bool) (
                env('MOMO_PARTNER_CODE')
                && env('MOMO_ACCESS_KEY')
                && env('MOMO_SECRET_KEY')
            ),
        ],

    ],

];
