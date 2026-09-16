<?php

namespace App\Services\AI;

/** Chọn nhà cung cấp AI theo cấu hình — NƠI DUY NHẤT biết tên các nhà cung cấp. */
class AiManager
{
    public function provider(): AiProvider
    {
        $ten = (string) config('ai.provider', 'gemini');

        return match ($ten) {
            'gemini' => new GeminiProvider((array) config('ai.gemini', []), (int) config('ai.max_output_tokens', 800)),
            default => throw new \InvalidArgumentException('Nhà cung cấp AI không được hỗ trợ: ' . $ten),
        };
    }
}
