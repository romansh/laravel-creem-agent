<?php

namespace Romansh\LaravelCreemAgent\Heartbeat;

class ChangeDetector
{
    public function detect(array $previousState, array $txnResult, array $subResult, array $custResult): array
    {
        $changes = [];

        // New transactions
        foreach ($txnResult['newTransactions'] as $txn) {
            $changes[] = [
                'type' => 'new_transaction',
                'severity' => 'good_news',
                'data' => $txn,
                'message' => sprintf(
                    'New sale: %s ($%.2f) from %s',
                    $txn['product'],
                    $txn['amount'],
                    $txn['customer_email']
                ),
            ];
        }

        // Subscription transitions
        foreach ($subResult['transitions'] as $transition) {
            $changes[] = [
                'type' => 'subscription_' . $transition['type'],
                'severity' => $transition['type'],
                'data' => $transition,
                'message' => $this->formatTransition($transition),
            ];
        }

        // New customers
        if ($custResult['newCount'] > 0) {
            $changes[] = [
                'type' => 'new_customers',
                'severity' => 'good_news',
                'data' => ['count' => $custResult['newCount'], 'total' => $custResult['totalCount']],
                'message' => sprintf('%d new customer(s) (total: %d)', $custResult['newCount'], $custResult['totalCount']),
            ];
        }

        // Churn spike detection
        $cancellations = array_filter($subResult['transitions'], fn($t) => in_array($t['to'], ['canceled', 'scheduled_cancel']));
        if (count($cancellations) >= 3) {
            $changes[] = [
                'type' => 'churn_spike',
                'severity' => 'alert',
                'data' => ['count' => count($cancellations)],
                'message' => sprintf('Churn spike: %d cancellations in this cycle', count($cancellations)),
            ];
        }

        return $changes;
    }

    private function formatTransition(array $transition): string
    {
        $subId = $transition['subscription_id'];
        $from = $transition['from'] ?? 'new';
        $to = $transition['to'] ?? 'unknown';

        return match ($transition['type']) {
            'new' => "New subscription: {$subId} ({$to})",
            'warning' => "Payment issue: {$subId} moved from {$from} to {$to}",
            'alert' => "Subscription lost: {$subId} moved from {$from} to {$to}",
            'good_news' => "Good news: {$subId} moved from {$from} to {$to}",
            'disappeared' => "Subscription {$subId}: previously {$from}, now no longer returned by the API",
            default => "Subscription {$subId}: {$from} → {$to}",
        };
    }
}
