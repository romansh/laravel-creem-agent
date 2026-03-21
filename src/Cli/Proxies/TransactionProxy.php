<?php

namespace Romansh\LaravelCreemAgent\Cli\Proxies;

use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class TransactionProxy
{
    public function __construct(private CreemCliManager $manager) {}

    /**
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20, ?string $store = null): array
    {
        return $this->manager->execute('transactions', 'list', [
            'filters' => $filters,
            'page' => $page,
            'limit' => $limit,
        ], $store);
    }

    public function find(string $id, ?string $store = null): array
    {
        return $this->manager->execute('transactions', 'get', ['id' => $id], $store);
    }

    public function byCustomer(string $customerId, int $page = 1, int $limit = 20, ?string $store = null): array
    {
        return $this->list(['customer_id' => $customerId], $page, $limit, $store);
    }

    public function byProduct(string $productId, int $page = 1, int $limit = 20, ?string $store = null): array
    {
        return $this->list(['product_id' => $productId], $page, $limit, $store);
    }

    public function byOrder(string $orderId, int $page = 1, int $limit = 20, ?string $store = null): array
    {
        return $this->list(['order_id' => $orderId], $page, $limit, $store);
    }
}
