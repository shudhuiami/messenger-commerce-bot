<?php

namespace Shudhuiami\MessengerCommerceBot\DataTransferObjects;

final class OrderResult
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $orderNumber,
        public readonly float $total,
    ) {
    }
}
