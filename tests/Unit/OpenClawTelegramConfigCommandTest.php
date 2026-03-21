<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Console\OpenClawTelegramConfigCommand;
use Symfony\Component\Console\Tester\CommandTester;

class OpenClawTelegramConfigCommandTest extends TestCase
{
    public function test_outputs_native_openclaw_telegram_snippet(): void
    {
        config()->set('creem-agent.telegram.mode', 'openclaw');
        config()->set('creem-agent.openclaw.telegram.bot_token', '123:abc');

        $command = new OpenClawTelegramConfigCommand();
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('channels', $tester->getDisplay());
        $this->assertStringContainsString('telegram', $tester->getDisplay());
        $this->assertStringContainsString('123:abc', $tester->getDisplay());
        $this->assertStringContainsString('docs.openclaw.ai/channels/telegram', $tester->getDisplay());
    }
}