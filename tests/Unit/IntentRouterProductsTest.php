<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\IntentRouter;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class IntentRouterProductsTest extends TestCase
{
    public function test_products_formatting_and_empty()
    {
        $prodProxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\ProductProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $prodProxy->method('list')->willReturn([
            ['price' => 5000, 'name' => 'P1'],
        ]);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['products', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('products')->willReturn($prodProxy);
        $cli->method('getActiveStore')->willReturn('s2');

        $router = new IntentRouter($cli);
        $res = $router->route(['intent' => 'query_products']);

        $this->assertStringContainsString('Products in store', $res);
        $this->assertStringContainsString('P1', $res);
        $this->assertStringContainsString('$50.00', $res);

        // empty
        $prodProxy2 = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\ProductProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $prodProxy2->method('list')->willReturn([]);

        $cli2 = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['products', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli2->method('products')->willReturn($prodProxy2);
        $cli2->method('getActiveStore')->willReturn('s2');

        $router2 = new IntentRouter($cli2);
        $res2 = $router2->route(['intent' => 'query_products']);
        $this->assertStringContainsString('No products found', $res2);
    }

    public function test_products_formatting_uses_default_name_and_price()
    {
        $prodProxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\ProductProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $prodProxy->method('list')->willReturn([
            [],
        ]);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['products', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('products')->willReturn($prodProxy);
        $cli->method('getActiveStore')->willReturn('s2');

        $result = (new IntentRouter($cli))->route(['intent' => 'query_products']);

        $this->assertStringContainsString('Unknown ($0.00)', $result);
    }
}
