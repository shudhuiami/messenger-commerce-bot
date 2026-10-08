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
        // Token goes in the Authorization header, never the URL: connection
        // errors echo the URL into exception messages, which we log below.
        $url = "https://graph.facebook.com/{$version}/me/messages";

        try {
            $response = Http::withToken($accessToken)->post($url, [
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
