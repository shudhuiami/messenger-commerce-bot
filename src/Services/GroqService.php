<?php

namespace Shudhuiami\MessengerCommerceBot\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    /**
     * Ask Groq's free-tier API (OpenAI-compatible chat completions) a
     * question, grounded by $systemPrompt. Returns null (never throws) if
     * the key is missing or the call fails, so callers can fall back to a
     * rule-based reply.
     */
    public function ask(string $systemPrompt, string $userMessage): ?string
    {
        $apiKey = config('messenger-bot.groq.api_key');

        if (! $apiKey) {
            return null;
        }

        $model = config('messenger-bot.groq.model', 'llama-3.3-70b-versatile');

        try {
            $response = Http::timeout(8)
                ->withToken($apiKey)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'temperature' => 0.4,
                    'max_tokens' => 220,
                ]);

            if (! $response->successful()) {
                Log::warning('Groq request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $text = $response->json('choices.0.message.content');

            return $text ? trim($text) : null;
        } catch (\Throwable $e) {
            Log::error('Groq request threw an exception.', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Same as ask(), but requests strict JSON output and returns it decoded
     * as an array. Used for structured intent classification rather than
     * free-text replies. Returns null on missing key, API failure, or
     * malformed JSON — never throws.
     */
    public function askJson(string $systemPrompt, string $userMessage): ?array
    {
        $apiKey = config('messenger-bot.groq.api_key');

        if (! $apiKey) {
            return null;
        }

        $model = config('messenger-bot.groq.model', 'llama-3.3-70b-versatile');

        try {
            $response = Http::timeout(8)
                ->withToken($apiKey)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'temperature' => 0,
                    'max_tokens' => 120,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                Log::warning('Groq JSON request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $text = $response->json('choices.0.message.content');

            if (! $text) {
                return null;
            }

            $decoded = json_decode($text, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::error('Groq JSON request threw an exception.', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
