<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\IntentRouter;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class IntentRouterMoreTest extends TestCase
{
    public function test_help_returns_usage_list()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('getActiveStore')->willReturn('default');

        $router = new IntentRouter($cli);
        $res = $router->route(['intent' => 'help']);

        $this->assertStringContainsString("I can help you with", $res);
        $this->assertStringContainsString("create checkout", $res);
    }

    public function test_create_checkout_success_and_failure()
    {
        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('getActiveStore')->willReturn('default');

        // success
        $cli->method('execute')->willReturn(['checkout_url' => 'https://pay.example/ok']);
        $router = new IntentRouter($cli);
        $res = $router->route(['intent' => 'create_checkout', 'product_id' => 'prod_1']);
        $this->assertStringContainsString('Checkout created: https://pay.example/ok', $res);

        // failure
        $cli2 = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli2->method('getActiveStore')->willReturn('default');
        $cli2->method('execute')->will($this->throwException(new \Exception('boom')));

        $router2 = new IntentRouter($cli2);
        $res2 = $router2->route(['intent' => 'create_checkout', 'product_id' => 'prod_1']);
        $this->assertStringContainsString('Failed to create checkout', $res2);

        $cli3 = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli3->method('getActiveStore')->willReturn('default');
        $cli3->method('execute')->willReturn([]);

        $router3 = new IntentRouter($cli3);
        $res3 = $router3->route(['intent' => 'create_checkout', 'product_id' => 'prod_1']);
        $this->assertStringContainsString('Checkout created: N/A', $res3);
    }

    public function test_cancel_subscription_success_and_failure()
    {
        $subs = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['cancel'])
            ->getMock();

        $subs->expects($this->once())->method('cancel')->with('sub_1', true);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['subscriptions', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('subscriptions')->willReturn($subs);
        $cli->method('getActiveStore')->willReturn('default');

        $router = new IntentRouter($cli);
        $res = $router->route(['intent' => 'cancel_subscription', 'id' => 'sub_1']);
        $this->assertStringContainsString('scheduled for cancellation', $res);

        // failure
        $subs2 = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['cancel'])
            ->getMock();

        $subs2->method('cancel')->will($this->throwException(new \Exception('nope')));

        $cli2 = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['subscriptions', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli2->method('subscriptions')->willReturn($subs2);
        $cli2->method('getActiveStore')->willReturn('default');

        $router2 = new IntentRouter($cli2);
        $res2 = $router2->route(['intent' => 'cancel_subscription', 'id' => 'sub_1']);
        $this->assertStringContainsString('Failed to cancel', $res2);
    }

    public function test_query_customers_handles_malformed_payload_gracefully()
    {
        $customers = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\CustomerProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $customers->method('list')->willReturn(['items' => 'broken']);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['customers', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('customers')->willReturn($customers);
        $cli->method('getActiveStore')->willReturn('default');

        $router = new IntentRouter($cli);
        $res = $router->route(['intent' => 'query_customers']);

        $this->assertStringContainsString('0 customer(s)', $res);
    }

    public function test_query_transactions_and_products_ignore_non_array_items()
    {
        $transactions = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();
        $transactions->method('list')->willReturn(['items' => 'broken']);

        $products = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\ProductProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();
        $products->method('list')->willReturn(['items' => 'broken']);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['transactions', 'products', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('transactions')->willReturn($transactions);
        $cli->method('products')->willReturn($products);
        $cli->method('getActiveStore')->willReturn('default');

        $router = new IntentRouter($cli);

        $this->assertSame('No transactions found.', $router->route(['intent' => 'query_transactions']));
        $this->assertSame('No products found.', $router->route(['intent' => 'query_products']));
    }
}
