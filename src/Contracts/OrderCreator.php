<?php

namespace Shudhuiami\MessengerCommerceBot\Contracts;

use Shudhuiami\MessengerCommerceBot\DataTransferObjects\CustomerData;
use Shudhuiami\MessengerCommerceBot\DataTransferObjects\OrderRequestData;
use Shudhuiami\MessengerCommerceBot\DataTransferObjects\OrderResult;

/**
 * Implement this to create a real order in your host app's own order
 * system (whatever tables/columns that uses). The bot calls this exactly
 * once per confirmed order — it already guards against calling it twice
 * for the same conversation, so this method does not need its own
 * idempotency check. Return null if the order cannot be created (e.g.
 * out of stock, product no longer active) so the bot can tell the
 * customer gracefully instead of pretending it worked.
 */
interface OrderCreator
{
    public function create(CustomerData $customer, OrderRequestData $request): ?OrderResult;

    /**
     * Look up an already-created order by id (used when the bot detects a
     * conversation already has an order linked, so it can still show the
     * real order number/total instead of a blank duplicate confirmation).
     */
    public function find(int|string $orderId): ?OrderResult;
}
