<?php

namespace Shudhuiami\MessengerCommerceBot\Services;

use Shudhuiami\MessengerCommerceBot\Contracts\StoreInfoResolver;
use Shudhuiami\MessengerCommerceBot\DataTransferObjects\ProductData;
use Shudhuiami\MessengerCommerceBot\Models\MessengerConversation;
use Shudhuiami\MessengerCommerceBot\Models\MessengerCustomer;
use Shudhuiami\MessengerCommerceBot\Models\MessengerSession;

class MessengerBotService
{
    public function __construct(
        private MessengerProductSearchService $search,
        private MessengerOrderService $orders,
        private GroqService $groq,
        private StoreInfoResolver $storeInfo,
    ) {
    }

    public function handle(MessengerCustomer $customer, MessengerConversation $conversation, string $rawText): string
    {
        $text = trim($rawText);
        $normalized = mb_strtolower($text);

        if ($normalized === 'help') {
            return $this->helpText();
        }

        if ($normalized === 'support') {
            $conversation->update(['status' => 'human_support']);
            return "You're now connected with our support team. A team member will contact you shortly.";
        }

        if ($normalized === 'cancel') {
            $this->sessionFor($customer)->reset();
            $conversation->update(['status' => 'cancelled']);
            return 'Your order has been cancelled. Send a product name anytime to start again.';
        }

        $session = $this->sessionFor($customer);

        return match ($session->current_step) {
            'awaiting_quantity' => $this->handleQuantity($customer, $session, $text),
            'awaiting_name' => $this->handleName($customer, $session, $text),
            'awaiting_address' => $this->handleAddress($session, $text),
            'awaiting_phone' => $this->handlePhone($customer, $session, $text),
            'awaiting_confirmation' => $this->handleConfirmation($customer, $conversation, $session, $normalized),
            default => $this->handleIdle($session, $text, $normalized),
        };
    }

    private function sessionFor(MessengerCustomer $customer): MessengerSession
    {
        return MessengerSession::firstOrCreate(['messenger_customer_id' => $customer->id]);
    }

    private function handleIdle(MessengerSession $session, string $text, string $normalized): string
    {
        if (in_array($normalized, ['hi', 'hello', 'hey', 'start', 'hi there'], true)) {
            return "Welcome! 🌿 Tell me a product name, brand, or category you're looking for and I'll help you find it.";
        }

        $cart = $session->cart_data ?? [];

        if (ctype_digit($normalized) && ! empty($cart['search_results'])) {
            $index = ((int) $normalized) - 1;
            $productId = $cart['search_results'][$index] ?? null;

            if ($productId) {
                return $this->selectProduct($session, (int) $productId);
            }

            return 'Please reply with a valid product number from the list above.';
        }

        if (in_array($normalized, ['yes', 'yeah', 'y', 'order', 'order it', 'i want it'], true) && ! empty($cart['last_single_match'])) {
            return $this->selectProduct($session, (int) $cart['last_single_match']);
        }

        return $this->handleProductQuery($session, $text);
    }

    private function selectProduct(MessengerSession $session, int $productId): string
    {
        $product = $this->search->find($productId);

        if (! $product) {
            $session->update(['cart_data' => null]);
            return 'Sorry, that product is no longer available. Please try another product name.';
        }

        $session->update([
            'product_id' => $product->id,
            'current_step' => 'awaiting_quantity',
            'cart_data' => null,
        ]);

        return "Great choice: {$product->title} - {$product->price} BDT.\nHow many would you like to order? Please send a quantity.";
    }

