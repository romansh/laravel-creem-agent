<?php

namespace Romansh\LaravelCreemAgent\Heartbeat;

use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Events\HeartbeatCompleted;
use Romansh\LaravelCreemAgent\Events\ChangeDetected;
use Illuminate\Support\Facades\Log;

class HeartbeatRunner
{
    private StateManager $stateManager;
    private TransactionChecker $transactionChecker;
    private SubscriptionChecker $subscriptionChecker;
    private CustomerChecker $customerChecker;
    private ChangeDetector $changeDetector;
    private Reporter $reporter;
    private ?\Closure $eventDispatcher;

    public function __construct(
        CreemCliManager $cli,
        ?StateManager $stateManager = null,
        ?TransactionChecker $transactionChecker = null,
        ?SubscriptionChecker $subscriptionChecker = null,
        ?CustomerChecker $customerChecker = null,
        ?ChangeDetector $changeDetector = null,
        ?Reporter $reporter = null,
        ?\Closure $eventDispatcher = null,
    )
    {
        $this->stateManager = $stateManager ?? new StateManager();
        $this->transactionChecker = $transactionChecker ?? new TransactionChecker($cli);
        $this->subscriptionChecker = $subscriptionChecker ?? new SubscriptionChecker($cli);
        $this->customerChecker = $customerChecker ?? new CustomerChecker($cli);
        $this->changeDetector = $changeDetector ?? new ChangeDetector();
        $this->reporter = $reporter ?? new Reporter();
        $this->eventDispatcher = $eventDispatcher;
    }

    public function run(string $store): array
    {
        Log::info("[CreemAgent] Running heartbeat for store '{$store}'");

        // Step 1: Load previous state
        $previousState = $this->stateManager->load($store);
        $isFirstRun = $this->stateManager->isFirstRun($previousState);

        // Step 2: Check transactions
        $txnResult = $this->transactionChecker->check($previousState, $store);

        // Step 3: Check subscription health
        $subResult = $this->subscriptionChecker->check($previousState, $store);

        // Step 4: Check customers
        $custResult = $this->customerChecker->check($previousState, $store);

        // Step 5: Update state
        $newState = [
            'lastCheckAt' => now()->toIso8601String(),
            'lastTransactionId' => $txnResult['latestId'] ?? $previousState['lastTransactionId'],
            'transactionCount' => $txnResult['totalCount'] ?? $previousState['transactionCount'],
            'customerCount' => $custResult['totalCount'] ?? $previousState['customerCount'],
            'subscriptions' => $subResult['counts'] ?? $previousState['subscriptions'],
            'knownSubscriptions' => $subResult['knownSubscriptions'] ?? $previousState['knownSubscriptions'],
        ];

        $this->stateManager->save($store, $newState);

        // Step 6: Report
        if ($isFirstRun) {
            $this->reporter->reportFirstRun($store, $newState);
            $changes = [];
        } else {
            $changes = $this->changeDetector->detect($previousState, $txnResult, $subResult, $custResult);
            $this->reporter->reportChanges($store, $changes);
        }

        // Dispatch events
        $this->dispatchEvent(new HeartbeatCompleted($store, $newState, $changes));
        foreach ($changes as $change) {
            $this->dispatchEvent(new ChangeDetected($store, $change));
        }

        Log::info("[CreemAgent] Heartbeat complete for store '{$store}'", [
            'first_run' => $isFirstRun,
            'changes' => count($changes),
        ]);

        return [
            'store' => $store,
            'first_run' => $isFirstRun,
            'state' => $newState,
            'changes' => $changes,
        ];
    }

    public function runAllStores(): array
    {
        $stores = array_keys(config('creem-agent.stores', ['default' => []]));
        $results = [];

        foreach ($stores as $store) {
            $results[$store] = $this->run($store);
        }

        return $results;
    }

    private function dispatchEvent(object $event): void
    {
        if ($this->eventDispatcher !== null) {
            ($this->eventDispatcher)($event);
            return;
        }

        event($event);
    }
}
