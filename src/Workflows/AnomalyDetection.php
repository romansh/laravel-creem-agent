<?php

namespace Romansh\LaravelCreemAgent\Workflows;

use Romansh\LaravelCreemAgent\Events\HeartbeatCompleted;
use Romansh\LaravelCreemAgent\Notifications\WorkflowAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Cache;

class AnomalyDetection
{
    public function handle(HeartbeatCompleted $event): void
    {
        $state = $event->state;
        $store = $event->store;
        $cacheKey = "creem_agent_anomaly_{$store}";

        $history = Cache::get($cacheKey, []);
        $history[] = [
            'at' => now()->toIso8601String(),
            'transactions' => $state['transactionCount'] ?? 0,
            'active_subs' => $state['subscriptions']['active'] ?? 0,
        ];

        // Keep last 24 data points
        $history = array_slice($history, -24);
        Cache::put($cacheKey, $history, 86400 * 7);

        if (count($history) < 3) {
            return;
        }

        // Simple anomaly: active subscriptions dropped >20% from previous check
        $current = end($history);
        $previous = $history[count($history) - 2];

        if ($previous['active_subs'] > 0) {
            $dropPercent = (($previous['active_subs'] - $current['active_subs']) / $previous['active_subs']) * 100;

            if ($dropPercent > 20) {
                $message = sprintf(
                    'Active subscriptions dropped %.0f%% (%d → %d)',
                    $dropPercent,
                    $previous['active_subs'],
                    $current['active_subs']
                );

                Notification::route('slack', config('creem-agent.notifications.slack_webhook_url'))
                    ->notify(new WorkflowAlert($store, 'AnomalyDetection', $message, [
                        'drop_percent' => $dropPercent,
                        'from' => $previous['active_subs'],
                        'to' => $current['active_subs'],
                    ]));
            }
        }
    }
}
