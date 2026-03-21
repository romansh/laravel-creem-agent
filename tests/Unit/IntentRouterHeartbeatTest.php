<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\IntentRouter;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Cli\Proxies\CustomerProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy;
use Romansh\LaravelCreemAgent\Heartbeat\HeartbeatRunner;
use Romansh\LaravelCreemAgent\Heartbeat\StateManager;

class IntentRouterHeartbeatTest extends TestCase
{
    public function test_unknown_intent_and_run_heartbeat_variants()
    {
        $this->assertStringContainsString(
            "didn't understand",
            (new IntentRouter($this->getMockBuilder(CreemCliManager::class)->disableOriginalConstructor()->getMock()))
                ->route(['intent' => 'unknown'])
        );

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cli->method('getActiveStore')->willReturn('default');

        $runner = new class extends HeartbeatRunner {
            private int $call = 0;

            public function __construct() {}

            public function run(string $store): array
            {
                $this->call++;

                return match ($this->call) {
                    1 => ['first_run' => true, 'changes' => [], 'state' => []],
                    2 => ['first_run' => false, 'changes' => [], 'state' => []],
                    default => ['first_run' => false, 'changes' => [['message' => 'new sale']], 'state' => []],
                };
            }
        };

        $router = new IntentRouter($cli, null, $runner);

        $first = $router->route(['intent' => 'run_heartbeat']);
        $none = $router->route(['intent' => 'run_heartbeat']);
        $changes = $router->route(['intent' => 'run_heartbeat']);

        $this->assertStringContainsString('initial snapshot created', $first);
        $this->assertStringContainsString('no changes detected', $none);
        $this->assertStringContainsString('1 change(s)', $changes);
    }

    public function test_status_uses_injected_state_manager_defaults()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cli->method('getActiveStore')->willReturn('fallback');

        $stateManager = new class extends StateManager {
            public function __construct() {}

            public function load(string $store): array
            {
                return [
                    'lastCheckAt' => 'never',
                    'customerCount' => 0,
                    'transactionCount' => 0,
                    'subscriptions' => [],
                ];
            }
        };

        $result = (new IntentRouter($cli, $stateManager))->route(['intent' => 'status']);

        $this->assertStringContainsString("Store 'fallback' status:", $result);
        $this->assertStringContainsString('Canceled: 0', $result);
    }

    public function test_run_heartbeat_returns_failure_message_when_runner_throws()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cli->method('getActiveStore')->willReturn('default');

        $runner = new class extends HeartbeatRunner {
            public function __construct() {}

            public function run(string $store): array
            {
                throw new \RuntimeException('runner exploded');
            }
        };

        $result = (new IntentRouter($cli, null, $runner))->route(['intent' => 'run_heartbeat']);

        $this->assertStringContainsString('Heartbeat failed: runner exploded', $result);
    }

    public function test_query_customers_transactions_and_products_error_paths()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cli->method('getActiveStore')->willReturn('default');

        $cust = $this->getMockBuilder(CustomerProxy::class)->disableOriginalConstructor()->getMock();
        $cust->method('list')->will($this->throwException(new \RuntimeException('customers fail')));

        $tx = $this->getMockBuilder(TransactionProxy::class)->disableOriginalConstructor()->getMock();
        $tx->method('list')->will($this->throwException(new \RuntimeException('transactions fail')));

        $prod = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\ProductProxy::class)->disableOriginalConstructor()->getMock();
        $prod->method('list')->will($this->throwException(new \RuntimeException('products fail')));

        $cli->method('customers')->willReturn($cust);
        $cli->method('transactions')->willReturn($tx);
        $cli->method('products')->willReturn($prod);

        $router = new IntentRouter($cli);
        $this->assertStringContainsString('Error querying customers', $router->route(['intent' => 'query_customers']));
        $this->assertStringContainsString('Error querying transactions', $router->route(['intent' => 'query_transactions']));
        $this->assertStringContainsString('Error querying products', $router->route(['intent' => 'query_products']));
    }
}