    private function handleQuantity(MessengerCustomer $customer, MessengerSession $session, string $text): string
    {
        if (! preg_match('/\d+/', $text, $m)) {
            return 'Please send a valid quantity, e.g. 2.';
        }

        $qty = (int) $m[0];

        if ($qty < 1 || $qty > 99) {
            return 'Please send a quantity between 1 and 99.';
        }

        $product = $session->product_id ? $this->search->find($session->product_id) : null;

        if (! $product || ! $product->isActive) {
            $session->reset();
            return 'Sorry, that product is no longer available. Please send a product name to search again.';
        }

        if (! is_null($product->stock) && $product->stock < $qty) {
            return "Sorry, only {$product->stock} left in stock. Please send a smaller quantity.";
        }

        if (empty($customer->name)) {
            $session->update(['quantity' => $qty, 'current_step' => 'awaiting_name']);

            return 'Got it. What is your full name for the order?';
        }

        $session->update(['quantity' => $qty, 'current_step' => 'awaiting_address']);

        return 'Got it. What is your delivery address?';
    }

    private function handleName(MessengerCustomer $customer, MessengerSession $session, string $text): string
    {
        $name = trim($text);

        // Any script's letters are fine; digits are not, which also catches an
        // address or phone number sent one step early.
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || ! preg_match('/\p{L}/u', $name) || preg_match('/\p{N}/u', $name)) {
            return 'Please send your full name for the order.';
        }

        $customer->update(['name' => $name]);

        // Reached from the confirmation step (NAME to change it) or from a
        // session that already has address and phone: go straight back to
        // the summary instead of asking for them again.
        if ($session->delivery_address && $session->customer_phone) {
            $session->update(['current_step' => 'awaiting_confirmation']);

            return $this->orderSummary($customer, $session);
        }

        $session->update(['current_step' => 'awaiting_address']);

