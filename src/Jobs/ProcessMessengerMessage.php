<?php

namespace Shudhuiami\MessengerCommerceBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Shudhuiami\MessengerCommerceBot\Models\MessengerCustomer;
use Shudhuiami\MessengerCommerceBot\Models\MessengerMessage;
use Shudhuiami\MessengerCommerceBot\Services\MessengerBotService;
use Shudhuiami\MessengerCommerceBot\Services\MessengerService;

class ProcessMessengerMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $event)
    {
    }

    public function handle(MessengerBotService $bot, MessengerService $messenger): void
    {
        $psid = $this->event['sender']['id'] ?? null;
        $text = $this->event['message']['text'] ?? null;
        $messageId = $this->event['message']['mid'] ?? null;

        if (! $psid || ! $text) {
            return;
        }

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
