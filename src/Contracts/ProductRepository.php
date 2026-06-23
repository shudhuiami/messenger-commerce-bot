<?php

namespace Shudhuiami\MessengerCommerceBot\Contracts;

use Shudhuiami\MessengerCommerceBot\DataTransferObjects\ProductData;

/**
 * Implement this against your host app's actual product/catalog tables.
 * The bot never queries your database directly — it only ever goes
 * through these methods, so it works regardless of your schema/column
 * names. All search methods should only return active/published/in-stock
 * products per your own app's conventions; the bot does not filter further.
 */
interface ProductRepository
{
    public function find(int $id): ?ProductData;

    /**
     * Should rank exact title matches before partial matches where possible
     * (the caller does not re-rank the results you return).
     *
     * @return ProductData[]
     */
    public function searchByTitle(string $query, int $limit): array;

    /** @return ProductData[] */
    public function searchByBrand(string $query, int $limit): array;

    /** @return ProductData[] */
    public function searchByCategory(string $query, int $limit): array;

    /** @return ProductData[] */
    public function searchByDescription(string $query, int $limit): array;

    /** @return ProductData[] */
    public function latest(int $limit): array;

    /** @return ProductData[] */
    public function bestSelling(int $limit): array;

    /** @return ProductData[] */
    public function cheapestInCategory(string $categoryName, int $limit): array;
}
