<?php

namespace App\Services\Search;

/**
 * Kết quả phân tích một lần tìm kiếm.
 * ============================================================
 * Vật mang dữ liệu, không có logic nghiệp vụ: giữ nguyên thứ khách gõ,
 * thứ hệ thống thật sự đem đi tìm, và phần nào đã bị sửa.
 *
 * Tách ra thành lớp riêng để giao diện không phải đoán. Nếu chỉ trả về
 * mảng từ khoá thì Blade không có cách nào biết "hoa" là do khách gõ hay
 * do hệ thống đoán hộ — mà đó chính là điều bắt buộc phải nói cho khách
 * biết. Sửa từ khoá sau lưng người dùng rồi im lặng là làm họ tưởng cửa
 * hàng có đúng thứ họ tìm.
 */
readonly class SearchTerms
{
    /**
     * @param  string  $original  nguyên văn khách gõ, chỉ cắt khoảng trắng thừa
     * @param  list<string>  $tokens  từ khoá thật sự đem đi tìm
     * @param  array<string, string>  $corrections  từ gõ sai => từ đã thay
     * @param  string|null  $alternative  từ khoá gần giống, phổ biến hơn hẳn,
     *                                    gợi ý thêm chứ KHÔNG thay thế
     */
    public function __construct(
        public string $original,
        public array $tokens,
        public array $corrections = [],
        public ?string $alternative = null,
    ) {
    }

    public static function empty(string $original = ''): self
    {
        return new self($original, [], []);
    }

    public function isEmpty(): bool
    {
        return $this->tokens === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->tokens !== [];
    }

    public function wasCorrected(): bool
    {
        return $this->corrections !== [];
    }

    /** Cụm từ hệ thống đã dùng thay cho từ khoá gốc. */
    public function suggestion(): string
    {
        return implode(' ', $this->tokens);
    }

    /** Có từ hai từ khoá trở lên — điều kiện để nới lỏng phép AND. */
    public function hasMultipleTokens(): bool
    {
        return count($this->tokens) > 1;
    }
}
