<?php

namespace Shudhuiami\MessengerCommerceBot\DataTransferObjects;

final class CustomerData
{
    public function __construct(
        public readonly string $psid,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
    ) {
    }
}
