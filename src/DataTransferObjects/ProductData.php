<?php

namespace Shudhuiami\MessengerCommerceBot\DataTransferObjects;

final class ProductData
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly float $price,
        public readonly ?string $slug = null,
        public readonly ?float $oldPrice = null,
        public readonly ?int $stock = null,
        public readonly ?string $description = null,
        public readonly ?string $categoryName = null,
        public readonly ?string $brandName = null,
        public readonly ?string $url = null,
        public readonly bool $isActive = true,
    ) {
    }
}
