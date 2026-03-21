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

class HeartbeatRunnerMoreTest extends TestCase
{
    public function test_run_all_stores_returns_results_for_each_store()
    {
        config()->set('creem-agent.stores', ['one' => [], 'two' => []]);

        $stateManager = new class extends StateManager {
            public function __construct() {}

            public function load(string $store): array
            {
                return $this->defaults();
            }

            public function save(string $store, array $state): void
            {
            }
        };

        $results = (new HeartbeatRunner(
            new CreemCliManager(fn() => false),
            $stateManager,
            new class extends TransactionChecker {
                public function __construct() {}
                public function check(array $previousState, ?string $store = null): array { return ['latestId' => null, 'totalCount' => 0, 'newTransactions' => []]; }
            },
            new class extends SubscriptionChecker {
                public function __construct() {}
                public function check(array $previousState, ?string $store = null): array { return ['counts' => ['active' => 0], 'knownSubscriptions' => [], 'transitions' => []]; }
            },
            new class extends CustomerChecker {
                public function __construct() {}
                public function check(array $previousState, ?string $store = null): array { return ['totalCount' => 0, 'newCount' => 0]; }
            },
            new ChangeDetector(),
            new Reporter()
        ))->runAllStores();

        $this->assertArrayHasKey('one', $results);
        $this->assertArrayHasKey('two', $results);
        $this->assertTrue($results['one']['first_run']);
    }
}
