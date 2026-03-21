<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class CreemCliManagerTest extends TestCase
{
    public function test_transactions_proxy_calls_execute()
    {
        $manager = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $manager->expects($this->once())
            ->method('execute')
            ->with('transactions', 'list', [
                'filters' => [],
                'page' => 1,
                'limit' => 10,
            ], null)
            ->willReturn(['items' => [], 'pagination' => ['total_records' => 0], 'total' => 0]);

        $result = $manager->transactions()->list([], 1, 10);
        $this->assertIsArray($result);
    }
}
