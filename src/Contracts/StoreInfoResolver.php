<?php

namespace Shudhuiami\MessengerCommerceBot\Contracts;

/**
 * Implement this to expose basic store info the bot grounds its free-text
 * AI replies in, so it never invents a category/brand/contact detail that
 * doesn't actually exist in your store.
 */
interface StoreInfoResolver
{
    public function siteName(): string;

    public function contactPhone(): ?string;

    public function address(): ?string;

    /** @return string[] */
    public function activeCategoryNames(): array;

    /** @return string[] */
    public function activeBrandNames(): array;
}
