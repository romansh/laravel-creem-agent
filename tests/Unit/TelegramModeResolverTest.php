<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Support\TelegramModeResolver;

class TelegramModeResolverTest extends TestCase
{
    public function test_defaults_to_laravel_mode(): void
    {
        config()->set('creem-agent.telegram.mode', null);
        config()->set('creem-agent.openclaw.enabled', false);

        $resolver = new TelegramModeResolver();

        $this->assertSame('laravel', $resolver->mode());
        $this->assertTrue($resolver->usesLaravelTransport());
        $this->assertFalse($resolver->usesOpenClawGateway());
    }

    public function test_uses_openclaw_mode_when_configured(): void
    {
        config()->set('creem-agent.telegram.mode', 'openclaw');

        $resolver = new TelegramModeResolver();

        $this->assertSame('openclaw', $resolver->mode());
        $this->assertFalse($resolver->usesLaravelTransport());
        $this->assertTrue($resolver->usesOpenClawGateway());
    }

    public function test_invalid_mode_falls_back_to_laravel(): void
    {
        config()->set('creem-agent.telegram.mode', 'unexpected');

        $resolver = new TelegramModeResolver();

        $this->assertSame('laravel', $resolver->mode());
    }
}