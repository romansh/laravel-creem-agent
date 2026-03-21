<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\IntentRouter;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy;
use Romansh\LaravelCreemAgent\Heartbeat\StateManager;

class IntentRouterTest extends TestCase
{
    public function test_switch_store_changes_active_store()
    {
        config(['creem-agent.stores' => ['default' => [], 'secondary' => []]]);

        $mock = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['setActiveStore', 'getActiveStore'])
            ->getMock();

        $mock->expects($this->once())->method('setActiveStore')->with('secondary');
        $mock->method('getActiveStore')->willReturn('default');

        $router = new IntentRouter($mock);
        $result = $router->route(['intent' => 'switch_store', 'store' => 'secondary']);

        $this->assertStringContainsString("Switched to store 'secondary'", $result);
    }

    public function test_query_subscriptions_returns_count()
    {
        $subscriptions = $this->getMockBuilder(SubscriptionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['listByStatus'])
            ->getMock();

        $subscriptions->method('listByStatus')->with('active')->willReturn([
            'items' => [['id' => 'sub_1'], ['id' => 'sub_2']],
        ]);

        $mock = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['subscriptions', 'getActiveStore'])
            ->getMock();

        $mock->method('subscriptions')->willReturn($subscriptions);
        $mock->method('getActiveStore')->willReturn('default');

        $router = new IntentRouter($mock);
        $result = $router->route(['intent' => 'query_subscriptions', 'status' => 'active']);

        $this->assertStringContainsString("2 active subscription", $result);
    }

    public function test_status_reads_state_file()
    {
        $mock = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['getActiveStore'])
            ->getMock();

        $mock->method('getActiveStore')->willReturn('default');

        $stateManager = new class extends StateManager {
            public function __construct() {}

            public function load(string $store): array
            {
                return [
                    'lastCheckAt' => '2026-01-01T00:00:00Z',
                    'customerCount' => 5,
                    'transactionCount' => 3,
                    'subscriptions' => ['active' => 2, 'trialing' => 0, 'past_due' => 0, 'canceled' => 1],
                ];
            }
        };

        $router = new IntentRouter($mock, $stateManager);
        $result = $router->route(['intent' => 'status']);

        $this->assertStringContainsString("Customers: 5", $result);
        $this->assertStringContainsString("Transactions: 3", $result);
        $this->assertStringContainsString("Active subs: 2", $result);
    }
}
