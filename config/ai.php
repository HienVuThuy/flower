<?php

/* Trợ lý AI — "Plant & Shopping Advisor" */

return [

    'provider' => env('AI_PROVIDER', 'gemini'),

    /* Trần chống lạm dụng: mỗi lượt hỏi là một lần trả tiền cho nhà cung cấp. */
    'moi_phut' => (int) env('AI_MOI_PHUT', 10),
    'moi_ngay' => (int) env('AI_MOI_NGAY', 80),

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),

        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),

        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

        'timeout' => (int) env('GEMINI_TIMEOUT', 20),

        'verify_ssl' => env('GEMINI_VERIFY_SSL', true),

        'thinking_budget' => (int) env('GEMINI_THINKING_BUDGET', 0),
    ],

    'max_history' => 8,

    'max_message_length' => 500,

    'max_output_tokens' => 1024,

    'max_products' => 8,
];
