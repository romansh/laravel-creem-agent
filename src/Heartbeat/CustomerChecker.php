<?php

namespace Romansh\LaravelCreemAgent\Heartbeat;

use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Illuminate\Support\Facades\Log;

class CustomerChecker
{
    public function __construct(private CreemCliManager $cli) {}

    public function check(array $previousState, ?string $store = null): array
    {
        try {
            $result = $this->cli->customers()->list(1, $store);
            $total = $result['total'] ?? count($result['items'] ?? $result['data'] ?? $result);
        } catch (\Exception $e) {
            Log::warning('[CreemAgent] Failed to fetch customers', ['error' => $e->getMessage()]);
            $total = $previousState['customerCount'] ?? 0;
        }

        $newCustomers = max(0, $total - ($previousState['customerCount'] ?? 0));

        return [
            'totalCount' => $total,
            'newCount' => $newCustomers,
        ];
    }
}
