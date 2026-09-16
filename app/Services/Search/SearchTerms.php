<?php

namespace App\Services\Search;

/** Kết quả phân tích một lần tìm kiếm. */
readonly class SearchTerms
{
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

    public function suggestion(): string
    {
        return implode(' ', $this->tokens);
    }

    public function hasMultipleTokens(): bool
    {
        return count($this->tokens) > 1;
    }
}
