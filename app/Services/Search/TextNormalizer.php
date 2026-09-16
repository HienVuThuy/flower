<?php

namespace App\Services\Search;

/** Đưa chữ tiếng Việt về một dạng chuẩn duy nhất để so khớp. */
class TextNormalizer
{
    public const MAX_QUERY_LENGTH = 100;

    public const MAX_TOKENS = 8;

    public const MAX_TOKEN_LENGTH = 32;

    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',

        'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',

        'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',

        'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',

        'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',

        'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',

        'đ' => 'd',
    ];

    public function normalize(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = mb_strtolower($value, 'UTF-8');

        $value = preg_replace('/\p{Mn}/u', '', $value) ?? $value;

        $value = strtr($value, self::ACCENTS);

        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    public function tokenize(?string $value): array
    {
        $value = $this->normalize(mb_substr((string) $value, 0, self::MAX_QUERY_LENGTH, 'UTF-8'));

        if ($value === '') {
            return [];
        }

        $tokens = [];

        foreach (explode(' ', $value) as $token) {
            $token = substr($token, 0, self::MAX_TOKEN_LENGTH);

            if ($token !== '' && ! isset($tokens[$token])) {
                $tokens[$token] = true;
            }

            if (count($tokens) >= self::MAX_TOKENS) {
                break;
            }
        }

        return array_keys($tokens);
    }
}
