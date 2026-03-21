<?php

namespace Romansh\LaravelCreemAgent\Console;

use Illuminate\Console\Command;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Support\OpenClawTelegramConfigBuilder;
use Romansh\LaravelCreemAgent\Support\TelegramModeResolver;

class AgentInstallCommand extends Command
{
    protected $signature = 'creem-agent:install';
    protected $description = 'First-time setup wizard for Creem Agent';

    public function handle(
        CreemCliManager $cli,
        TelegramModeResolver $telegramMode,
        OpenClawTelegramConfigBuilder $openClawTelegram
    ): int
    {
        $this->info('🤖 Creem Agent Installation Wizard');
        $this->line('');

        // Step 1: Check API key
        $this->info('Step 1: Verifying API connection...');
        try {
            $profile = config('creem-agent.default_store', 'default');
            $creem = \Romansh\LaravelCreem\Creem::profile(
                config("creem-agent.stores.{$profile}.profile", 'default')
            );
            $creem->products()->list(1, 1);
            $this->line('  ✅ API connection successful');
        } catch (\Exception $e) {
            $this->error('  ❌ API connection failed: ' . $e->getMessage());
            $this->line('  Set CREEM_API_KEY in your .env file');
            return self::FAILURE;
        }

        // Step 2: Check CLI availability
        $this->info('Step 2: Detecting CLI backend...');
        if ($cli->isNativeCliAvailable()) {
            $this->line('  ✅ Native creem CLI detected (brew)');
        } else {
            $this->line('  ℹ️  Using laravel-creem-cli (Artisan) backend');
        }

        // Step 3: Create state directory
        $this->info('Step 3: Creating state storage...');
        $statePath = config('creem-agent.state_path', storage_path('creem-agent'));
        if (!is_dir($statePath)) {
            mkdir($statePath, 0755, true);
        }
        $this->line("  ✅ State directory: {$statePath}");

        // Step 4: List configured stores
        $this->info('Step 4: Configured stores:');
        $stores = config('creem-agent.stores', []);
        foreach ($stores as $name => $config) {
            $freq = $config['heartbeat_frequency'] ?? 4;
            $this->line("  • {$name} (heartbeat every {$freq}h)");
        }

        // Step 5: Check notifications
        $this->info('Step 5: Notification channels:');
        $slack = config('creem-agent.notifications.slack_webhook_url');
        $telegram = config('creem-agent.notifications.telegram_bot_token');
        $discord = config('creem-agent.notifications.discord_webhook_url');
        $ownsTelegram = $telegramMode->mode();
        $telegramConfigured = $telegramMode->usesOpenClawGateway()
            ? $openClawTelegram->hasBotToken()
            : (bool) $telegram;

        $this->line('  ' . ($slack ? '✅' : '⬜') . ' Slack');
        $this->line('  ' . ($telegramConfigured ? '✅' : '⬜') . ' Telegram (' . ($ownsTelegram === 'openclaw' ? 'OpenClaw gateway' : 'Laravel webhook') . ')');
        $this->line('  ' . ($discord ? '✅' : '⬜') . ' Discord');
        $this->line('  ✅ Database (always enabled)');

        if ($telegramMode->usesOpenClawGateway()) {
            $this->line('  ℹ️  Generate OpenClaw Telegram config: php artisan creem-agent:openclaw-telegram-config');
        } else {
            $this->line('  ℹ️  Telegram webhook endpoint: /creem-agent/telegram/webhook');
        }

        $this->line('');
        $this->info('Setup complete! Run your first heartbeat:');
        $this->line('  php artisan creem-agent:heartbeat');
        $this->line('');
        $this->info('Or start the agent daemon:');
        $this->line('  php artisan creem-agent:start --daemon');

        return self::SUCCESS;
    }
}
