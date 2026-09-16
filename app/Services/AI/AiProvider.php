<?php

namespace App\Services\AI;

/** Một nhà cung cấp mô hình ngôn ngữ. */
interface AiProvider
{
    public function configured(): bool;

    public function reply(string $systemPrompt, array $messages): string;
}
