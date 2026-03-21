<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Cli\Proxies\CustomerProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\ProductProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy;

class ProxyMethodCoverageTest extends TestCase
{
    public function test_all_proxy_methods_forward_expected_store_and_arguments()
    {
        $calls = [];

        $manager = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $manager->method('execute')->willReturnCallback(function ($resource, $action, $args, $store) use (&$calls) {
            $calls[] = [$resource, $action, $args, $store];
            return ['ok' => true];
        });

        (new CustomerProxy($manager))->list(3, 's0');
        (new CustomerProxy($manager))->find('cust_1', 's1');
        (new CustomerProxy($manager))->findByEmail('user@example.test', 's1b');
        (new CustomerProxy($manager))->billingPortal('cust_2', 's1c');
        (new ProductProxy($manager))->list(7, 's2');
        (new SubscriptionProxy($manager))->list([], 1, 9, 's3');
        (new SubscriptionProxy($manager))->find('sub_1', 's4');
        (new SubscriptionProxy($manager))->pause('sub_1', 's5');
        (new SubscriptionProxy($manager))->resume('sub_1', 's6');
        (new TransactionProxy($manager))->find('txn_1', 's7');

        $this->assertSame(['customers', 'list', ['limit' => 3], 's0'], $calls[0]);
        $this->assertSame(['customers', 'get', ['id' => 'cust_1'], 's1'], $calls[1]);
        $this->assertSame(['customers', 'get', ['email' => 'user@example.test'], 's1b'], $calls[2]);
        $this->assertSame(['customers', 'billing', ['id' => 'cust_2'], 's1c'], $calls[3]);
        $this->assertSame(['products', 'list', ['limit' => 7], 's2'], $calls[4]);
        $this->assertSame(['subscriptions', 'list', ['filters' => [], 'page' => 1, 'limit' => 9], 's3'], $calls[5]);
        $this->assertSame(['subscriptions', 'get', ['id' => 'sub_1'], 's4'], $calls[6]);
        $this->assertSame(['subscriptions', 'pause', ['id' => 'sub_1'], 's5'], $calls[7]);
        $this->assertSame(['subscriptions', 'resume', ['id' => 'sub_1'], 's6'], $calls[8]);
        $this->assertSame(['transactions', 'get', ['id' => 'txn_1'], 's7'], $calls[9]);
    }
}
