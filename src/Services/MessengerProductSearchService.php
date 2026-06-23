<?php

namespace Shudhuiami\MessengerCommerceBot\Services;

use Shudhuiami\MessengerCommerceBot\Contracts\ProductRepository;
use Shudhuiami\MessengerCommerceBot\DataTransferObjects\ProductData;

class MessengerProductSearchService
{
    public function __construct(private ProductRepository $repository)
    {
    }

    /**
     * Search across title, brand, category, and description, ranked by
     * relevance (title matches first), deduplicated, limited.
     *
     * @return ProductData[]
     */
    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $merged = [];
        $seen = [];

        $appendUnseen = function (array $products) use (&$merged, &$seen): void {
            foreach ($products as $product) {
                if (! isset($seen[$product->id])) {
                    $seen[$product->id] = true;
                    $merged[] = $product;
                }
            }
        };

        $appendUnseen($this->repository->searchByTitle($query, $limit));
        $appendUnseen($this->repository->searchByBrand($query, $limit));
        $appendUnseen($this->repository->searchByCategory($query, $limit));
        $appendUnseen($this->repository->searchByDescription($query, $limit));

        return array_slice($merged, 0, $limit);
    }

    /** @return ProductData[] */
    public function cheapestInCategory(string $categoryName, int $limit = 5): array
    {
        return $this->repository->cheapestInCategory($categoryName, $limit);
    }

    /** @return ProductData[] */
    public function latest(int $limit = 5): array
    {
        return $this->repository->latest($limit);
    }

    /** @return ProductData[] */
    public function bestSelling(int $limit = 5): array
    {
        return $this->repository->bestSelling($limit);
    }

    public function find(int $id): ?ProductData
    {
        return $this->repository->find($id);
    }

    /**
     * Multi-line product detail reply using only fields that are actually set.
     */
    public function describe(ProductData $product): string
    {
        $lines = [$product->title];

        if (! is_null($product->oldPrice) && $product->oldPrice > $product->price) {
            $lines[] = "Price: {$product->oldPrice} BDT (was) → {$product->price} BDT (now)";
        } else {
            $lines[] = "Price: {$product->price} BDT";
        }

        if (! is_null($product->stock)) {
            $lines[] = 'Stock: ' . ($product->stock > 0 ? 'Available' : 'Out of stock');
        }

        if (! empty($product->description)) {
            $short = mb_strlen($product->description) > 120
                ? mb_substr($product->description, 0, 120) . '...'
                : $product->description;
            $lines[] = $short;
        }

        if ($product->categoryName) {
            $lines[] = "Category: {$product->categoryName}";
        }

        if ($product->brandName) {
            $lines[] = "Brand: {$product->brandName}";
        }

        if ($product->url) {
            $lines[] = "Link: {$product->url}";
        }

        return implode("\n", $lines);
    }

    public function summaryLine(ProductData $product): string
    {
        return "{$product->title} - {$product->price} BDT";
    }
}
