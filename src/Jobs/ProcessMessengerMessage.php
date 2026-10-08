<?php

namespace Shudhuiami\MessengerCommerceBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Shudhuiami\MessengerCommerceBot\Models\MessengerCustomer;
use Shudhuiami\MessengerCommerceBot\Models\MessengerMessage;
use Shudhuiami\MessengerCommerceBot\Services\MessengerBotService;
use Shudhuiami\MessengerCommerceBot\Services\MessengerService;

class ProcessMessengerMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Allows a few re-queues when another worker holds this customer's lock
     * for longer than we're willing to block.
     */
    public int $tries = 5;

    public function __construct(public array $event)
    {
    }

    public function handle(MessengerBotService $bot, MessengerService $messenger): void
    {
        $psid = $this->event['sender']['id'] ?? null;
        $text = $this->event['message']['text'] ?? null;

        if (! $psid || ! $text) {
            return;
        }

        // One message per customer at a time: with several queue workers, two
        // quick messages (e.g. a double-tapped YES) would otherwise both read
        // the same session/conversation state and could create two orders.
        // All state is loaded inside the lock so each message sees the result
        // of the previous one.
        try {
            Cache::store(config('messenger-bot.lock_store'))
                ->lock("messenger-bot:customer:{$psid}", 60)
                ->block(15, fn () => $this->process($bot, $messenger, $psid, $text));
        } catch (LockTimeoutException) {
            $this->release(5);
        }
    }

    private function process(MessengerBotService $bot, MessengerService $messenger, string $psid, string $text): void
    {
        $messageId = $this->event['message']['mid'] ?? null;

        // Meta may redeliver the same event; the unique message_id column guards this too,
        // but checking first avoids running the bot logic twice for nothing.
        if ($messageId && MessengerMessage::where('message_id', $messageId)->exists()) {
            return;
        }

        $customer = MessengerCustomer::firstOrCreate(['psid' => $psid]);

        $conversation = $customer->conversations()
            ->whereIn('status', ['active', 'human_support'])
            ->latest()
            ->first()
            ?? $customer->conversations()->create(['status' => 'active']);

        $conversation->messages()->create([
            'message_id' => $messageId,
            'sender_type' => 'customer',
            'message_text' => $text,
            'raw_payload' => $this->event,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // A human agent has taken over: keep logging the customer's messages
        // for them but stay silent. Staff hand the conversation back by
        // setting its status to something else (e.g. "active" or "closed").
        if ($conversation->status === 'human_support') {
            return;
        }

        try {
            $reply = $bot->handle($customer, $conversation, $text);
        } catch (\Throwable $e) {
            Log::error('Messenger bot handling failed.', ['psid' => $psid, 'message' => $e->getMessage()]);
            $reply = 'Sorry, something went wrong. Please try again in a moment.';
        }

        $conversation->messages()->create([
            'sender_type' => 'bot',
            'message_text' => $reply,
        ]);

        $messenger->sendText($psid, $reply);
    }
}
