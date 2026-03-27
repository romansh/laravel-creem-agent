<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CliDriverInterface;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Cli\Proxies\CustomerProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\ProductProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\SubscriptionProxy;
use Romansh\LaravelCreemAgent\Cli\Proxies\TransactionProxy;

class CreemCliManagerCoverageTest extends TestCase
{
    private string $tmpDir;
    private string $originalPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalPath = getenv('PATH') ?: '';
        $this->tmpDir = sys_get_temp_dir() . '/creem-manager-' . uniqid();
        @mkdir($this->tmpDir, 0700, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpDir . '/creem');
        @rmdir($this->tmpDir);
        putenv('PATH=' . $this->originalPath);
        Cache::flush();
        parent::tearDown();
    }

    public function test_native_cli_availability_uses_property_cache_and_store_cache()
    {
        $calls = 0;
        $manager = new CreemCliManager(function () use (&$calls) {
            $calls++;
            return true;
        });

        $this->assertTrue($manager->isNativeCliAvailable());
        $this->assertTrue($manager->isNativeCliAvailable());
        $this->assertSame(1, $calls);

        Cache::put('creem_cli_native_available', false, 60);

        $cachedManager = new CreemCliManager(function () {
            throw new \RuntimeException('detector should not be called');
        });

        $this->assertFalse($cachedManager->isNativeCliAvailable());
    }

    public function test_native_cli_availability_handles_missing_cli_and_invalid_whoami_json()
    {
        Cache::flush();
        putenv('PATH=' . $this->tmpDir);

        $manager = new CreemCliManager();
        $this->assertFalse($manager->isNativeCliAvailable());

        Cache::flush();
        file_put_contents($this->tmpDir . '/creem', "#!/bin/bash\nif [[ \"$1\" == \"whoami\" ]]; then echo not-json; exit 0; fi\necho ok\n");
        chmod($this->tmpDir . '/creem', 0755);
        putenv('PATH=' . $this->tmpDir . PATH_SEPARATOR . getenv('PATH'));

        $manager2 = new CreemCliManager();
        $this->assertFalse($manager2->isNativeCliAvailable());
    }

    public function test_native_cli_availability_detects_real_shell_script()
    {
        Cache::flush();
        file_put_contents($this->tmpDir . '/creem', "#!/bin/sh\nif [ \"$1\" = \"whoami\" ]; then\n  echo '{\"id\":\"usr_1\"}'\n  exit 0\nfi\necho ok\n");
        chmod($this->tmpDir . '/creem', 0755);
        putenv('PATH=' . $this->tmpDir . PATH_SEPARATOR . getenv('PATH'));

        $manager = new CreemCliManager();

        $this->assertTrue($manager->isNativeCliAvailable());
    }

    public function test_native_cli_availability_accepts_valid_whoami_json()
    {
        $manager = new CreemCliManager(fn() => true);

        $this->assertTrue($manager->isNativeCliAvailable());
    }

    public function test_active_store_default_and_proxy_builders()
    {
        config()->set('creem-agent.default_store', 'main');
        $manager = new CreemCliManager();

        $this->assertSame('main', $manager->getActiveStore());
        $manager->setActiveStore('secondary');
        $this->assertSame('secondary', $manager->getActiveStore());

        $this->assertInstanceOf(TransactionProxy::class, $manager->transactions());
        $this->assertInstanceOf(SubscriptionProxy::class, $manager->subscriptions());
        $this->assertInstanceOf(CustomerProxy::class, $manager->customers());
        $this->assertInstanceOf(ProductProxy::class, $manager->products());
        $this->assertSame('secondary', $manager->getProfileForStore());
    }

    public function test_execute_falls_back_to_injected_artisan_driver_after_native_failure()
    {
        $native = new class implements CliDriverInterface {
            public function execute(string $resource, string $action, array $args = [], ?string $profile = null): array
            {
                throw new \RuntimeException('native failed');
            }
        };

        $artisan = new class implements CliDriverInterface {
            public array $calls = [];

            public function execute(string $resource, string $action, array $args = [], ?string $profile = null): array
            {
                $this->calls[] = compact('resource', 'action', 'args', 'profile');
                return ['ok' => true];
            }
        };

        config()->set('creem-agent.stores.default.profile', 'main-profile');

        $manager = new CreemCliManager(fn() => true, $native, $artisan);
        $result = $manager->execute('products', 'list');
        $nextResult = $manager->execute('products', 'list');

        $this->assertSame(['ok' => true], $result);
        $this->assertSame(['ok' => true], $nextResult);
        $this->assertSame('main-profile', $artisan->calls[0]['profile']);
        $this->assertCount(2, $artisan->calls);
    }
}
