<?php

namespace Romansh\LaravelCreemAgent\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Console\HeartbeatCommand;
use Romansh\LaravelCreemAgent\Heartbeat\HeartbeatRunner;
use Symfony\Component\Console\Tester\CommandTester;

class HeartbeatCommandAllStoresTest extends TestCase
{
    public function test_heartbeat_command_handles_all_stores()
    {
        $runner = new class extends HeartbeatRunner {
            public function __construct() {}

            public function runAllStores(): array
            {
                return [
                    'one' => ['first_run' => true, 'state' => ['customerCount' => 1, 'transactionCount' => 2, 'subscriptions' => []], 'changes' => []],
                    'two' => ['first_run' => false, 'state' => [], 'changes' => [['message' => 'delta', 'severity' => 'info']]],
                ];
            }
        };

        $this->instance(CreemCliManager::class, new CreemCliManager(fn() => false));
        $command = new HeartbeatCommand($runner);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $status = $tester->execute(['--all-stores' => true]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Heartbeat for store: one', $tester->getDisplay());
        $this->assertStringContainsString('Heartbeat for store: two', $tester->getDisplay());
        $this->assertStringContainsString('delta', $tester->getDisplay());
    }
}
