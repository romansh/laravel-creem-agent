<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Cli\CliDriverInterface;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;
use Romansh\LaravelCreemAgent\Cli\NativeCliDriver;

class CreemCliManagerExtendedTest extends TestCase
{
    public function test_get_driver_prefers_native_when_available()
    {
        $native = new class extends NativeCliDriver {
            public function execute(string $resource, string $action, array $args = [], ?string $profile = null): array
            {
                return [];
            }
        };

        $m = new CreemCliManager(fn() => true, $native);
        $driver = $m->getDriver();

        $this->assertSame($native, $driver);
        $this->assertSame($driver, $m->getDriver());
    }

    public function test_execute_falls_back_to_artisan_on_native_failure()
    {
        $native = new class extends NativeCliDriver {
            public function execute(string $resource, string $action, array $args = [], ?string $profile = null): array
            {
                throw new \RuntimeException('native fail');
            }
        };

        $artisan = new class implements CliDriverInterface {
            public function execute(string $resource, string $action, array $args = [], ?string $profile = null): array
            {
                return ['items' => []];
            }
        };

        $manager = new CreemCliManager(fn() => true, $native, $artisan);
        $res = $manager->execute('products', 'list', []);
        $this->assertIsArray($res);
    }
}
