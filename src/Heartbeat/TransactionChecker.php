<?php

namespace Romansh\LaravelCreemAgent\Heartbeat;

use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Illuminate\Support\Facades\Log;

class TransactionChecker
{
    public function __construct(private CreemCliManager $cli) {}

    public function check(array $previousState, ?string $store = null): array
    {
        try {
            $transactions = $this->cli->transactions()->list([], 1, 20, $store);
        } catch (\Throwable $e) {
            Log::warning('[CreemAgent] Failed to fetch transactions', ['error' => $e->getMessage()]);
            return [
                'newTransactions' => [],
                'latestId' => $previousState['lastTransactionId'],
                'totalCount' => $previousState['transactionCount'],
            ];
        }

        $items = $transactions['items'] ?? $transactions['data'] ?? $transactions;
        if (!is_array($items) || empty($items)) {
            return [
                'newTransactions' => [],
                'latestId' => $previousState['lastTransactionId'],
                'totalCount' => $previousState['transactionCount'],
            ];
        }

        $latestId = $items[0]['id'] ?? null;
        $newTransactions = [];

        if ($latestId !== $previousState['lastTransactionId']) {
            foreach ($items as $txn) {
                $txnId = $txn['id'] ?? null;
                if ($txnId === $previousState['lastTransactionId']) {
                    break;
                }
                $newTransactions[] = [
                    'id' => $txnId,
                    'amount' => ($txn['amount'] ?? 0) / 100,
                    'currency' => $txn['currency'] ?? 'USD',
                    'status' => $txn['status'] ?? 'unknown',
                    'product' => $txn['product']['name'] ?? $txn['product_id'] ?? 'Unknown',
                    'customer_email' => $txn['customer']['email'] ?? $txn['customer_email'] ?? 'unknown',
                    'created_at' => $txn['created_at'] ?? null,
                ];
            }
        }

        return [
            'newTransactions' => $newTransactions,
            'latestId' => $latestId,
            'totalCount' => $transactions['pagination']['total_records']
                ?? $transactions['total']
                ?? count($items) + ($previousState['transactionCount'] ?? 0),
        ];
    }
}
