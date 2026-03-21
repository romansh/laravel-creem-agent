<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Heartbeat\ChangeDetector;
use Romansh\LaravelCreemAgent\Heartbeat\HeartbeatRunner;
use Romansh\LaravelCreemAgent\Heartbeat\Reporter;
use Romansh\LaravelCreemAgent\Heartbeat\StateManager;
use Romansh\LaravelCreemAgent\Heartbeat\SubscriptionChecker;
use Romansh\LaravelCreemAgent\Heartbeat\TransactionChecker;
use Romansh\LaravelCreemAgent\Heartbeat\CustomerChecker;
use Romansh\LaravelCreemAgent\Events\HeartbeatCompleted;
use Romansh\LaravelCreemAgent\Events\ChangeDetected;

class HeartbeatRunnerTest extends TestCase
{
    public function test_first_run_dispatches_heartbeat_completed_and_creates_state()
    {
        $saved = new class {
            public array $states = [];
        };
        $events = [];

        $stateManager = new class($saved) extends StateManager {
            public function __construct(private object $saved) {}

            public function load(string $store): array
            {
                return $this->defaults();
            }

            public function save(string $store, array $state): void
            {
                $this->saved->states[$store] = $state;
            }
        };

        $transactionChecker = new class extends TransactionChecker {
            public function __construct() {}

            public function check(array $previousState, ?string $store = null): array
            {
                return ['latestId' => null, 'totalCount' => 0, 'newTransactions' => []];
            }
        };

        $subscriptionChecker = new class extends SubscriptionChecker {
            public function __construct() {}

            public function check(array $previousState, ?string $store = null): array
            {
                return ['counts' => ['active' => 0], 'knownSubscriptions' => [], 'transitions' => []];
            }
        };

        $customerChecker = new class extends CustomerChecker {
            public function __construct() {}

            public function check(array $previousState, ?string $store = null): array
            {
                return ['totalCount' => 0, 'newCount' => 0];
            }
        };

        $reporter = new class extends Reporter {
            public bool $firstRunReported = false;

            public function __construct() {}

            public function reportFirstRun(string $store, array $state): void
            {
                $this->firstRunReported = true;
            }
        };

        $runner = new HeartbeatRunner(
            new CreemCliManager(fn() => false),
            $stateManager,
            $transactionChecker,
            $subscriptionChecker,
            $customerChecker,
            new ChangeDetector(),
            $reporter,
            function (object $event) use (&$events): void {
                $events[] = $event;
            }
        );

        $res = $runner->run('default');

        $this->assertTrue($res['first_run']);
        $this->assertTrue($reporter->firstRunReported);
        $this->assertArrayHasKey('default', $saved->states);
        $this->assertInstanceOf(HeartbeatCompleted::class, $events[0]);
    }

    public function test_detects_changes_and_dispatches_change_events()
    {
        $events = [];

        $stateManager = new class extends StateManager {
            public function __construct() {}

            public function load(string $store): array
            {
                return [
                    'lastCheckAt' => '2026-03-19T10:00:00Z',
                    'lastTransactionId' => 'txn_old',
                    'transactionCount' => 1,
                    'customerCount' => 0,
                    'subscriptions' => ['active' => 1],
                    'knownSubscriptions' => ['sub_1' => 'active'],
                ];
            }

            public function isFirstRun(array $state): bool
            {
                return false;
            }

            public function save(string $store, array $state): void
            {
            }
        };

        $transactionChecker = new class extends TransactionChecker {
            public function __construct() {}

            public function check(array $previousState, ?string $store = null): array
            {
                return ['latestId' => 'txn_new', 'totalCount' => 2, 'newTransactions' => [['id' => 'txn_new']]];
            }
        };

        $subscriptionChecker = new class extends SubscriptionChecker {
            public function __construct() {}

            public function check(array $previousState, ?string $store = null): array
            {
                return ['counts' => ['active' => 0], 'knownSubscriptions' => [], 'transitions' => [['type' => 'alert']]];
            }
        };

        $customerChecker = new class extends CustomerChecker {
            public function __construct() {}

            public function check(array $previousState, ?string $store = null): array
            {
                return ['totalCount' => 2, 'newCount' => 2];
            }
        };

        $changeDetector = new class extends ChangeDetector {
            public function detect(array $previousState, array $txnResult, array $subResult, array $custResult): array
            {
                return [['message' => 'change detected', 'severity' => 'alert']];
            }
        };

        $runner = new HeartbeatRunner(
            new CreemCliManager(fn() => false),
            $stateManager,
            $transactionChecker,
            $subscriptionChecker,
            $customerChecker,
            $changeDetector,
            new Reporter(),
            function (object $event) use (&$events): void {
                $events[] = $event;
            }
        );

        $res = $runner->run('default');

        $this->assertFalse($res['first_run']);
        $this->assertCount(2, $events);
        $this->assertInstanceOf(HeartbeatCompleted::class, $events[0]);
        $this->assertInstanceOf(ChangeDetected::class, $events[1]);
    }
}
