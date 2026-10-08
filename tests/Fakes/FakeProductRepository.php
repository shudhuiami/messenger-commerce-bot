<?php

namespace Shudhuiami\MessengerCommerceBot\Tests\Fakes;

use Shudhuiami\MessengerCommerceBot\Contracts\ProductRepository;
use Shudhuiami\MessengerCommerceBot\DataTransferObjects\ProductData;

class FakeProductRepository implements ProductRepository
{
    /** @var ProductData[] */
    public array $products;

    public function __construct()
    {
        $this->products = [
            new ProductData(id: 1, title: 'Aloe Vera Plant', price: 350, stock: 10, categoryName: 'Indoor Plants'),
            new ProductData(id: 2, title: 'Snake Plant', price: 500, oldPrice: 650, stock: 5, categoryName: 'Indoor Plants'),
        ];
    }

    public function find(int $id): ?ProductData
    {
        foreach ($this->products as $product) {
            if ($product->id === $id) {
                return $product;
            }
        }

        return null;
    }

    public function searchByTitle(string $query, int $limit): array
    {
        return $this->matching(fn (ProductData $p) => stripos($p->title, $query) !== false, $limit);
    }

    public function searchByBrand(string $query, int $limit): array
    {
        return [];
    }

    public function searchByCategory(string $query, int $limit): array
    {
        return $this->matching(fn (ProductData $p) => $p->categoryName && stripos($p->categoryName, $query) !== false, $limit);
    }

    public function searchByDescription(string $query, int $limit): array
    {
        return [];
    }

    public function latest(int $limit): array
    {
        return array_slice($this->products, 0, $limit);
    }

    public function bestSelling(int $limit): array
    {
        return [];
    }

    public function cheapestInCategory(string $categoryName, int $limit): array
    {
        return $this->searchByCategory($categoryName, $limit);
    }

    private function matching(callable $filter, int $limit): array
    {
        return array_slice(array_values(array_filter($this->products, $filter)), 0, $limit);
    }
}
