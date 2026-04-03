<?php

namespace Romansh\LaravelCreemAgent\Agent;

use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Heartbeat\HeartbeatRunner;
use Romansh\LaravelCreemAgent\Heartbeat\StateManager;

class IntentRouter
{
    public function __construct(
        private CreemCliManager $cli,
        private ?StateManager $stateManager = null,
        private ?HeartbeatRunner $heartbeatRunner = null,
    ) {
        $this->stateManager ??= new StateManager();
    }

    public function route(array $intent): string
    {
        return match ($intent['intent']) {
            'switch_store' => $this->switchStore($intent),
            'query_subscriptions' => $this->querySubscriptions($intent),
            'query_customers' => $this->queryCustomers(),
            'query_transactions' => $this->queryTransactions(),
            'query_products' => $this->queryProducts(),
            'status' => $this->status(),
            'run_heartbeat' => $this->runHeartbeat(),
            'cancel_subscription' => $this->cancelSubscription($intent),
            'create_checkout' => $this->createCheckout($intent),
            'help' => $this->help(),
            default => "I didn't understand that. Type 'help' to see what I can do.",
        };
    }

    private function switchStore(array $intent): string
    {
        $store = $intent['store'];
        $stores = array_keys(config('creem-agent.stores', []));

        if (!in_array($store, $stores)) {
            return "Store '{$store}' not found. Available: " . implode(', ', $stores);
        }

        $this->cli->setActiveStore($store);
        return "Switched to store '{$store}'.";
    }

    private function querySubscriptions(array $intent): string
    {
        try {
            $status = $intent['status'] ?? 'active';
            $result = $this->cli->subscriptions()->listByStatus($status);
            $items = $result['items'] ?? $result['data'] ?? $result;
            $count = is_array($items) ? count($items) : 0;

            $store = $this->cli->getActiveStore();
            return "You have {$count} {$status} subscription(s) in store '{$store}'.";
        } catch (\Throwable $e) {
            return "Error querying subscriptions: {$e->getMessage()}";
        }
    }

    private function queryCustomers(): string
    {
        try {
            $result = $this->cli->customers()->list(1);
            $items = $result['items'] ?? $result['data'] ?? [];
            $total = is_array($result)
                ? ($result['pagination']['total_records'] ?? $result['total'] ?? (is_array($items) ? count($items) : 0))
                : 0;
            return "You have {$total} customer(s) in store '{$this->cli->getActiveStore()}'.";
        } catch (\Throwable $e) {
            return "Error querying customers: {$e->getMessage()}";
        }
    }

    private function queryTransactions(): string
    {
        try {
            $result = $this->cli->transactions()->list([], 1, 5);
            $items = $result['items'] ?? $result['data'] ?? $result;

            if (!is_array($items) || empty($items)) {
                return "No transactions found.";
            }

            $lines = ["Recent transactions in store '{$this->cli->getActiveStore()}':"];
            foreach ($items as $txn) {
                $amount = number_format(($txn['amount'] ?? 0) / 100, 2);
                $currency = $txn['currency'] ?? 'USD';
                $product = $txn['product']['name'] ?? $txn['product_id'] ?? 'Unknown';
                $lines[] = "  • \${$amount} {$currency} — {$product}";
            }
            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error querying transactions: {$e->getMessage()}";
        }
    }

    private function queryProducts(): string
    {
        try {
            $result = $this->cli->products()->list(20);
            $items = $result['items'] ?? $result['data'] ?? $result;

            if (!is_array($items) || empty($items)) {
                return "No products found.";
            }

            $lines = ["Products in store '{$this->cli->getActiveStore()}':"];
            foreach ($items as $prod) {
                $price = number_format(($prod['price'] ?? 0) / 100, 2);
                $name = $prod['name'] ?? 'Unknown';
                $lines[] = "  • {$name} (\${$price})";
            }
            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error querying products: {$e->getMessage()}";
        }
    }

    private function status(): string
    {
        $store = $this->cli->getActiveStore();
        $state = $this->stateManager->load($store);

        $subs = $state['subscriptions'] ?? [];
        $lastCheck = $state['lastCheckAt'] ?? 'never';

        $lines = [
            "Store '{$store}' status:",
            "  Last heartbeat: {$lastCheck}",
            "  Customers: {$state['customerCount']}",
            "  Transactions: {$state['transactionCount']}",
            "  Active subs: " . ($subs['active'] ?? 0),
            "  Trialing: " . ($subs['trialing'] ?? 0),
            "  Past due: " . ($subs['past_due'] ?? 0),
            "  Canceled: " . ($subs['canceled'] ?? 0),
        ];

        return implode("\n", $lines);
    }

    private function runHeartbeat(): string
    {
        try {
            $runner = $this->heartbeatRunner ?? new HeartbeatRunner($this->cli);
            $result = $runner->run($this->cli->getActiveStore());

            if ($result['first_run']) {
                return "First heartbeat completed — initial snapshot created.";
            }

            $count = count($result['changes']);
            if ($count === 0) {
                return "Heartbeat complete — no changes detected.";
            }

            $messages = array_map(fn($c) => "  • {$c['message']}", $result['changes']);
            return "Heartbeat complete — {$count} change(s):\n" . implode("\n", $messages);
        } catch (\Throwable $e) {
            return "Heartbeat failed: {$e->getMessage()}";
        }
    }

    private function cancelSubscription(array $intent): string
    {
        try {
            $this->cli->subscriptions()->cancel($intent['id'], true);
            return "Subscription {$intent['id']} scheduled for cancellation at period end.";
        } catch (\Throwable $e) {
            return "Failed to cancel: {$e->getMessage()}";
        }
    }

    private function createCheckout(array $intent): string
    {
        try {
            $result = $this->cli->execute('checkouts', 'create', ['product_id' => $intent['product_id']]);
            $url = $result['checkout_url'] ?? 'N/A';
            return "Checkout created: {$url}";
        } catch (\Throwable $e) {
            return "Failed to create checkout: {$e->getMessage()}";
        }
    }

    private function help(): string
    {
        return implode("\n", [
            "I can help you with:",
            "  • 'status' — Store overview",
            "  • 'how many active subscriptions?' — Subscription counts",
            "  • 'any payment issues?' — Check past_due subscriptions",
            "  • 'recent transactions' — Latest sales",
            "  • 'products' — List products",
            "  • 'how many customers?' — Customer count",
            "  • 'run heartbeat' — Check for changes now",
            "  • 'switch to store X' — Change active store",
            "  • 'cancel subscription sub_XXX' — Cancel a subscription",
            "  • 'create checkout for product prod_XXX' — Create checkout URL",
        ]);
    }
}
