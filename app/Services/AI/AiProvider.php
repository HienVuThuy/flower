<?php

namespace App\Services\AI;

/**
 * Một nhà cung cấp mô hình ngôn ngữ.
 * ============================================================
 * Khung chat và phần đọc dữ liệu cửa hàng chỉ biết giao diện này. Thêm nhà
 * cung cấp khác = thêm một lớp cài đặt nó và khai trong AiManager; không
 * đụng tới controller, giao diện hay luật nghiệp vụ.
 */
interface AiProvider
{
    /** Đã có đủ khoá / cấu hình để gọi chưa. Chưa thì không được gọi ra ngoài. */
    public function configured(): bool;

    /**
     * Gửi chỉ dẫn hệ thống và lượt hội thoại, nhận câu trả lời dạng chữ.
     *
     * @param  list<array{role: 'user'|'assistant', text: string}>  $messages  cũ trước, mới sau
     *
     * @throws AiException với thông điệp đọc được cho khách
     */
    public function reply(string $systemPrompt, array $messages): string;
}
