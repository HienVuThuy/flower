<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Giao Hàng Nhanh (GHN)
    |--------------------------------------------------------------------------
    |
    | Dịch vụ vận chuyển tính phí giao theo địa chỉ THẬT của người nhận
    | (tỉnh → quận/huyện → phường/xã) thay vì bảng phí phẳng theo tỉnh.
    |
    | KHÔNG hard-code token hay shop id ở đây. Cả hai là thông tin định
    | danh của cửa hàng trên hệ thống GHN: lộ token là người khác tạo được
    | vận đơn dưới tên cửa hàng và ghi nợ tiền ship vào tài khoản này.
    | Chúng nằm trong .env, và .env đã nằm trong .gitignore.
    |
    | base_url mặc định trỏ về môi trường THỬ NGHIỆM của GHN. Đổi sang
    | https://online-gateway.ghn.vn/shiip/public-api khi chạy thật — và
    | khi đó phải đổi cả token, vì token của môi trường thử không dùng
    | được ở môi trường thật.
    |
    | verify_ssl để false CHỈ dành cho máy phát triển: XAMPP trên Windows
    | thường thiếu bộ chứng chỉ gốc nên mọi kết nối HTTPS đều hỏng. Trên
    | máy chủ thật PHẢI là true, nếu không thì ai đứng giữa cũng đọc và
    | sửa được nội dung trao đổi với GHN.
    */
    'ghn' => [
        'base_url' => env('GHN_BASE_URL', 'https://dev-online-gateway.ghn.vn/shiip/public-api'),
        'token' => env('GHN_TOKEN'),
        'shop_id' => env('GHN_SHOP_ID'),
        'verify_ssl' => env('GHN_VERIFY_SSL', true),

        // Quận/huyện GỬI HÀNG — mốc để GHN tính quãng đường.
        'from_district_id' => env('GHN_FROM_DISTRICT_ID'),

        /*
         * Trọng lượng mặc định (gram) cho sản phẩm chưa khai cân nặng.
         *
         * 200g là mức GHN dùng làm ví dụ và cũng hợp lý cho một bó hoa
         * nhỏ. Khai thiếu thì phí tính hụt, và phần hụt cửa hàng chịu —
         * nên đây là mức TẠM, không phải mức đúng.
         */
        'default_weight' => (int) env('GHN_DEFAULT_WEIGHT', 200),

        /*
         * Kích thước kiện mặc định (cm).
         *
         * GHN tính phí theo cân nặng QUY ĐỔI: dài × rộng × cao / 5000.
         * Bỏ trống thì GHN từ chối đơn, nên phải có một bộ số mặc định.
         */
        'box' => [
            'length' => (int) env('GHN_BOX_LENGTH', 20),
            'width' => (int) env('GHN_BOX_WIDTH', 20),
            'height' => (int) env('GHN_BOX_HEIGHT', 20),
        ],

        /*
         * Mã dịch vụ: 2 = hàng nhẹ (dưới 20kg), 5 = hàng nặng.
         * Cửa hàng hoa gần như luôn dùng hàng nhẹ.
         */
        'service_type_id' => (int) env('GHN_SERVICE_TYPE_ID', 2),

        /*
         * Bản ghi rác của MÔI TRƯỜNG THỬ, không hiện cho khách chọn.
         *
         *   2002 = "Hà Nội 02"                 → 0 quận/huyện
         *   298  = "Test - Alert - Tỉnh - 001" → 0 quận/huyện
         *
         * Cả hai đều `Status = 1` như tỉnh thật, nên không có cách nào
         * phân biệt bằng dữ liệu. Chọn phải chúng thì ô quận/huyện rỗng
         * và khách mắc kẹt.
         *
         * ĐỂ RỖNG KHI CHẠY THẬT: cổng thật của GHN không có mấy bản ghi
         * này, và một danh sách chặn cứng để lâu sẽ chặn nhầm khi GHN
         * dùng lại mã đó cho một tỉnh có thật.
         */
        'skip_province_ids' => array_filter(array_map(
            'intval',
            explode(',', (string) env('GHN_SKIP_PROVINCE_IDS', '2002,298')),
        )),
    ],

];