        return 'Thanks. What is your delivery address?';
    }

    private function handleAddress(MessengerSession $session, string $text): string
    {
        if (mb_strlen(trim($text)) < 5) {
            return 'Please send your full delivery address.';
        }

        $session->update(['delivery_address' => trim($text), 'current_step' => 'awaiting_phone']);

        return 'Thanks. What is your phone number?';
    }

    private function handlePhone(MessengerCustomer $customer, MessengerSession $session, string $text): string
    {
        $digits = preg_replace('/\D/', '', $text);

        if (mb_strlen($digits) < 7 || mb_strlen($digits) > 15) {
            return 'Please send a valid phone number.';
        }

        if (empty($customer->name)) {
            $session->update(['customer_phone' => trim($text), 'current_step' => 'awaiting_name']);

            return 'Thanks. What is your full name for the order?';
        }

        $session->update(['customer_phone' => trim($text), 'current_step' => 'awaiting_confirmation']);

        return $this->orderSummary($customer, $session);
    }

    private function orderSummary(MessengerCustomer $customer, MessengerSession $session): string
    {
        $product = $session->product_id ? $this->search->find($session->product_id) : null;

        if (! $product) {
            $session->reset();
            return 'Sorry, something went wrong. Please send a product name to start again.';
        }

        $qty = (int) $session->quantity;
        $total = $product->price * $qty;

        $lines = [
            'Order summary:',
            "Name: {$customer->name}",
            "Product: {$product->title}",
            "Quantity: {$qty}",
            ! is_null($product->oldPrice) && $product->oldPrice > $product->price
                ? "Unit price: {$product->price} BDT (was {$product->oldPrice} BDT)"
                : "Unit price: {$product->price} BDT",
        ];

        $lines[] = "Total: {$total} BDT";
        $lines[] = "Delivery address: {$session->delivery_address}";
        $lines[] = "Phone: {$session->customer_phone}";
        $lines[] = '';
        $lines[] = 'Reply YES to confirm, NAME to change your name, or CANCEL to cancel.';

        return implode("\n", $lines);
    }

    private function handleConfirmation(MessengerCustomer $customer, MessengerConversation $conversation, MessengerSession $session, string $normalized): string
    {
        if (in_array($normalized, ['yes', 'y', 'confirm'], true)) {
            $order = $this->orders->createOrder($customer, $session, $conversation);

            if (! $order) {
                return 'Sorry, we could not complete your order — the item may be out of stock. Please send CANCEL and try again.';
            }

            $session->reset();

            return "Thank you! Your order #{$order->orderNumber} has been placed. Total: {$order->total} BDT. We'll contact you soon to confirm delivery.";
        }

        // CANCEL is handled globally in handle() before any step runs.

        if (in_array($normalized, ['name', 'change name'], true)) {
            $session->update(['current_step' => 'awaiting_name']);

            return 'Sure. What name should we put on the order?';
        }

        return 'Please reply YES to confirm your order, NAME to change your name, or CANCEL to cancel.';
    }

    private function handleProductQuery(MessengerSession $session, string $text): string
    {
        $normalized = mb_strtolower(trim($text));

        if (preg_match('/\b(latest|new)\s+products?\b/i', $normalized)) {
            return $this->replyWithList($session, $this->search->latest(), 'Here are our latest products:');
        }

        if (preg_match('/\bbest[\s-]?sell(ing|ers?)?\b/i', $normalized)) {
            $products = $this->search->bestSelling();

            if (empty($products)) {
                return "Sorry, we don't have best-seller data right now.";
            }

            return $this->replyWithList($session, $products, 'Our best-selling products:');
        }

        if (preg_match('/\bcheapest\s+(.+)/i', $text, $m)) {
            $term = trim($m[1], " ?.");
            $products = $this->search->cheapestInCategory($term);

            if (empty($products)) {
                return 'Sorry, I could not find that product. Please try another product name or category.';
            }

            return $this->replyWithList($session, $products, "Cheapest in \"{$term}\":");
        }

        // Generic browsing questions ("what products do you have", "show me the list of
        // products") aren't a product name to search for — treat them as "show me everything".
        if (preg_match('/\b(what\s+(kind\s+of\s+)?products?|list\s+of\s+products?|show\s+me\s+(the\s+)?products?|what\s+do\s+you\s+(sell|have|offer)|product\s+list|catalog|what.?s\s+available)\b/i', $normalized)) {
            return $this->replyWithList($session, $this->search->latest(8), 'Here are some of our products:');
        }

        $term = $this->extractSearchTerm($text);

        if ($term !== '') {
            $products = $this->search->search($term);

            if (! empty($products)) {
                return $this->replyWithList($session, $products, null);
            }
        }

        if ($routed = $this->routeViaClassifiedIntent($session, $text)) {
            return $routed;
        }

        return $this->askAi($text)
            ?? 'Sorry, I could not find that product. Please try another product name or category.';
    }

    /**
     * Lets Groq classify messages the English regex rules above can't parse
     * (different language, unusual phrasing) into a small set of known
     * actions, then dispatches into the same deterministic handlers used for
     * English — so a non-English "show me all products" gets a real product
     * list, not just a conversational deflection, and an "other" classification
     * (or any failure/missing key) safely falls through to the free-text
     * Groq reply instead of ever touching order data.
     */
    private function routeViaClassifiedIntent(MessengerSession $session, string $text): ?string
    {
        $systemPrompt = <<<PROMPT
            You are an intent classifier for an e-commerce store's chat bot. The customer message may be in English or another language, including romanized/informal spelling. Classify it into exactly one of these and respond with ONLY a JSON object, no other text:

            1. {"intent": "show_all_products"} - the customer wants to see the full list/catalog of products.
            2. {"intent": "search_product", "term": "<English search term>"} - the customer is asking about a specific product, category, or brand. Translate the core subject into a short English search term.
            3. {"intent": "other"} - anything else: general questions, store policy, greetings, chit-chat.

            Respond with ONLY the JSON object, nothing else.
            PROMPT;

        $classified = $this->groq->askJson($systemPrompt, $text);

        if (! $classified) {
            return null;
        }

        if (($classified['intent'] ?? null) === 'show_all_products') {
            return $this->replyWithList($session, $this->search->latest(8), 'Here are some of our products:');
        }

        if (($classified['intent'] ?? null) === 'search_product' && ! empty($classified['term'])) {
            $products = $this->search->search($classified['term']);

            if (! empty($products)) {
                return $this->replyWithList($session, $products, null);
            }
        }

        return null;
    }

    /**
     * Catch-all for anything the rule-based matching above doesn't recognize
     * (general questions, chit-chat, store info). Grounded strictly in real
     * store data so it can't invent products, prices, or policies. Returns
     * null if no API key is configured or the request fails — callers must
     * fall back to a rule-based reply in that case.
     */
    private function askAi(string $text): ?string
    {
        $siteName = $this->storeInfo->siteName();
        $phone = $this->storeInfo->contactPhone() ?? 'not listed';
        $address = $this->storeInfo->address() ?? 'not listed';
        $categories = implode(', ', $this->storeInfo->activeCategoryNames()) ?: 'none listed';
        $brands = implode(', ', $this->storeInfo->activeBrandNames()) ?: 'none listed';
        $highlights = implode("\n", array_map(
            fn (ProductData $p) => $this->search->summaryLine($p),
            $this->search->latest(5)
        )) ?: 'none listed';

        $systemPrompt = <<<PROMPT
            You are a friendly customer support assistant for {$siteName}, an online store. Reply to the customer's message in 1-3 short sentences, in a warm, natural, human tone.

            Rules you must follow strictly:
            - Only use the facts listed below. Never invent product names, prices, stock levels, policies, or contact details that are not given here.
            - If you don't have the information to answer, say so honestly and suggest the customer send a product name, a category name, or "HELP" to see what you can do.
            - Do not attempt to take, confirm, or process an order yourself. If the customer wants to order something, tell them to send the product name and you'll guide them through it.
            - Keep it brief and conversational, like a real support agent texting back. No markdown, no bullet lists.

            Store facts:
            - Categories carried: {$categories}
            - Brands carried: {$brands}
            - Some products currently available:
            {$highlights}
            - Contact phone: {$phone}
            - Address: {$address}
            PROMPT;

        return $this->groq->ask($systemPrompt, $text);
    }

    private function extractSearchTerm(string $text): string
    {
        $t = trim($text);
        $t = preg_replace('/[?.!]+$/', '', $t) ?? $t;

        $patterns = [
            '/^(price|cost)\s+(of|for)\s+/i',
            '/^(do you have|have you got|got any)\s+/i',
            '/^is\s+/i',
            '/\s+available$/i',
            '/^stock\s+(of|for)\s+/i',
            '/^show\s+me\s+/i',
            '/^details?\s+(of|for|about)\s+/i',
            '/^products?\s+(from|by|in)\s+/i',
            '/^(tell me about|info(rmation)?\s*(on|about))\s+/i',
        ];

        foreach ($patterns as $pattern) {
            $t = preg_replace($pattern, '', $t) ?? $t;
        }

        return trim($t);
    }

    /** @param ProductData[] $products */
    private function replyWithList(MessengerSession $session, array $products, ?string $header): string
    {
        if (empty($products)) {
            $session->update(['cart_data' => null]);
            return 'Sorry, I could not find that product. Please try another product name or category.';
        }

        if (count($products) === 1) {
            $product = $products[0];
            $session->update(['cart_data' => ['last_single_match' => $product->id, 'search_results' => null]]);
            return $this->search->describe($product) . "\n\nDo you want to order it? Reply YES to order.";
        }

        $lines = [];
        foreach (array_values($products) as $i => $product) {
            $lines[] = ($i + 1) . '. ' . $this->search->summaryLine($product);
        }

        $session->update(['cart_data' => [
            'search_results' => array_values(array_map(fn (ProductData $p) => $p->id, $products)),
            'last_single_match' => null,
        ]]);

        $intro = $header ?? 'I found a few matching products:';

        return $intro . "\n" . implode("\n", $lines) . "\n\nPlease reply with the product number you want.";
    }

    private function helpText(): string
    {
        return implode("\n", [
            'Here is how I can help:',
            '- Send a product name to search.',
            '- Send a category name to view products.',
            '- Reply with a product number to select it.',
            '- Reply CANCEL to cancel an order in progress.',
            '- Reply SUPPORT to talk to our team.',
        ]);
    }
}
