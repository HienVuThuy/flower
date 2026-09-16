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

    'ghn' => [
        'base_url' => env('GHN_BASE_URL', 'https://dev-online-gateway.ghn.vn/shiip/public-api'),

        'tracking_url' => env('GHN_TRACKING_URL'),
        'token' => env('GHN_TOKEN'),
        'shop_id' => env('GHN_SHOP_ID'),
        'verify_ssl' => env('GHN_VERIFY_SSL', true),

        'from_district_id' => env('GHN_FROM_DISTRICT_ID'),

        'default_weight' => (int) env('GHN_DEFAULT_WEIGHT', 200),

        'box' => [
            'length' => (int) env('GHN_BOX_LENGTH', 20),
            'width' => (int) env('GHN_BOX_WIDTH', 20),
            'height' => (int) env('GHN_BOX_HEIGHT', 20),
        ],

        'service_type_id' => (int) env('GHN_SERVICE_TYPE_ID', 2),

        'skip_province_ids' => array_filter(array_map(
            'intval',
            explode(',', (string) env('GHN_SKIP_PROVINCE_IDS', '2002,298')),
        )),
    ],

];
