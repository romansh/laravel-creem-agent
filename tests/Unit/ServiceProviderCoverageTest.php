<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Agent\AgentManager;
use Romansh\LaravelCreemAgent\CreemAgentServiceProvider;

class ServiceProviderCoverageTest extends TestCase
{
    public function test_service_provider_registers_bindings_and_boots_console_hooks()
    {
        $provider = new class($this->app) extends CreemAgentServiceProvider {
            public array $capturedPublishes = [];
            public array $capturedCommands = [];
            public array $capturedRoutes = [];

            protected function publishes(array $paths, $groups = null)
            {
                $this->capturedPublishes[] = [$groups, $paths];
            }

            public function commands($commands)
            {
                $this->capturedCommands = $commands;
            }

            protected function loadRoutesFrom($path)
            {
                $this->capturedRoutes[] = $path;
            }
        };

        $provider->register();

        $this->assertTrue($this->app->bound('creem-cli'));
        $this->assertTrue($this->app->bound(AgentManager::class));

        $provider->boot();

        $this->assertNotEmpty($provider->capturedPublishes);
        $this->assertCount(6, $provider->capturedCommands);
        $this->assertCount(1, $provider->capturedRoutes);
    }

    public function test_service_provider_boots_routes_when_not_running_in_console()
    {
        $app = $this->getMockBuilder(\Illuminate\Foundation\Application::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['runningInConsole'])
            ->getMock();

        $app->method('runningInConsole')->willReturn(false);

        $provider = new class($app) extends CreemAgentServiceProvider {
            public array $capturedRoutes = [];

            protected function loadRoutesFrom($path)
            {
                $this->capturedRoutes[] = $path;
            }
        };

        $provider->boot();

        $this->assertCount(1, $provider->capturedRoutes);
    }

    public function test_original_service_provider_registers_creem_cli_and_routes()
    {
        $provider = new CreemAgentServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertInstanceOf(\Romansh\LaravelCreemAgent\Cli\CreemCliManager::class, $this->app->make('creem-cli'));
        $this->assertTrue($this->app->bound('creem-cli'));
    }
}