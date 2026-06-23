<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Facebook Messenger
    |--------------------------------------------------------------------------
    */

    'verify_token' => env('MESSENGER_VERIFY_TOKEN'),
    'page_access_token' => env('MESSENGER_PAGE_ACCESS_TOKEN'),
    'graph_version' => env('MESSENGER_GRAPH_VERSION', 'v19.0'),
    'page_username' => env('MESSENGER_PAGE_USERNAME'),

    /*
    |--------------------------------------------------------------------------
    | Webhook route
    |--------------------------------------------------------------------------
    |
    | Registered automatically under the "web" middleware group. Must be
    | excluded from CSRF verification in your app's bootstrap/app.php:
    |
    |   $middleware->validateCsrfTokens(except: ['messenger/webhook']);
    |
    */

    'webhook_path' => 'messenger/webhook',

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
