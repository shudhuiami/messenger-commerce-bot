<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Facebook Messenger
    |--------------------------------------------------------------------------
    */

    'verify_token' => env('MESSENGER_VERIFY_TOKEN'),
    // Your Meta app's App Secret (App settings → Basic). Used to verify the
    // X-Hub-Signature-256 header on every webhook POST; without it the
    // webhook rejects all deliveries.
    'app_secret' => env('MESSENGER_APP_SECRET'),
    'page_access_token' => env('MESSENGER_PAGE_ACCESS_TOKEN'),
    'graph_version' => env('MESSENGER_GRAPH_VERSION', 'v19.0'),
    'page_username' => env('MESSENGER_PAGE_USERNAME'),

    /*
    |--------------------------------------------------------------------------
    | Webhook route
    |--------------------------------------------------------------------------
    |
    | Registered automatically outside the "web" middleware group, so no CSRF
    | exemption is needed. POSTs must carry a valid X-Hub-Signature-256
    | (see app_secret above).
    |
    */

    'webhook_path' => 'messenger/webhook',

    /*
    |--------------------------------------------------------------------------
    | Per-customer lock
    |--------------------------------------------------------------------------
    |
    | Messages from the same customer are processed one at a time using an
    | atomic cache lock, so parallel queue workers can't race on the same
    | order (e.g. a double-tapped YES creating two orders). The cache store
    | must support atomic locks and be shared by all workers (redis,
    | database, memcached, dynamodb — not "array", and "file" only on a
    | single server). Null uses your default cache store.
    |
    */

    'lock_store' => env('MESSENGER_LOCK_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Groq (free-tier AI fallback for open-ended questions)
    |--------------------------------------------------------------------------
    */

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contract bindings
    |--------------------------------------------------------------------------
    |
    | Point these at your own classes implementing each contract for your
    | app's actual product/order/store-info schema. See the package README
    | for what each contract expects.
    */

    'bindings' => [
        'product_repository' => null,
        'order_creator' => null,
        'store_info_resolver' => null,
    ],

];
