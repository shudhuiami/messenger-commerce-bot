<?php

namespace Shudhuiami\MessengerCommerceBot\Tests\Fakes;

use Shudhuiami\MessengerCommerceBot\Contracts\OrderCreator;
use Shudhuiami\MessengerCommerceBot\DataTransferObjects\CustomerData;
use Shudhuiami\MessengerCommerceBot\DataTransferObjects\OrderRequestData;
use Shudhuiami\MessengerCommerceBot\DataTransferObjects\OrderResult;

class FakeOrderCreator implements OrderCreator
{
    /** @var array<int, array{customer: CustomerData, request: OrderRequestData}> */
    public array $created = [];

    public function create(CustomerData $customer, OrderRequestData $request): ?OrderResult
    {
        $this->created[] = ['customer' => $customer, 'request' => $request];
        $id = count($this->created);

        return new OrderResult(id: $id, orderNumber: "ORD-{$id}", total: 0);
    }

    public function find(int|string $orderId): ?OrderResult
    {
        return new OrderResult(id: $orderId, orderNumber: "ORD-{$orderId}", total: 0);
    }
}
