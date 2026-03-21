<?php

namespace Romansh\LaravelCreemAgent\Cli;

use Illuminate\Support\Facades\Cache;
use Romansh\LaravelCreemAgent\Cli\Proxies\CustomerProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\ProductProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy;

class CreemCliManager
{
    private ?bool $nativeCliAvailable = null;
    private ?CliDriverInterface $driver = null;
    private ?string $activeStore = null;

    private const CACHE_KEY = 'creem_cli_native_available';
    private const CACHE_TTL = 86400; // 24 hours

    public function __construct(
        private ?\Closure $nativeCliDetector = null,
        private ?CliDriverInterface $nativeDriver = null,
        private ?CliDriverInterface $artisanDriver = null,
    ) {}

    public function isNativeCliAvailable(): bool
    {
        if ($this->nativeCliAvailable !== null) {
            return $this->nativeCliAvailable;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            $this->nativeCliAvailable = (bool) $cached;
            return $this->nativeCliAvailable;
        }

        $available = $this->detectNativeCli();

        $this->nativeCliAvailable = $available;
        Cache::put(self::CACHE_KEY, $available, self::CACHE_TTL);

        return $available;
    }

    public function invalidateCliCache(): void
    {
        $this->nativeCliAvailable = null;
        Cache::forget(self::CACHE_KEY);
    }

    public function getDriver(): CliDriverInterface
    {
        if ($this->driver !== null) {
            return $this->driver;
        }

        $this->driver = $this->isNativeCliAvailable()
            ? $this->makeNativeDriver()
            : $this->makeArtisanDriver();

        return $this->driver;
    }

    public function setActiveStore(?string $store): void
    {
        $this->activeStore = $store;
    }

    public function getActiveStore(): string
    {
        return $this->activeStore ?? config('creem-agent.default_store', 'default');
    }

    public function getProfileForStore(?string $store = null): string
    {
        $store = $store ?? $this->getActiveStore();
        return config("creem-agent.stores.{$store}.profile", $store);
    }

    public function execute(string $resource, string $action, array $args = [], ?string $store = null): array
    {
        $profile = $this->getProfileForStore($store);

        try {
            return $this->getDriver()->execute($resource, $action, $args, $profile);
        } catch (\Exception $e) {
            // If native CLI fails, try falling back to artisan
            if ($this->isUsingNativeDriver()) {
                $this->invalidateCliCache();
                $this->driver = $this->makeArtisanDriver();
                return $this->driver->execute($resource, $action, $args, $profile);
            }
            throw $e;
        }
    }

    public function transactions(): TransactionProxy
    {
        return new TransactionProxy($this);
    }

    public function subscriptions(): SubscriptionProxy
    {
        return new SubscriptionProxy($this);
    }

    public function customers(): CustomerProxy
    {
        return new CustomerProxy($this);
    }

    public function products(): ProductProxy
    {
        return new ProductProxy($this);
    }

    private function detectNativeCli(): bool
    {
        if ($this->nativeCliDetector !== null) {
            return (bool) ($this->nativeCliDetector)();
        }

        $result = @shell_exec('which creem 2>/dev/null');
            $result = @shell_exec('command -v creem 2>/dev/null');
            $available = !empty(trim($result ?? ''));        

        if ($available) {
            $whoami = @shell_exec('creem whoami --json 2>/dev/null');
            $available = !empty($whoami) && json_decode($whoami) !== null;
        }

        return $available;
    }

    private function makeNativeDriver(): CliDriverInterface
    {
        return $this->nativeDriver ?? new NativeCliDriver();
    }

    private function makeArtisanDriver(): CliDriverInterface
    {
        return $this->artisanDriver ?? new ArtisanCliDriver();
    }

    private function isUsingNativeDriver(): bool
    {
        return $this->driver instanceof NativeCliDriver
            || ($this->nativeDriver !== null && $this->driver === $this->nativeDriver);
    }
}
