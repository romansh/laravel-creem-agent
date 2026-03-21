<?php

namespace Romansh\LaravelCreemAgent\Console;

use Illuminate\Console\Command;
use Romansh\LaravelCreemAgent\Heartbeat\StateManager;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class AgentStatusCommand extends Command
{
    protected $signature = 'creem-agent:status';
    protected $description = 'Show Creem Agent status';

    public function handle(CreemCliManager $cli): int
    {
        $stateManager = new StateManager();
        $stores = config('creem-agent.stores', []);

        $this->info('🤖 Creem Agent Status');
        $this->line('');

        $this->line('CLI Backend: ' . ($cli->isNativeCliAvailable() ? 'Native creem CLI (brew)' : 'laravel-creem-cli (Artisan)'));
        $this->line('');

        foreach ($stores as $name => $config) {
            $state = $stateManager->load($name);
            $lastCheck = $state['lastCheckAt'] ?? 'never';
            $subs = $state['subscriptions'] ?? [];

            $this->info("Store: {$name}");
            $this->line("  Last heartbeat: {$lastCheck}");
            $this->line("  Customers: {$state['customerCount']}");
            $this->line("  Transactions: {$state['transactionCount']}");
            $this->line("  Active: " . ($subs['active'] ?? 0));
            $this->line("  Trialing: " . ($subs['trialing'] ?? 0));
            $this->line("  Past due: " . ($subs['past_due'] ?? 0));
            $this->line("  Canceled: " . ($subs['canceled'] ?? 0));
            $this->line('');
        }

        return self::SUCCESS;
    }
}
