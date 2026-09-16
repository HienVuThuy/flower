<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Google Gemini qua REST (generateContent). */
final class GeminiProvider implements AiProvider
{
    public function __construct(
        private readonly array $cauHinh,
        private readonly int $toiDaToken = 800,
    ) {
    }

    public function configured(): bool
    {
        return trim((string) ($this->cauHinh['key'] ?? '')) !== '';
    }

    public function reply(string $systemPrompt, array $messages): string
    {
        if (! $this->configured()) {
            throw new AiException('Trợ lý AI chưa được cấu hình.');
        }

        $model = (string) ($this->cauHinh['model'] ?? 'gemini-2.5-flash');

        try {
            $phanHoi = Http::baseUrl((string) ($this->cauHinh['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'))
                ->withOptions(['verify' => filter_var($this->cauHinh['verify_ssl'] ?? true, FILTER_VALIDATE_BOOLEAN)])
                ->acceptJson()
                ->timeout((int) ($this->cauHinh['timeout'] ?? 20))
                ->withHeaders(['x-goog-api-key' => (string) $this->cauHinh['key']])
                ->post('models/' . rawurlencode($model) . ':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents' => array_map(fn (array $m) => [
                        'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => [['text' => $m['text']]],
                    ], $messages),
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => $this->toiDaToken,
                        'thinkingConfig' => ['thinkingBudget' => (int) ($this->cauHinh['thinking_budget'] ?? 0)],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Không kết nối được Gemini.', ['model' => $model, 'loi' => $e->getMessage()]);

            throw new AiException('Không kết nối được trợ lý AI. Vui lòng thử lại sau.');
        }

        if ($phanHoi->failed()) {
            Log::warning('Gemini trả lỗi.', [
                'model' => $model,
                'status' => $phanHoi->status(),
                'loi' => mb_substr((string) $phanHoi->json('error.message', ''), 0, 300),
            ]);

            throw new AiException('Trợ lý AI đang bận. Vui lòng thử lại sau ít phút.');
        }

        $traLoi = collect((array) data_get($phanHoi->json(), 'candidates.0.content.parts', []))
            ->pluck('text')
            ->filter(fn ($t) => is_string($t))
            ->implode('');

        if (trim($traLoi) === '') {
            throw new AiException('Trợ lý AI chưa trả lời được câu này. Bạn thử hỏi cách khác nhé.');
        }

        if (data_get($phanHoi->json(), 'candidates.0.finishReason') === 'MAX_TOKENS') {
            return rtrim($traLoi) . '… (câu trả lời dài nên bị cắt — bạn hỏi cụ thể hơn để nhận phần còn lại nhé)';
        }

        return trim($traLoi);
    }
}
