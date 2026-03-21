<?php

namespace Romansh\LaravelCreemAgent\Cli\Proxies;

use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class CustomerProxy
{
    public function __construct(private CreemCliManager $manager) {}

    public function list(int $limit = 20, ?string $store = null): array
    {
        return $this->manager->execute('customers', 'list', ['limit' => $limit], $store);
    }

    public function find(string $id, ?string $store = null): array
    {
        return $this->manager->execute('customers', 'get', ['id' => $id], $store);
    }

    public function findByEmail(string $email, ?string $store = null): array
    {
        return $this->manager->execute('customers', 'get', ['email' => $email], $store);
    }

    public function billingPortal(string $id, ?string $store = null): array
    {
        return $this->manager->execute('customers', 'billing', ['id' => $id], $store);
    }
}
