<?php

namespace Romansh\LaravelCreemAgent\Cli;

use Romansh\LaravelCreem\Creem;

class ArtisanCliDriver implements CliDriverInterface
{
    public function execute(string $resource, string $action, array $args = [], ?string $profile = null): array
    {
        $creem = Creem::profile($profile ?? 'default');

        return match ($resource) {
            'transactions' => $this->handleTransactions($creem, $action, $args),
            'subscriptions' => $this->handleSubscriptions($creem, $action, $args),
            'customers' => $this->handleCustomers($creem, $action, $args),
            'products' => $this->handleProducts($creem, $action, $args),
            'checkouts' => $this->handleCheckouts($creem, $action, $args),
            'discounts' => $this->handleDiscounts($creem, $action, $args),
            default => throw new \InvalidArgumentException("Unknown resource: {$resource}"),
        };
    }

    private function handleTransactions(Creem $creem, string $action, array $args): array
    {
        $service = $creem->transactions();

        return match ($action) {
            'list' => $service->list(
                array_filter(
                    $args['filters'] ?? [
                        'customer_id' => $args['customer'] ?? null,
                        'product_id' => $args['product'] ?? null,
                        'order_id' => $args['order'] ?? null,
                        'status' => $args['status'] ?? null,
                    ],
                    static fn ($value) => $value !== null && $value !== ''
                ),
                (int) ($args['page'] ?? 1),
                (int) ($args['limit'] ?? 20)
            ),
            'get' => $service->find($args[0] ?? $args['id'] ?? ''),
            default => throw new \InvalidArgumentException("Unknown action: {$action}"),
        };
    }

    private function handleSubscriptions(Creem $creem, string $action, array $args): array
    {
        $service = $creem->subscriptions();

        return match ($action) {
            'list' => $service->list(
                (int) ($args['page'] ?? 1),
                (int) ($args['limit'] ?? 20),
                array_filter(
                    $args['filters'] ?? ['status' => $args['status'] ?? null],
                    static fn ($value) => $value !== null && $value !== ''
                )
            ),
            'get' => $service->find($args[0] ?? $args['id'] ?? ''),
            'cancel' => $service->cancel(
                $args[0] ?? $args['id'] ?? '',
                ($args['mode'] ?? 'immediate') === 'scheduled'
            ),
            'pause' => $service->pause($args[0] ?? $args['id'] ?? ''),
            'resume' => $service->resume($args[0] ?? $args['id'] ?? ''),
            default => throw new \InvalidArgumentException("Unknown action: {$action}"),
        };
    }

    private function handleCustomers(Creem $creem, string $action, array $args): array
    {
        $service = $creem->customers();

        return match ($action) {
            'list' => $service->list((int) ($args['page'] ?? 1), (int) ($args['limit'] ?? 20)),
            'get' => isset($args['email'])
                ? $service->findByEmail($args['email'])
                : $service->find($args[0] ?? $args['id'] ?? ''),
            'billing' => ['portal_url' => $service->createPortalLink($args[0] ?? $args['id'] ?? '')],
            default => throw new \InvalidArgumentException("Unknown action: {$action}"),
        };
    }

    private function handleProducts(Creem $creem, string $action, array $args): array
    {
        $service = $creem->products();

        return match ($action) {
            'list' => $service->list((int) ($args['page'] ?? 1), (int) ($args['limit'] ?? 20)),
            'get' => $service->find($args[0] ?? $args['id'] ?? ''),
            'create' => $service->create($args),
            default => throw new \InvalidArgumentException("Unknown action: {$action}"),
        };
    }

    private function handleCheckouts(Creem $creem, string $action, array $args): array
    {
        $service = $creem->checkouts();

        return match ($action) {
            'create' => $service->create($args),
            'get' => $service->find($args[0] ?? $args['id'] ?? ''),
            default => throw new \InvalidArgumentException("Unknown action: {$action}"),
        };
    }

    private function handleDiscounts(Creem $creem, string $action, array $args): array
    {
        $service = $creem->discounts();

        return match ($action) {
            'get' => $service->find($args[0] ?? $args['id'] ?? ''),
            default => throw new \InvalidArgumentException("Unknown action: {$action}"),
        };
    }
}
