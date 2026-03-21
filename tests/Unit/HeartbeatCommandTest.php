<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Console\HeartbeatCommand;
use Romansh\LaravelCreemAgent\Heartbeat\HeartbeatRunner;
use Symfony\Component\Console\Tester\CommandTester;

class HeartbeatCommandTest extends TestCase
{
    public function test_heartbeat_command_prints_first_run_output()
    {
        $runner = new class extends HeartbeatRunner {
            public function __construct() {}

            public function run(string $store): array
            {
                return [
                    'first_run' => true,
                    'state' => [
                        'customerCount' => 2,
                        'transactionCount' => 1,
                        'subscriptions' => ['active' => 1, 'trialing' => 0, 'past_due' => 0],
                    ],
                    'changes' => [],
                ];
            }
        };

        $this->instance(CreemCliManager::class, new CreemCliManager(fn() => false));
        $command = new HeartbeatCommand($runner);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $status = $tester->execute(['--store' => 's1']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('First run — initial snapshot created', $tester->getDisplay());
        $this->assertStringContainsString('Customers: 2', $tester->getDisplay());
        $this->assertStringContainsString('Transactions: 1', $tester->getDisplay());
    }

    public function test_heartbeat_command_prints_no_change_and_change_severities()
    {
        $runner = new class extends HeartbeatRunner {
            private int $call = 0;

            public function __construct() {}

            public function run(string $store): array
            {
                $this->call++;

                if ($this->call === 1) {
                    return ['first_run' => false, 'state' => [], 'changes' => []];
                }

                return [
                    'first_run' => false,
                    'state' => [],
                    'changes' => [
                        ['message' => 'warning path', 'severity' => 'warning'],
                        ['message' => 'alert path', 'severity' => 'alert'],
                        ['message' => 'good path', 'severity' => 'good_news'],
                        ['message' => 'info path', 'severity' => 'info'],
                    ],
                ];
            }
        };

        $this->instance(CreemCliManager::class, new CreemCliManager(fn() => false));
        $command = new HeartbeatCommand($runner);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $firstStatus = $tester->execute(['--store' => 's2']);
        $this->assertSame(0, $firstStatus);
        $this->assertStringContainsString('No changes detected.', $tester->getDisplay());

        $secondStatus = $tester->execute(['--store' => 's2']);
        $this->assertSame(0, $secondStatus);
        $this->assertStringContainsString('warning path', $tester->getDisplay());
        $this->assertStringContainsString('alert path', $tester->getDisplay());
        $this->assertStringContainsString('good path', $tester->getDisplay());
        $this->assertStringContainsString('info path', $tester->getDisplay());
    }
}
