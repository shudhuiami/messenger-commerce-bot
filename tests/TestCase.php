<?php

namespace Shudhuiami\MessengerCommerceBot\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Shudhuiami\MessengerCommerceBot\MessengerCommerceBotServiceProvider;
use Shudhuiami\MessengerCommerceBot\Tests\Fakes\FakeOrderCreator;
use Shudhuiami\MessengerCommerceBot\Tests\Fakes\FakeProductRepository;
use Shudhuiami\MessengerCommerceBot\Tests\Fakes\FakeStoreInfoResolver;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [MessengerCommerceBotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('messenger-bot.verify_token', 'verify-me');
        $app['config']->set('messenger-bot.app_secret', 'app-secret');
        $app['config']->set('messenger-bot.page_access_token', 'page-token');
        $app['config']->set('messenger-bot.groq.api_key', null);
        $app['config']->set('messenger-bot.bindings', [
            'product_repository' => FakeProductRepository::class,
            'order_creator' => FakeOrderCreator::class,
            'store_info_resolver' => FakeStoreInfoResolver::class,
        ]);

        // Singletons so tests can inspect what the bot did through them.
        $app->singleton(FakeOrderCreator::class);
        $app->singleton(FakeProductRepository::class);
    }
}
