<?php

namespace Shudhuiami\MessengerCommerceBot\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessengerService
{
    public function sendText(string $psid, string $text): void
    {
        $accessToken = config('messenger-bot.page_access_token');

        if (! $accessToken) {
            Log::warning('Messenger send skipped: MESSENGER_PAGE_ACCESS_TOKEN is not configured.', ['psid' => $psid]);
            return;
        }

        $version = config('messenger-bot.graph_version', 'v19.0');
        $url = "https://graph.facebook.com/{$version}/me/messages?access_token={$accessToken}";

        try {
            $response = Http::post($url, [
                'recipient' => ['id' => $psid],
                'message' => ['text' => $text],
            ]);

            if (! $response->successful()) {
                Log::error('Messenger send failed.', [
                    'psid' => $psid,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Messenger send threw an exception.', [
                'psid' => $psid,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
