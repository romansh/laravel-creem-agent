<?php

namespace Romansh\LaravelCreemAgent\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Console\AgentStartCommand;
use Romansh\LaravelCreemAgent\Console\AgentStopCommand;
use Romansh\LaravelCreemAgent\Console\AgentStatusCommand;
use Romansh\LaravelCreemAgent\Console\HeartbeatCommand;
use Romansh\LaravelCreemAgent\Heartbeat\HeartbeatRunner;
use Romansh\LaravelCreemAgent\Heartbeat\StateManager;
use Symfony\Component\Console\Tester\CommandTester;

class ConsoleCommandsTest extends TestCase
{
    public function test_agent_start_daemon_outputs_instructions()
    {
        $command = new AgentStartCommand();
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $status = $tester->execute(['--daemon' => true]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Starting Creem Agent in daemon mode...', $tester->getDisplay());
    }

    public function test_agent_stop_outputs_message()
    {
        $command = new AgentStopCommand();
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Creem Agent stop signal sent.', $tester->getDisplay());
    }

    public function test_agent_status_shows_store_and_cli_backend()
    {
        $tmp = sys_get_temp_dir() . '/creem-agent-status';
        @mkdir($tmp);
        config(['creem-agent.state_path' => $tmp]);
        config(['creem-agent.stores' => ['default' => []]]);

        $mock = $this->getMockBuilder(CreemCliManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock->method('isNativeCliAvailable')->willReturn(true);

        $this->instance(CreemCliManager::class, $mock);
        (new StateManager())->save('default', [
            'lastCheckAt' => '2026-03-19T10:00:00Z',
            'transactionCount' => 1,
            'customerCount' => 2,
            'subscriptions' => ['active' => 3, 'trialing' => 0, 'past_due' => 0, 'canceled' => 0],
        ]);

        $command = new AgentStatusCommand();
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('🤖 Creem Agent Status', $tester->getDisplay());
        $this->assertStringContainsString('Native creem CLI (brew)', $tester->getDisplay());
    }

    public function test_heartbeat_command_prints_first_run()
    {
        $runner = new class extends HeartbeatRunner {
            public function __construct() {}

            public function run(string $store): array
            {
                return [
                    'first_run' => true,
                    'state' => [
                        'customerCount' => 0,
                        'transactionCount' => 0,
                        'subscriptions' => ['active' => 0, 'trialing' => 0, 'past_due' => 0],
                    ],
                    'changes' => [],
                ];
            }
        };

        $this->instance(CreemCliManager::class, new CreemCliManager(fn() => false));

        $command = new HeartbeatCommand($runner);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $status = $tester->execute(['--store' => 'default']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('First run — initial snapshot created', $tester->getDisplay());
    }
}
