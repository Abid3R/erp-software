<?php

namespace App\Services\Gemini;

use App\Services\Gemini\Exceptions\GeminiException;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the Google Gemini `generateContent` REST endpoint. No SDK —
 * just Laravel's HTTP client, so it is trivially fakeable in tests. Stateless:
 * the caller passes the full turn history each time.
 */
class GeminiClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Send a conversation and return the model's plain-text reply.
     *
     * @param  string  $systemInstruction  Grounding + behaviour rules.
     * @param  list<array{role: string, text: string}>  $history  Ordered turns; role is 'user' or 'model'.
     */
    public function chat(string $systemInstruction, array $history): string
    {
        if (! $this->isConfigured()) {
            throw new GeminiException('The AI assistant is not configured. Set GEMINI_API_KEY to enable it.');
        }

        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $base = rtrim((string) config('services.gemini.base_url'), '/');
        $key = (string) config('services.gemini.key');

        $contents = array_map(fn (array $turn): array => [
            'role' => $turn['role'] === 'model' ? 'model' : 'user',
            'parts' => [['text' => $turn['text']]],
        ], $history);

        $response = Http::timeout((int) config('services.gemini.timeout', 30))
            ->acceptJson()
            ->withHeaders(['x-goog-api-key' => $key])
            ->post("{$base}/models/{$model}:generateContent", [
                'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 1024,
                ],
            ]);

        if ($response->failed()) {
            $message = $response->json('error.message') ?? 'The AI service returned an error.';

            throw new GeminiException("Gemini request failed: {$message}");
        }

        $text = $this->extractText($response->json());

        if ($text === '') {
            throw new GeminiException('The AI service returned an empty response.');
        }

        return $text;
    }

    /**
     * Pull the concatenated text parts from the first candidate.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): string
    {
        /** @var list<array{text?: string}> $parts */
        $parts = data_get($payload, 'candidates.0.content.parts', []);

        $text = '';
        foreach ($parts as $part) {
            $text .= $part['text'] ?? '';
        }

        return trim($text);
    }
}
