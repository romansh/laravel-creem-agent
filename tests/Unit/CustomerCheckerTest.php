<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Heartbeat\CustomerChecker;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class CustomerCheckerTest extends TestCase
{
    public function test_counts_and_new_customers()
    {
        $prev = ['customerCount' => 2];

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['customers'])
            ->disableOriginalConstructor()
            ->getMock();

        $custProxy = $this->getMockBuilder(\Romansh\LaravelCreemAgent\Cli\Proxies\CustomerProxy::class)
            ->disableOriginalConstructor()
            ->getMock();
        $custProxy->method('list')->willReturn(['total' => 5]);

        $cli->method('customers')->willReturn($custProxy);

        $checker = new CustomerChecker($cli);
        $res = $checker->check($prev);

        $this->assertEquals(5, $res['totalCount']);
        $this->assertEquals(3, $res['newCount']);
    }

    public function test_handles_exception_and_uses_previous()
    {
        $prev = ['customerCount' => 4];

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['customers'])
            ->disableOriginalConstructor()
            ->getMock();

        $cli->method('customers')->will($this->throwException(new \Exception('boom')));

        $checker = new CustomerChecker($cli);
        $res = $checker->check($prev);

        $this->assertEquals(4, $res['totalCount']);
        $this->assertEquals(0, $res['newCount']);
    }
}
