<?php

namespace Romansh\LaravelCreemAgent\Console;

use Illuminate\Console\Command;
use Romansh\LaravelCreemAgent\Heartbeat\HeartbeatRunner;
use Romansh\LaravelCreemAgent\Heartbeat\Reporter;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class HeartbeatCommand extends Command
{
    protected $signature = 'creem-agent:heartbeat
        {--store= : Run for a specific store}
        {--all-stores : Run for all configured stores}
        {--force : Run even if not due yet}';

    protected $description = 'Run one heartbeat cycle for Creem store monitoring';

    public function __construct(private ?HeartbeatRunner $runner = null)
    {
        parent::__construct();
    }

    public function handle(CreemCliManager $cli): int
    {
        $runner = $this->runner ?? new HeartbeatRunner($cli, reporter: new Reporter(forceTelegramDirect: true));

        if ($this->option('all-stores')) {
            $results = $runner->runAllStores();
            foreach ($results as $store => $result) {
                $this->printResult($store, $result);
            }
            return self::SUCCESS;
        }

        $store = $this->option('store') ?? config('creem-agent.default_store', 'default');
        $result = $runner->run($store);
        $this->printResult($store, $result);

        return self::SUCCESS;
    }

    private function printResult(string $store, array $result): void
    {
        $this->info("[CreemAgent] Heartbeat for store: {$store}");

        if ($result['first_run']) {
            $this->comment('  First run — initial snapshot created');
            $state = $result['state'];
            $this->line("  Customers: {$state['customerCount']}");
            $this->line("  Active subscriptions: " . ($state['subscriptions']['active'] ?? 0));
            $this->line("  Trialing: " . ($state['subscriptions']['trialing'] ?? 0));
            $this->line("  Past due: " . ($state['subscriptions']['past_due'] ?? 0));
            $this->line("  Transactions: {$state['transactionCount']}");
        } else {
            $count = count($result['changes']);
            if ($count === 0) {
                $this->line('  No changes detected.');
            } else {
                $this->line("  {$count} change(s) detected:");
                foreach ($result['changes'] as $change) {
                    $icon = match ($change['severity'] ?? 'info') {
                        'good_news' => '✅',
                        'warning' => '⚠️',
                        'alert' => '🚨',
                        default => 'ℹ️',
                    };
                    $this->line("    {$icon} {$change['message']}");
                }
            }
        }
    }
}
