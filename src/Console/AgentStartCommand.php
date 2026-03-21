<?php

namespace Romansh\LaravelCreemAgent\Console;

use Illuminate\Console\Command;

class AgentStartCommand extends Command
{
    protected $signature = 'creem-agent:start {--daemon : Run as background daemon}';
    protected $description = 'Start the Creem Agent';

    public function handle(): int
    {
        if ($this->option('daemon')) {
            $this->info('Starting Creem Agent in daemon mode...');
            $this->line('Register the heartbeat in your scheduler:');
            $this->line('');
            $this->line("  // In routes/console.php or app/Console/Kernel.php:");
            $this->line("  Schedule::command('creem-agent:heartbeat --all-stores')");
            $this->line("      ->everyFourHours()");
            $this->line("      ->runInBackground()");
            $this->line("      ->withoutOverlapping();");
            $this->line('');
            $this->info('Then run: php artisan schedule:work');
            return self::SUCCESS;
        }

        $this->info('Starting Creem Agent (interactive)...');
        $this->call('creem-agent:heartbeat', ['--all-stores' => true]);

        return self::SUCCESS;
    }
}
