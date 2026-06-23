<?php

namespace Shudhuiami\MessengerCommerceBot;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shudhuiami\MessengerCommerceBot\Contracts\OrderCreator;
use Shudhuiami\MessengerCommerceBot\Contracts\ProductRepository;
use Shudhuiami\MessengerCommerceBot\Contracts\StoreInfoResolver;
use Shudhuiami\MessengerCommerceBot\Http\Controllers\MessengerWebhookController;

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

        Route::middleware('web')->group(function () use ($webhookPath) {
            Route::get($webhookPath, [MessengerWebhookController::class, 'verify']);
            Route::post($webhookPath, [MessengerWebhookController::class, 'receive']);
        });
    }
}
