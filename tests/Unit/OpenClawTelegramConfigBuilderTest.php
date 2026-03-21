<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Support\OpenClawTelegramConfigBuilder;
use Romansh\LaravelCreemAgent\Support\TelegramModeResolver;

class OpenClawTelegramConfigBuilderTest extends TestCase
{
    public function test_builds_openclaw_telegram_config_from_current_settings(): void
    {
        config()->set('creem-agent.telegram.mode', 'openclaw');
        config()->set('creem-agent.openclaw.telegram.bot_token', '123:abc');
        config()->set('creem-agent.openclaw.telegram.dm_policy', 'allowlist');
        config()->set('creem-agent.openclaw.telegram.allow_from', '111, 222');
        config()->set('creem-agent.openclaw.telegram.group_policy', 'open');
        config()->set('creem-agent.openclaw.telegram.group_allow_from', '333');
        config()->set('creem-agent.openclaw.telegram.require_mention', false);

        $builder = new OpenClawTelegramConfigBuilder(new TelegramModeResolver());
        $config = $builder->build();

        $this->assertTrue($builder->openClawOwnsTelegram());
        $this->assertTrue($builder->hasBotToken());
        $this->assertSame('123:abc', $config['channels']['telegram']['botToken']);
        $this->assertSame('allowlist', $config['channels']['telegram']['dmPolicy']);
        $this->assertSame(['111', '222'], $config['channels']['telegram']['allowFrom']);
        $this->assertSame('open', $config['channels']['telegram']['groupPolicy']);
        $this->assertSame(['333'], $config['channels']['telegram']['groupAllowFrom']);
        $this->assertFalse($config['channels']['telegram']['groups']['*']['requireMention']);
    }

    public function test_render_outputs_snippet_with_placeholder_when_token_missing(): void
    {
        config()->set('creem-agent.telegram.mode', 'openclaw');
        config()->set('creem-agent.openclaw.telegram.bot_token', null);

        $builder = new OpenClawTelegramConfigBuilder(new TelegramModeResolver());
        $snippet = $builder->render();

        $this->assertFalse($builder->hasBotToken());
        $this->assertStringContainsString('channels', $snippet);
        $this->assertStringContainsString('__SET_OPENCLAW_TELEGRAM_BOT_TOKEN__', $snippet);
        $this->assertStringContainsString('requireMention', $snippet);
    }
}