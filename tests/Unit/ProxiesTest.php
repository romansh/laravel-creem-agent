<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class ProxiesTest extends TestCase
{
    public function test_subscription_proxy_calls_execute()
    {
        $manager = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $manager->expects($this->once())
            ->method('execute')
            ->with('subscriptions', 'list', [
                'filters' => ['status' => 'active'],
                'page' => 1,
                'limit' => 100,
            ], null)
            ->willReturn([]);

        $proxy = new \Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy($manager);
        $res = $proxy->listByStatus('active');
        $this->assertIsArray($res);
    }

    public function test_transaction_proxy_list_calls_execute()
    {
        $manager = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $manager->expects($this->once())
            ->method('execute')
            ->with('transactions', 'list', [
                'filters' => [],
                'page' => 1,
                'limit' => 20,
            ], null)
            ->willReturn(['items' => []]);

        $proxy = new \Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy($manager);
        $res = $proxy->list();
        $this->assertIsArray($res);
    }
}
