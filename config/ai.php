<?php

/*
|--------------------------------------------------------------------------
| Trợ lý AI — "Plant & Shopping Advisor"
|--------------------------------------------------------------------------
| Nhà cung cấp đứng sau MỘT giao diện (App\Services\AI\AiProvider): đổi
| sang nhà cung cấp khác là thêm một lớp và đổi AI_PROVIDER, không viết lại
| khung chat hay phần đọc dữ liệu cửa hàng.
|
| KHOÁ CHỈ ĐỌC TỪ .env — không ghi vào mã, không đưa lên Git. Chưa có khoá
| thì trợ lý KHÔNG gọi ra ngoài, khung chat nói "chưa được cấu hình". Không
| có câu trả lời giả.
*/

return [

    'provider' => env('AI_PROVIDER', 'gemini'),

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),

        /*
         * Đổi được bằng GEMINI_MODEL mà không sửa mã. Google ngừng mở
         * gemini-2.5-flash cho khoá mới (trả 404 kèm lời khuyên dùng bản mới),
         * nên mặc định là gemini-3.6-flash.
         */
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),

        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

        // Phải có giới hạn: dịch vụ ngoài treo thì request của khách không được treo theo.
        'timeout' => (int) env('GEMINI_TIMEOUT', 20),

        // false CHỈ cho máy phát triển (XAMPP trên Windows thiếu chứng chỉ gốc) — cùng lý do với GHN.
        'verify_ssl' => env('GEMINI_VERIFY_SSL', true),
    ],

    // Số lượt hỏi–đáp gần nhất gửi kèm để AI hiểu ngữ cảnh. Nhiều hơn là tốn và chậm.
    'max_history' => 8,

    // Độ dài tối đa một câu hỏi của khách.
    'max_message_length' => 500,

    'max_output_tokens' => 800,

    // Số sản phẩm tối đa đưa vào ngữ cảnh cho một câu hỏi.
    'max_products' => 8,
];
