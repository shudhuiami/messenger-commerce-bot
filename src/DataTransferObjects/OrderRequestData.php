<?php

namespace Shudhuiami\MessengerCommerceBot\DataTransferObjects;

final class OrderRequestData
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly string $deliveryAddress,
        public readonly string $phone,
        public readonly string $notes,
    ) {
    }
}
