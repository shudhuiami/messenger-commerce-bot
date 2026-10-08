<?php

namespace Shudhuiami\MessengerCommerceBot\Tests;

use Illuminate\Support\Facades\Queue;
use Shudhuiami\MessengerCommerceBot\Jobs\ProcessMessengerMessage;

class WebhookTest extends TestCase
{
    private function payload(): string
    {
        return json_encode(['object' => 'page', 'entry' => [[
            'messaging' => [[
                'sender' => ['id' => 'psid-1'],
                'message' => ['mid' => 'm-1', 'text' => 'hello'],
            ]],
        ]]]);
    }

    private function postWebhook(string $body, array $headers = [])
    {
        return $this->call('POST', '/messenger/webhook', [], [], [], array_merge([
            'CONTENT_TYPE' => 'application/json',
        ], $headers), $body);
    }

    public function test_signed_post_is_accepted_and_queued(): void
    {
        Queue::fake();
        $body = $this->payload();

        $this->postWebhook($body, [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'app-secret'),
        ])->assertOk()->assertSee('EVENT_RECEIVED');

        Queue::assertPushed(ProcessMessengerMessage::class, 1);
    }

    public function test_unsigned_post_is_rejected(): void
    {
        Queue::fake();

        $this->postWebhook($this->payload())->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_post_signed_with_wrong_secret_is_rejected(): void
    {
        Queue::fake();
        $body = $this->payload();

        $this->postWebhook($body, [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'not-the-secret'),
        ])->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_post_is_rejected_when_app_secret_is_not_configured(): void
    {
        Queue::fake();
        config(['messenger-bot.app_secret' => null]);
        $body = $this->payload();

        $this->postWebhook($body, [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, ''),
        ])->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_verification_echoes_challenge_for_correct_token(): void
    {
        $this->get('/messenger/webhook?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=abc123')
            ->assertOk()
            ->assertSee('abc123');
    }

    public function test_verification_fails_for_wrong_token(): void
    {
        $this->get('/messenger/webhook?hub_mode=subscribe&hub_verify_token=nope&hub_challenge=abc123')
            ->assertForbidden();
    }

    public function test_verification_fails_when_verify_token_is_not_configured(): void
    {
        config(['messenger-bot.verify_token' => null]);

        $this->get('/messenger/webhook?hub_mode=subscribe&hub_challenge=abc123')
            ->assertForbidden();
    }
}
