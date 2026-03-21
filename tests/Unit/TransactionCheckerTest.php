<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Heartbeat\TransactionChecker;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class TransactionCheckerTest extends TestCase
{
    public function test_returns_empty_on_no_transactions()
    {
        $prev = ['lastTransactionId' => null, 'transactionCount' => 0];

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['transactions'])
            ->disableOriginalConstructor()
            ->getMock();

        $txProxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy::class)
            ->disableOriginalConstructor()
            ->getMock();
        $txProxy->expects($this->once())
            ->method('list')
            ->with([], 1, 20, null)
            ->willReturn([]);

        $cli->method('transactions')->willReturn($txProxy);

        $checker = new TransactionChecker($cli);
        $res = $checker->check($prev);

        $this->assertEquals([], $res['newTransactions']);
        $this->assertEquals(null, $res['latestId']);
        $this->assertEquals(0, $res['totalCount']);
    }

    public function test_detects_new_transactions()
    {
        $prev = ['lastTransactionId' => 'txn_old', 'transactionCount' => 1];

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['transactions'])
            ->disableOriginalConstructor()
            ->getMock();

        $txProxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy::class)
            ->disableOriginalConstructor()
            ->getMock();
        $txProxy->expects($this->once())
            ->method('list')
            ->with([], 1, 20, null)
            ->willReturn([
                'items' => [['id' => 'txn_new', 'amount' => 1000, 'currency' => 'USD']],
                'pagination' => ['total_records' => 1],
            ]);

        $cli->method('transactions')->willReturn($txProxy);

        $checker = new TransactionChecker($cli);
        $res = $checker->check($prev);

        $this->assertNotEmpty($res['newTransactions']);
        $this->assertEquals('txn_new', $res['latestId']);
        $this->assertEquals(1, $res['totalCount']);
    }
}
