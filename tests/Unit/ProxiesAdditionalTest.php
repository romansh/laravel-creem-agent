<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class ProxiesAdditionalTest extends TestCase
{
    public function test_customer_proxy_calls_execute()
    {
        $manager = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $manager->expects($this->once())
            ->method('execute')
            ->with('customers', 'get', $this->isType('array'), null)
            ->willReturn(['id' => 'c_1']);

        $proxy = new \Romansh\LaravelCreemAgent\Cli\Proxies\CustomerProxy($manager);
        $res = $proxy->find('c_1');
        $this->assertEquals(['id' => 'c_1'], $res);
    }

    public function test_product_proxy_calls_execute()
    {
        $manager = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $manager->expects($this->once())
            ->method('execute')
            ->with('products', 'create', $this->isType('array'), null)
            ->willReturn(['created' => true]);

        $proxy = new \Romansh\LaravelCreemAgent\Cli\Proxies\ProductProxy($manager);
        $res = $proxy->create(['name' => 'X']);
        $this->assertEquals(['created' => true], $res);
    }
}
