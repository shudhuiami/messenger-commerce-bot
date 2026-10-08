<?php

namespace Shudhuiami\MessengerCommerceBot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyMessengerSignature
{
    /**
     * Rejects webhook POSTs that aren't signed by Meta. Every real delivery
     * carries X-Hub-Signature-256: sha256=<HMAC of the raw body keyed with
     * the app secret>; without this check anyone who finds the URL could
     * post fake messages as any customer and place orders in their name.
     * Fails closed when the app secret isn't configured.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('messenger-bot.app_secret');

        if (! $secret) {
            Log::error('Messenger webhook rejected: MESSENGER_APP_SECRET is not configured.');
            return response('Forbidden', 403);
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('Messenger webhook rejected: invalid or missing X-Hub-Signature-256.');
            return response('Forbidden', 403);
        }

        return $next($request);
    }
}
