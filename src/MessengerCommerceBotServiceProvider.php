<?php

namespace Shudhuiami\MessengerCommerceBot;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shudhuiami\MessengerCommerceBot\Contracts\OrderCreator;
use Shudhuiami\MessengerCommerceBot\Contracts\ProductRepository;
use Shudhuiami\MessengerCommerceBot\Contracts\StoreInfoResolver;
use Shudhuiami\MessengerCommerceBot\Http\Controllers\MessengerWebhookController;
use Shudhuiami\MessengerCommerceBot\Http\Middleware\VerifyMessengerSignature;

class MessengerCommerceBotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/messenger-bot.php', 'messenger-bot');

        foreach ([
            'product_repository' => ProductRepository::class,
            'order_creator' => OrderCreator::class,
            'store_info_resolver' => StoreInfoResolver::class,
        ] as $configKey => $contract) {
            $this->app->bind($contract, function () use ($configKey, $contract) {
                $implementation = config("messenger-bot.bindings.{$configKey}");

                if (! $implementation) {
                    throw new \RuntimeException(
                        "No implementation bound for {$contract}. Publish the config with `php artisan vendor:publish --tag=messenger-bot-config` and set `bindings.{$configKey}` to your own class implementing it."
                    );
                }

                return $this->app->make($implementation);
            });
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/messenger-bot.php' => config_path('messenger-bot.php'),
        ], 'messenger-bot-config');

        $webhookPath = config('messenger-bot.webhook_path', 'messenger/webhook');

        // Registered outside the "web" group: Meta's server-to-server calls
        // need no session or cookies, and skipping the group means no CSRF
        // exemption is needed. POSTs are authenticated by signature instead.
        Route::get($webhookPath, [MessengerWebhookController::class, 'verify']);
        Route::post($webhookPath, [MessengerWebhookController::class, 'receive'])
            ->middleware(VerifyMessengerSignature::class);
    }
}
