<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Console\AgentInstallCommand;
use Romansh\LaravelCreemAgent\Console\AgentStartCommand;
use Symfony\Component\Console\Tester\CommandTester;

class CommandCoverageTest extends TestCase
{
    public function test_agent_start_interactive_calls_heartbeat_command()
    {
        $command = new class extends AgentStartCommand {
            public array $calls = [];

            public function call($command, array $arguments = []): int
            {
                $this->calls[] = [$command, $arguments];
                return self::SUCCESS;
            }
        };

        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Starting Creem Agent (interactive)...', $tester->getDisplay());
        $this->assertSame([['creem-agent:heartbeat', ['--all-stores' => true]]], $command->calls);
    }

    public function test_agent_install_command_covers_native_cli_branch_and_state_creation()
    {
        if (!class_exists(\Romansh\LaravelCreem\Creem::class)) {
            $this->markTestSkipped('laravel-creem dependency is not autoloadable in this local environment');
        }

        $statePath = sys_get_temp_dir() . '/creem-agent-install-' . uniqid();
        config()->set('creem.profiles.default', ['api_key' => 'test', 'test_mode' => true]);
        config()->set('creem.test_api_url', 'https://api.test');
        config()->set('creem-agent.default_store', 'default');
        config()->set('creem-agent.stores', ['default' => ['profile' => 'default', 'heartbeat_frequency' => 2]]);
        config()->set('creem-agent.notifications.slack_webhook_url', 'https://hooks.slack.test');
        config()->set('creem-agent.notifications.telegram_bot_token', 'telegram-token');
        config()->set('creem-agent.notifications.discord_webhook_url', null);
        config()->set('creem-agent.state_path', $statePath);

        Http::fake([
            'https://api.test/*' => Http::response(['items' => []], 200),
        ]);

        $cli = $this->getMockBuilder(CreemCliManager::class)
            ->onlyMethods(['isNativeCliAvailable'])
            ->disableOriginalConstructor()
            ->getMock();
        $cli->method('isNativeCliAvailable')->willReturn(true);

        $this->app->instance(CreemCliManager::class, $cli);

        $command = new AgentInstallCommand();
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $this->assertDirectoryExists($statePath);
        $this->assertStringContainsString('Native creem CLI detected', $tester->getDisplay());
        $this->assertStringContainsString('heartbeat every 2h', $tester->getDisplay());
    }
}