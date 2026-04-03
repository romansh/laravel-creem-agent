<?php

namespace Romansh\LaravelCreemAgent\Heartbeat;

use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Illuminate\Support\Facades\Log;

class CustomerChecker
{
    public function __construct(private CreemCliManager $cli) {}

    public function check(array $previousState, ?string $store = null): array
    {
        $total = $previousState['customerCount'] ?? 0;

        try {
            $result = $this->cli->customers()->list(1, $store);
            $items = $result['items'] ?? $result['data'] ?? [];

            if (is_array($result)) {
                $total = $result['pagination']['total_records']
                    ?? $result['total']
                    ?? (is_array($items) ? count($items) : $total);
            }
        } catch (\Throwable $e) {
            Log::warning('[CreemAgent] Failed to fetch customers', ['error' => $e->getMessage()]);
        }

        $newCustomers = max(0, $total - ($previousState['customerCount'] ?? 0));

        return [
            'totalCount' => $total,
            'newCount' => $newCustomers,
        ];
    }
}
