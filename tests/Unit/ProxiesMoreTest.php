<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class ProxiesMoreTest extends TestCase
{
    public function test_subscription_proxy_more_methods()
    {
        $manager = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $manager->expects($this->exactly(4))
            ->method('execute')
            ->willReturnOnConsecutiveCalls([], [], [], []);

        $proxy = new \Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy($manager);
        $this->assertIsArray($proxy->list());
        $this->assertIsArray($proxy->listByStatus('active'));
        $this->assertIsArray($proxy->find('s1'));
        $this->assertIsArray($proxy->cancel('s1'));
    }

    public function test_transaction_proxy_by_customer_and_product()
    {
        $manager = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $manager->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls([], []);

        $proxy = new \Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy($manager);
        $this->assertIsArray($proxy->byCustomer('c1'));
        $this->assertIsArray($proxy->byProduct('p1'));
    }

    public function test_transaction_proxy_supports_arbitrary_filters()
    {
        $manager = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $manager->expects($this->once())
            ->method('execute')
            ->with('transactions', 'list', [
                'filters' => [
                    'customer_id' => 'c1',
                    'order_id' => 'o1',
                    'status' => 'paid',
                ],
                'page' => 2,
                'limit' => 15,
            ], 'store_a')
            ->willReturn([]);

        $proxy = new \Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy($manager);

        $this->assertIsArray($proxy->list([
            'customer_id' => 'c1',
            'order_id' => 'o1',
            'status' => 'paid',
        ], 2, 15, 'store_a'));
    }
}
