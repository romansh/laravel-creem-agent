<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\IntentRouter;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class IntentRouterTransactionsTest extends TestCase
{
    public function test_transactions_formatting_and_empty()
    {
        $txnProxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $txnProxy->method('list')->willReturn([
            [
                'amount' => 2500,
                'currency' => 'USD',
                'product' => ['name' => 'Prod A'],
            ],
        ]);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['transactions', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('transactions')->willReturn($txnProxy);
        $cli->method('getActiveStore')->willReturn('s1');

        $router = new IntentRouter($cli);
        $res = $router->route(['intent' => 'query_transactions']);

        $this->assertStringContainsString('Recent transactions in store', $res);
        $this->assertStringContainsString('Prod A', $res);
        $this->assertStringContainsString('$25.00', $res);

        // empty
        $txnProxy2 = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $txnProxy2->method('list')->willReturn([]);

        $cli2 = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['transactions', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli2->method('transactions')->willReturn($txnProxy2);
        $cli2->method('getActiveStore')->willReturn('s1');

        $router2 = new IntentRouter($cli2);
        $res2 = $router2->route(['intent' => 'query_transactions']);
        $this->assertStringContainsString('No transactions found', $res2);
    }

    public function test_transactions_formatting_uses_default_currency_and_product_name()
    {
        $txnProxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['list'])
            ->getMock();

        $txnProxy->method('list')->willReturn([
            [
                'amount' => 0,
            ],
        ]);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['transactions', 'getActiveStore'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('transactions')->willReturn($txnProxy);
        $cli->method('getActiveStore')->willReturn('s1');

        $result = (new IntentRouter($cli))->route(['intent' => 'query_transactions']);

        $this->assertStringContainsString('$0.00 USD', $result);
        $this->assertStringContainsString('Unknown', $result);
    }
}
