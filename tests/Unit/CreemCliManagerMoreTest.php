<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Cache;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Cli\CliDriverInterface;

class CreemCliManagerMoreTest extends TestCase
{
    public function test_get_profile_for_store_uses_config()
    {
        config()->set('creem-agent.stores.foo.profile', 'myprofile');

        $m = new CreemCliManager();
        $this->assertSame('myprofile', $m->getProfileForStore('foo'));

        // when no explicit profile, returns store name
        $this->assertSame('bar', $m->getProfileForStore('bar'));
    }

    public function test_invalidate_cli_cache_clears_flag_and_cache()
    {
        Cache::put('creem_cli_native_available', true, 60);

        $m = new CreemCliManager(fn() => true);
        $this->assertTrue($m->isNativeCliAvailable());

        $m->invalidateCliCache();

        $this->assertNull(Cache::get('creem_cli_native_available'));
        $this->assertTrue($m->isNativeCliAvailable());
    }

    public function test_execute_throws_when_artisan_driver_fails()
    {
        $thrower = new class implements CliDriverInterface {
            public function execute(string $resource, string $action, array $args = [], ?string $profile = null): array
            {
                throw new \RuntimeException('driver fail');
            }
        };

        $m = new CreemCliManager(fn() => false, null, $thrower);

        $this->expectException(\RuntimeException::class);
        $m->execute('products', 'list', []);
    }
}
