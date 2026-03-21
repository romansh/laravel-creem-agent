<?php

namespace Romansh\LaravelCreemAgent\Heartbeat;

use Illuminate\Support\Facades\Log;

class StateManager
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = config('creem-agent.state_path', storage_path('creem-agent'));
    }

    public function load(string $store): array
    {
        $path = $this->statePath($store);

        if (!file_exists($path)) {
            $defaults = $this->defaults();
            $this->save($store, $defaults);
            return $defaults;
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning("[CreemAgent] Corrupted state file for store '{$store}', resetting", [
                'path' => $path,
                'error' => json_last_error_msg(),
            ]);
            @unlink($path);
            $defaults = $this->defaults();
            $this->save($store, $defaults);
            return $defaults;
        }

        // Merge missing keys from defaults
        return array_replace_recursive($this->defaults(), $data);
    }

    public function save(string $store, array $state): void
    {
        $path = $this->statePath($store);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Atomic write: write to temp, then rename
        $tmp = $path . '.tmp.' . getmypid();
        file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        rename($tmp, $path);
    }

    public function isFirstRun(array $state): bool
    {
        return $state['lastCheckAt'] === null;
    }

    public function statePath(string $store): string
    {
        return $this->basePath . "/heartbeat-{$store}.json";
    }

    public function defaults(): array
    {
        return [
            'lastCheckAt' => null,
            'lastTransactionId' => null,
            'transactionCount' => 0,
            'customerCount' => 0,
            'subscriptions' => [
                'active' => 0,
                'trialing' => 0,
                'past_due' => 0,
                'paused' => 0,
                'canceled' => 0,
                'expired' => 0,
                'scheduled_cancel' => 0,
            ],
            'knownSubscriptions' => [],
        ];
    }
}
