<?php

namespace Romansh\LaravelCreemAgent\Console;

use Illuminate\Console\Command;
use Romansh\LaravelCreemAgent\Heartbeat\StateManager;

class HeartbeatResetCommand extends Command
{
    protected $signature = 'creem-agent:heartbeat-reset
        {--store= : Reset heartbeat state for a specific store}
        {--all-stores : Reset heartbeat state for all configured stores}';

    protected $description = 'Reset stored heartbeat state back to the initial snapshot state';

    public function handle(StateManager $stateManager): int
    {
        $stores = $this->storesToReset();

        foreach ($stores as $store) {
            $stateManager->reset($store);
            $this->info("[CreemAgent] Heartbeat state reset for store: {$store}");
        }

        if (count($stores) === 1) {
            $this->line('The next heartbeat run will behave like a first run.');
        } else {
            $this->line('The next heartbeat run for each store will behave like a first run.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function storesToReset(): array
    {
        if ($this->option('all-stores')) {
            $stores = array_keys(config('creem-agent.stores', []));

            if ($stores !== []) {
                return $stores;
            }
        }

        return [(string) ($this->option('store') ?? config('creem-agent.default_store', 'default'))];
    }
}