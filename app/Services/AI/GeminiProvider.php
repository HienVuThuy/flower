<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini qua REST (generateContent).
 * ============================================================
 * KHOÁ ĐI TRONG HEADER `x-goog-api-key`, KHÔNG TRÊN ĐƯỜNG DẪN: đường dẫn nằm
 * trong nhật ký máy chủ, nhật ký proxy, thông báo lỗi — khoá trên đó là khoá
 * bị lộ. Nhật ký lỗi ở đây cũng không ghi khoá.
 */
final class GeminiProvider implements AiProvider
{
    /** @param array{key?: ?string, model?: string, base_url?: string, timeout?: int, verify_ssl?: mixed} $cauHinh */
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
                        // Gemini gọi lượt của trợ lý là "model".
                        'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => [['text' => $m['text']]],
                    ], $messages),
                    'generationConfig' => [
                        // Thấp: tư vấn bán hàng cần bám dữ liệu, không cần sáng tạo.
                        'temperature' => 0.3,
                        'maxOutputTokens' => $this->toiDaToken,
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Không kết nối được Gemini.', ['model' => $model, 'loi' => $e->getMessage()]);

            throw new AiException('Không kết nối được trợ lý AI. Vui lòng thử lại sau.');
        }

        if ($phanHoi->failed()) {
            Log::warning('Gemini trả lỗi.', ['model' => $model, 'status' => $phanHoi->status()]);

            throw new AiException('Trợ lý AI đang bận. Vui lòng thử lại sau ít phút.');
        }

        $traLoi = collect((array) data_get($phanHoi->json(), 'candidates.0.content.parts', []))
            ->pluck('text')
            ->filter(fn ($t) => is_string($t))
            ->implode('');

        if (trim($traLoi) === '') {
            throw new AiException('Trợ lý AI chưa trả lời được câu này. Bạn thử hỏi cách khác nhé.');
        }

        return trim($traLoi);
    }
}
