<?php

namespace Romansh\LaravelCreemAgent\Workflows;

use Romansh\LaravelCreemAgent\Events\HeartbeatCompleted;
use Romansh\LaravelCreemAgent\Notifications\WorkflowAlert;
use Illuminate\Support\Facades\Notification;

class RevenueDigest
{
    public function handle(HeartbeatCompleted $event): void
    {
        $changes = $event->changes;
        $transactions = array_filter($changes, fn($c) => ($c['type'] ?? '') === 'new_transaction');

        if (empty($transactions)) {
            return;
        }

        $totalAmount = array_sum(array_map(fn($t) => $t['data']['amount'] ?? 0, $transactions));
        $count = count($transactions);

        $message = sprintf(
            '%d new transaction(s) totaling $%.2f',
            $count,
            $totalAmount
        );

        Notification::route('slack', config('creem-agent.notifications.slack_webhook_url'))
            ->notify(new WorkflowAlert($event->store, 'RevenueDigest', $message, [
                'transaction_count' => $count,
                'total_amount' => $totalAmount,
            ]));
    }
}
