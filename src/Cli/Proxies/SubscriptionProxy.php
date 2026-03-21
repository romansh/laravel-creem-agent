<?php

namespace Romansh\LaravelCreemAgent\Cli\Proxies;

use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class SubscriptionProxy
{
    public function __construct(private CreemCliManager $manager) {}

    /**
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20, ?string $store = null): array
    {
        return $this->manager->execute('subscriptions', 'list', [
            'filters' => $filters,
            'page' => $page,
            'limit' => $limit,
        ], $store);
    }

    public function listByStatus(string $status, int $limit = 100, ?string $store = null): array
    {
        return $this->list(['status' => $status], 1, $limit, $store);
    }

    public function find(string $id, ?string $store = null): array
    {
        return $this->manager->execute('subscriptions', 'get', ['id' => $id], $store);
    }

    public function cancel(string $id, bool $atPeriodEnd = true, ?string $store = null): array
    {
        return $this->manager->execute('subscriptions', 'cancel', [
            'id' => $id,
            'mode' => $atPeriodEnd ? 'scheduled' : 'immediate',
        ], $store);
    }

    public function pause(string $id, ?string $store = null): array
    {
        return $this->manager->execute('subscriptions', 'pause', ['id' => $id], $store);
    }

    public function resume(string $id, ?string $store = null): array
    {
        return $this->manager->execute('subscriptions', 'resume', ['id' => $id], $store);
    }
}
