<?php

return [

    /* | CỔNG THANH TOÁN */

    'gateways' => [

        'momo' => [
            'partner_code' => env('MOMO_PARTNER_CODE'),
            'access_key' => env('MOMO_ACCESS_KEY'),
            'secret_key' => env('MOMO_SECRET_KEY'),
            'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
            'refund_endpoint' => env('MOMO_REFUND_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/refund'),

            'partner_name' => env('MOMO_PARTNER_NAME', 'Angevil'),
            'store_id' => env('MOMO_STORE_ID', 'AngevilStore'),

            'request_type' => env('MOMO_REQUEST_TYPE', 'payWithCC'),

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
