# Messenger Commerce Bot

Facebook Messenger auto-reply, product Q&A, and order-taking bot for Laravel e-commerce apps. Schema-agnostic: the package never queries your database directly — you implement three small contracts that map onto whatever your `Product`/`Order`/`Settings` tables actually look like.

## What it does

- Verifies and receives the official Messenger webhook (`GET`/`POST /messenger/webhook`).
- Rule-based product Q&A: search by name/brand/category/description, "latest products", "cheapest X", numbered multi-match selection — all deterministic, no AI involved.
- A guided order flow entirely in chat: product → quantity → delivery address → phone → confirmation summary → YES/CANCEL. Order creation always goes through **your** `OrderCreator` implementation — the package never computes prices or writes order rows itself.
- Optional free Groq AI fallback (https://console.groq.com, no card required) for anything the rule-based matching can't parse — including a multilingual intent classifier so non-English customers asking "show me everything" or about a specific product still get the real product list / real product data, not just a conversational guess. AI is **never** used for order creation or pricing.
- Admin-friendly: conversations and messages are stored in their own tables (`messenger_customers`, `messenger_conversations`, `messenger_messages`, `messenger_sessions`) so you can build your own admin UI on top.

## Install (within this monorepo, as a path package)

Already wired into the root `composer.json` as a path repository. Run:

```bash
composer install
```

## Install (in a fresh project)

1. Copy the `packages/messenger-commerce-bot` directory into the new project.
2. Add the path repository + requirement to the new project's `composer.json`:
   ```json
   "repositories": [
       { "type": "path", "url": "packages/messenger-commerce-bot", "options": { "symlink": true } }
   ],
   "require": {
       "shudhuiami/messenger-commerce-bot": "*"
   }
   ```
3. `composer update`
4. `php artisan vendor:publish --tag=messenger-bot-config`
5. Implement the three contracts (see below), then point `config/messenger-bot.php`'s `bindings` array at your classes.
6. Add to `.env`:
   ```
   MESSENGER_VERIFY_TOKEN=
   MESSENGER_PAGE_ACCESS_TOKEN=
   MESSENGER_GRAPH_VERSION=v19.0
   MESSENGER_PAGE_USERNAME=
   GROQ_API_KEY=
   GROQ_MODEL=llama-3.3-70b-versatile
   ```
7. Exempt the webhook from CSRF in `bootstrap/app.php`:
   ```php
   $middleware->validateCsrfTokens(except: ['messenger/webhook']);
   ```
8. `php artisan migrate`

## The three contracts you implement

### `ProductRepository`

Maps the bot's product search/listing onto your actual `Product` (or equivalent) model/table. Every method returns `ProductData` DTOs, never your Eloquent models directly — translate your model's fields into the DTO in your adapter.

```php
class EloquentProductRepository implements ProductRepository
{
    public function find(int $id): ?ProductData { /* ... */ }
    public function searchByTitle(string $query, int $limit): array { /* ... */ }
    public function searchByBrand(string $query, int $limit): array { /* ... */ }
    public function searchByCategory(string $query, int $limit): array { /* ... */ }
    public function searchByDescription(string $query, int $limit): array { /* ... */ }
    public function latest(int $limit): array { /* ... */ }
    public function bestSelling(int $limit): array { /* ... */ }
    public function cheapestInCategory(string $categoryName, int $limit): array { /* ... */ }
}
```

Only return active/published/in-stock products per your own conventions — the bot does not filter further.

### `OrderCreator`

Creates a real order using **your** order system. The bot calls `create()` exactly once per confirmed order (it already guards against double-calling for the same conversation) and `find()` to redisplay an existing order's details if a duplicate confirmation slips through.

```php
class EloquentOrderCreator implements OrderCreator
{
    public function create(CustomerData $customer, OrderRequestData $request): ?OrderResult { /* ... */ }
    public function find(int|string $orderId): ?OrderResult { /* ... */ }
}
```

Return `null` from `create()` if the order can't be placed (out of stock, product inactive, etc.) — the bot tells the customer gracefully instead of pretending it worked.

### `StoreInfoResolver`

Grounds the AI fallback's free-text replies in real store facts so it can't invent a category, brand, or contact detail.

```php
class SettingsStoreInfoResolver implements StoreInfoResolver
{
    public function siteName(): string { /* ... */ }
    public function contactPhone(): ?string { /* ... */ }
    public function address(): ?string { /* ... */ }
    public function activeCategoryNames(): array { /* ... */ }
    public function activeBrandNames(): array { /* ... */ }
}
```

## Safety notes

- Order creation, pricing, and stock checks always go through your own `OrderCreator`/`ProductRepository` implementations — the AI fallback (Groq) is only ever used to generate the *text* of an answer to an open-ended question, or to classify a non-English message into one of three known intents that then dispatch into the same deterministic handlers as English. It never writes to your database or computes a price.
- Without `GROQ_API_KEY` configured, the bot still works — it just falls back to a canned "could not find that product" message for anything outside its rule-based patterns instead of an AI-generated reply.
