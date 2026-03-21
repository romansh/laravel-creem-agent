<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\IntentRouter;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class IntentRouterExtendedTest extends TestCase
{
    public function test_switch_store_not_found_and_success()
    {
        config()->set('creem-agent.stores', ['main' => ['profile' => 'main']]);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['setActiveStore', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $router = new IntentRouter($cli);

        $res = $router->route(['intent' => 'switch_store', 'store' => 'missing']);
        $this->assertStringContainsString("Store 'missing' not found", $res);

        $cli->expects($this->once())->method('setActiveStore')->with('main');
        $res2 = $router->route(['intent' => 'switch_store', 'store' => 'main']);
        $this->assertStringContainsString("Switched to store 'main'", $res2);
    }

    public function test_query_subscriptions_success_and_exception()
    {
        $subs = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['listByStatus'])
            ->getMock();

        $subs->method('listByStatus')->willReturn(['items' => [1, 2, 3]]);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['subscriptions', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('subscriptions')->willReturn($subs);
        $cli->method('getActiveStore')->willReturn('default');

        $router = new IntentRouter($cli);
        $res = $router->route(['intent' => 'query_subscriptions']);
        $this->assertStringContainsString('3 active subscription(s)', $res);

        $subs->method('listByStatus')->will($this->throwException(new \Exception('boom')));
        $res2 = $router->route(['intent' => 'query_subscriptions']);
        $this->assertStringContainsString('Error querying subscriptions', $res2);
    }

    public function test_query_subscriptions_supports_data_shape()
    {
        $subs = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['listByStatus'])
            ->getMock();

        $subs->method('listByStatus')->willReturn(['data' => [1, 2]]);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['subscriptions', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('subscriptions')->willReturn($subs);
        $cli->method('getActiveStore')->willReturn('default');

        $result = (new IntentRouter($cli))->route(['intent' => 'query_subscriptions', 'status' => 'trialing']);

        $this->assertStringContainsString('2 trialing subscription(s)', $result);
    }

    public function test_query_customers_and_transactions_and_products()
    {
        $custProxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\CustomerProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $custProxy->method('list')->willReturn(['items' => [1, 2]]);

        $txnProxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $txnProxy->method('list')->willReturn([
            ['amount' => 2500, 'currency' => 'USD', 'product' => ['name' => 'Prod A']],
        ]);

        $prodProxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\ProductProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $prodProxy->method('list')->willReturn([
            ['price' => 5000, 'name' => 'P1'],
        ]);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['customers', 'transactions', 'products', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('customers')->willReturn($custProxy);
        $cli->method('transactions')->willReturn($txnProxy);
        $cli->method('products')->willReturn($prodProxy);
        $cli->method('getActiveStore')->willReturn('default');

        $router = new IntentRouter($cli);

        $resCust = $router->route(['intent' => 'query_customers']);
        $this->assertStringContainsString('You have 2 customer(s)', $resCust);

        $resTxn = $router->route(['intent' => 'query_transactions']);
        $this->assertStringContainsString('Recent transactions in store', $resTxn);
        $this->assertStringContainsString('Prod A', $resTxn);

        $resProd = $router->route(['intent' => 'query_products']);
        $this->assertStringContainsString('Products in store', $resProd);
        $this->assertStringContainsString('P1', $resProd);
    }

    public function test_query_customers_supports_data_shape()
    {
        $custProxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\CustomerProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $custProxy->method('list')->willReturn(['data' => [1, 2, 3]]);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['customers', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('customers')->willReturn($custProxy);
        $cli->method('getActiveStore')->willReturn('default');

        $result = (new IntentRouter($cli))->route(['intent' => 'query_customers']);

        $this->assertStringContainsString('You have 3 customer(s)', $result);
    }

    public function test_status_reads_state_file()
    {
        $tmp = sys_get_temp_dir() . '/creem_state_test_' . uniqid();
        config()->set('creem-agent.state_path', $tmp);

        $stateMgr = new \Romansh\LaravelCreemAgent\Heartbeat\StateManager();
        $stateMgr->save('s1', [
            'lastCheckAt' => '2020-01-01T00:00:00Z',
            'transactionCount' => 5,
            'customerCount' => 7,
            'subscriptions' => ['active' => 2, 'trialing' => 0, 'past_due' => 1, 'canceled' => 0],
        ]);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('getActiveStore')->willReturn('s1');

        $router = new IntentRouter($cli);
        $res = $router->route(['intent' => 'status']);

        $this->assertStringContainsString("Store 's1' status:", $res);
        $this->assertStringContainsString('Customers: 7', $res);
        $this->assertStringContainsString('Transactions: 5', $res);
    }
}
