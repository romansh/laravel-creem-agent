<?php

namespace Romansh\LaravelCreemAgent\Console;

use Illuminate\Console\Command;
use Romansh\LaravelCreemAgent\Support\OpenClawTelegramConfigBuilder;

class OpenClawTelegramConfigCommand extends Command
{
    protected $signature = 'creem-agent:openclaw-telegram-config
        {--format=snippet : Output format: snippet or json}';

    protected $description = 'Render OpenClaw native Telegram channel config for the current agent setup';

    public function handle(OpenClawTelegramConfigBuilder $builder): int
    {
        $this->line($builder->render((string) $this->option('format')));
        $this->newLine();
        $this->line('Start the OpenClaw gateway after adding this config, then approve the first DM pairing if dmPolicy=pairing.');
        $this->line('Reference: https://docs.openclaw.ai/channels/telegram');

        return self::SUCCESS;
    }
}