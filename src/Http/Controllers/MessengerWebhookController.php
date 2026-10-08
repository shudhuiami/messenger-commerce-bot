<?php

namespace Shudhuiami\MessengerCommerceBot\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Shudhuiami\MessengerCommerceBot\Jobs\ProcessMessengerMessage;

class MessengerWebhookController extends Controller
{
    /**
     * Meta calls this with GET to verify the webhook URL.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expected = config('messenger-bot.verify_token');

        if ($mode === 'subscribe' && $expected && is_string($token) && hash_equals((string) $expected, $token)) {
            return response((string) $challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Meta calls this with POST for every page event. Kept thin: validate the
     * shape, queue a job per message event, and acknowledge immediately.
     */
    public function receive(Request $request): Response
    {
        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                if (! isset($event['message']['text'], $event['sender']['id'])) {
                    continue;
                }

                ProcessMessengerMessage::dispatch($event);
            }
        }

        return response('EVENT_RECEIVED', 200);
    }
}
