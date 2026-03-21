<?php

namespace Romansh\LaravelCreemAgent\Heartbeat;

use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Illuminate\Support\Facades\Log;

class SubscriptionChecker
{
    private const STATUSES = ['active', 'trialing', 'past_due', 'paused', 'canceled', 'expired', 'scheduled_cancel'];

    public function __construct(private CreemCliManager $cli) {}

    public function check(array $previousState, ?string $store = null): array
    {
        $counts = [];
        $allSubscriptions = [];

        foreach (self::STATUSES as $status) {
            try {
                $result = $this->cli->subscriptions()->listByStatus($status, 100, $store);
                $items = $result['items'] ?? $result['data'] ?? $result;

                if (!is_array($items)) {
                    $items = [];
                }

                $counts[$status] = count($items);

                foreach ($items as $sub) {
                    $subId = $sub['id'] ?? null;
                    if ($subId) {
                        $allSubscriptions[$subId] = $status;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("[CreemAgent] Failed to fetch {$status} subscriptions", ['error' => $e->getMessage()]);
                $counts[$status] = $previousState['subscriptions'][$status] ?? 0;
            }
        }

        // Detect transitions
        $transitions = [];
        $knownPrevious = $previousState['knownSubscriptions'] ?? [];

        foreach ($allSubscriptions as $subId => $currentStatus) {
            $previousStatus = $knownPrevious[$subId] ?? null;

            if ($previousStatus === null) {
                $transitions[] = [
                    'subscription_id' => $subId,
                    'from' => null,
                    'to' => $currentStatus,
                    'type' => 'new',
                ];
            } elseif ($previousStatus !== $currentStatus) {
                $transitions[] = [
                    'subscription_id' => $subId,
                    'from' => $previousStatus,
                    'to' => $currentStatus,
                    'type' => $this->classifyTransition($previousStatus, $currentStatus),
                ];
            }
        }

        // Detect disappeared subscriptions (were known, now gone from all statuses)
        foreach ($knownPrevious as $subId => $oldStatus) {
            if (!isset($allSubscriptions[$subId])) {
                $transitions[] = [
                    'subscription_id' => $subId,
                    'from' => $oldStatus,
                    'to' => 'unknown',
                    'type' => 'disappeared',
                ];
            }
        }

        return [
            'counts' => $counts,
            'knownSubscriptions' => $allSubscriptions,
            'transitions' => $transitions,
        ];
    }

    private function classifyTransition(string $from, string $to): string
    {
        if ($to === 'past_due') return 'warning';
        if (in_array($to, ['canceled', 'scheduled_cancel', 'expired'])) return 'alert';
        if ($from === 'paused' && $to === 'active') return 'good_news';
        if ($to === 'active' || $to === 'trialing') return 'good_news';
        return 'info';
    }
}
