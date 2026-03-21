<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\ProductProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\CustomerProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy;

class CliProxiesTest extends TestCase
{
    public function test_transaction_proxy_calls_manager_execute()
    {
        $m = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $m->expects($this->once())->method('execute')
            ->with('transactions', 'list', [
                'filters' => [],
                'page' => 1,
                'limit' => 10,
            ], null)
            ->willReturn(['items' => []]);

        $proxy = new TransactionProxy($m);
        $res = $proxy->list([], 1, 10);
        $this->assertIsArray($res);
    }

    public function test_product_proxy_create_and_find()
    {
        $m = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $calls = [];
        $m->method('execute')->willReturnCallback(function ($resource, $action, $args, $store) use (&$calls) {
            $calls[] = [$resource, $action, $args, $store];
            if ($action === 'create') {
                return ['id' => 'p1'];
            }
            return ['id' => 'p1'];
        });

        $proxy = new ProductProxy($m);
        $this->assertSame(['id' => 'p1'], $proxy->create(['name' => 'X']));
        $this->assertSame(['id' => 'p1'], $proxy->find('p1'));

        $this->assertCount(2, $calls);
        $this->assertSame('products', $calls[0][0]);
        $this->assertSame('create', $calls[0][1]);
        $this->assertSame('products', $calls[1][0]);
        $this->assertSame('get', $calls[1][1]);
    }

    public function test_customer_proxy_billing_and_find_by_email()
    {
        $m = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $calls = [];
        $m->method('execute')->willReturnCallback(function ($resource, $action, $args, $store) use (&$calls) {
            $calls[] = [$resource, $action, $args, $store];
            if ($action === 'billing') {
                return ['url' => 'ok'];
            }
            return ['items' => []];
        });

        $proxy = new CustomerProxy($m);
        $this->assertSame(['url' => 'ok'], $proxy->billingPortal('c1'));
        $this->assertSame(['items' => []], $proxy->findByEmail('a@b.test'));

        $this->assertCount(2, $calls);
        $this->assertSame('customers', $calls[0][0]);
        $this->assertSame('billing', $calls[0][1]);
        $this->assertSame('customers', $calls[1][0]);
        $this->assertSame('get', $calls[1][1]);
    }

    public function test_subscription_proxy_cancel_modes()
    {
        $m = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $calls = [];
        $m->method('execute')->willReturnCallback(function ($resource, $action, $args, $store) use (&$calls) {
            $calls[] = [$resource, $action, $args, $store];
            return ['ok' => true];
        });

        $proxy = new SubscriptionProxy($m);
        $this->assertSame(['ok' => true], $proxy->cancel('s1', true));
        $this->assertSame(['ok' => true], $proxy->cancel('s2', false));

        $this->assertCount(2, $calls);
        $this->assertSame('subscriptions', $calls[0][0]);
        $this->assertSame('cancel', $calls[0][1]);
        $this->assertSame('scheduled', $calls[0][2]['mode']);
        $this->assertSame('subscriptions', $calls[1][0]);
        $this->assertSame('cancel', $calls[1][1]);
        $this->assertSame('immediate', $calls[1][2]['mode']);
    }
}
