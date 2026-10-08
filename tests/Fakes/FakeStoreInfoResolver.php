<?php

namespace Shudhuiami\MessengerCommerceBot\Tests\Fakes;

use Shudhuiami\MessengerCommerceBot\Contracts\StoreInfoResolver;

class FakeStoreInfoResolver implements StoreInfoResolver
{
    public function siteName(): string
    {
        return 'Test Store';
    }

    public function contactPhone(): ?string
    {
        return null;
    }

    public function address(): ?string
    {
        return null;
    }

    public function activeCategoryNames(): array
    {
        return ['Indoor Plants'];
    }

    public function activeBrandNames(): array
    {
        return [];
    }
}
