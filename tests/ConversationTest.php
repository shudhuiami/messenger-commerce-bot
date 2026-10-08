<?php

namespace Shudhuiami\MessengerCommerceBot\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Shudhuiami\MessengerCommerceBot\Jobs\ProcessMessengerMessage;
use Shudhuiami\MessengerCommerceBot\Models\MessengerCustomer;
use Shudhuiami\MessengerCommerceBot\Models\MessengerMessage;
use Shudhuiami\MessengerCommerceBot\Models\MessengerSession;
use Shudhuiami\MessengerCommerceBot\Tests\Fakes\FakeOrderCreator;

class ConversationTest extends TestCase
{
    private int $mid = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['graph.facebook.com/*' => Http::response(['message_id' => 'x'])]);
    }

    /** Sends a customer message through the real job and returns the bot's reply. */
    private function say(string $text, string $psid = 'psid-1'): ?string
    {
        $before = MessengerMessage::where('sender_type', 'bot')->max('id') ?? 0;

        ProcessMessengerMessage::dispatchSync([
            'sender' => ['id' => $psid],
            'message' => ['mid' => 'm-' . (++$this->mid), 'text' => $text],
        ]);

        return MessengerMessage::where('sender_type', 'bot')->where('id', '>', $before)->value('message_text');
    }

    private function orders(): FakeOrderCreator
    {
        return app(FakeOrderCreator::class);
    }

    private function goToQuantity(): void
    {
        $this->assertStringContainsString('Do you want to order it?', $this->say('aloe'));
        $this->assertStringContainsString('How many', $this->say('yes'));
    }

    public function test_new_customer_order_flow_collects_name_and_creates_one_order(): void
    {
        $this->goToQuantity();
        $this->assertStringContainsString('full name', $this->say('2'));
        $this->assertStringContainsString('delivery address', $this->say('Rahim Uddin'));
        $this->assertStringContainsString('phone number', $this->say('House 12, Road 5, Dhanmondi, Dhaka'));

        $summary = $this->say('01711-000000');
        $this->assertStringContainsString('Name: Rahim Uddin', $summary);
        $this->assertStringContainsString('Total: 700 BDT', $summary);

        $this->assertStringContainsString('ORD-1', $this->say('YES'));

        $this->assertCount(1, $this->orders()->created);
        $this->assertSame('Rahim Uddin', $this->orders()->created[0]['customer']->name);
        $this->assertSame(2, $this->orders()->created[0]['request']->quantity);

        // A second YES (e.g. double tap) must not place another order.
        $this->say('YES');
        $this->assertCount(1, $this->orders()->created);
    }

    public function test_returning_customer_with_name_skips_name_step(): void
    {
        MessengerCustomer::create(['psid' => 'psid-1', 'name' => 'Karim']);

        $this->goToQuantity();
        $this->assertStringContainsString('delivery address', $this->say('1'));
    }

    public function test_name_step_rejects_digits_and_accepts_non_latin_scripts(): void
    {
        $this->goToQuantity();
        $this->say('1');

        $this->assertStringContainsString('Please send your full name', $this->say('House 12, Road 5'));
        $this->assertStringContainsString('Please send your full name', $this->say('x'));
        $this->assertStringContainsString('delivery address', $this->say('রহিম উদ্দিন'));
        $this->assertSame('রহিম উদ্দিন', MessengerCustomer::where('psid', 'psid-1')->value('name'));

        MessengerCustomer::where('psid', 'psid-1')->update(['name' => null]);
        MessengerSession::query()->update(['current_step' => 'awaiting_name']);
        $this->assertStringContainsString('delivery address', $this->say('محمد علي'));
    }

    public function test_customer_can_change_name_at_confirmation(): void
    {
        MessengerCustomer::create(['psid' => 'psid-1', 'name' => 'Old Name']);

        $this->goToQuantity();
        $this->say('1');
        $this->say('House 12, Road 5, Dhaka');
        $this->assertStringContainsString('Name: Old Name', $this->say('01711000000'));

        $this->assertStringContainsString('What name', $this->say('NAME'));
        $summary = $this->say('New Name');
        $this->assertStringContainsString('Name: New Name', $summary);
        $this->assertStringContainsString('Reply YES to confirm', $summary);

        $this->say('YES');
        $this->assertSame('New Name', $this->orders()->created[0]['customer']->name);
    }

    public function test_session_started_before_name_step_asks_for_name_before_summary(): void
    {
        $customer = MessengerCustomer::create(['psid' => 'psid-1']);
        MessengerSession::create([
            'messenger_customer_id' => $customer->id,
            'current_step' => 'awaiting_phone',
            'product_id' => 1,
            'quantity' => 1,
            'delivery_address' => 'House 12, Road 5, Dhaka',
        ]);

        $this->assertStringContainsString('full name', $this->say('01711000000'));
        $this->assertStringContainsString('Name: Rahim', $this->say('Rahim'));
    }

    public function test_discounted_price_is_shown_once_in_summary(): void
    {
        MessengerCustomer::create(['psid' => 'psid-1', 'name' => 'Karim']);

        $this->say('snake');
        $this->say('yes');
        $this->say('1');
        $this->say('House 12, Road 5, Dhaka');
        $summary = $this->say('01711000000');

        $this->assertStringContainsString('Unit price: 500 BDT (was 650 BDT)', $summary);
        $this->assertStringNotContainsString('Discount price', $summary);
    }

    public function test_bot_stays_silent_while_human_support_is_active(): void
    {
        $this->assertStringContainsString('support team', $this->say('support'));
        Http::assertSentCount(1);

        $this->assertNull($this->say('are you there?'));
        Http::assertSentCount(1);

        // The customer's message is still logged for the human agent.
        $this->assertTrue(MessengerMessage::where('message_text', 'are you there?')->where('sender_type', 'customer')->exists());
    }

    public function test_page_token_is_sent_as_header_not_in_url(): void
    {
        $this->say('hello');

        Http::assertSent(function (Request $request) {
            return str_starts_with($request->url(), 'https://graph.facebook.com/')
                && ! str_contains($request->url(), 'access_token')
                && $request->hasHeader('Authorization', 'Bearer page-token');
        });
    }

    public function test_customer_lock_is_released_after_processing(): void
    {
        $this->say('hello');

        $lock = Cache::lock('messenger-bot:customer:psid-1', 10);
        $this->assertTrue($lock->get());
        $lock->release();
    }
}
