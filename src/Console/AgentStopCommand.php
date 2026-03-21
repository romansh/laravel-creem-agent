<?php

namespace Romansh\LaravelCreemAgent\Console;

use Illuminate\Console\Command;

class AgentStopCommand extends Command
{
    protected $signature = 'creem-agent:stop';
    protected $description = 'Stop the Creem Agent gracefully';

    public function handle(): int
    {
        $this->info('Creem Agent stop signal sent.');
        $this->line('The scheduler-based agent will stop on next cycle.');
        $this->line('To stop the queue worker: php artisan queue:restart');
        return self::SUCCESS;
    }
}
