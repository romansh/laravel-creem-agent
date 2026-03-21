<?php

namespace Romansh\LaravelCreemAgent\Cli\Proxies;

use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class ProductProxy
{
    public function __construct(private CreemCliManager $manager) {}

    public function list(int $limit = 20, ?string $store = null): array
    {
        return $this->manager->execute('products', 'list', ['limit' => $limit], $store);
    }

    public function find(string $id, ?string $store = null): array
    {
        return $this->manager->execute('products', 'get', ['id' => $id], $store);
    }

    public function create(array $data, ?string $store = null): array
    {
        return $this->manager->execute('products', 'create', $data, $store);
    }
}
