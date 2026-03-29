<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Support\TelegramModeResolver;

class TelegramWebhookRouteRegistrationTest extends TestCase
{
    public function test_registers_telegram_webhook_route_in_laravel_mode(): void
    {
        config()->set('creem-agent.telegram.mode', 'laravel');

        $routes = $this->reloadAgentRoutes();

        $this->assertTrue(collect($routes)->contains(function ($route): bool {
            return $route->uri() === 'creem-agent/telegram/webhook'
                && in_array('POST', $route->methods(), true);
        }));
    }

    public function test_skips_telegram_webhook_route_in_openclaw_mode(): void
    {
        config()->set('creem-agent.telegram.mode', 'openclaw');

        $routes = $this->reloadAgentRoutes();

        $this->assertFalse(collect($routes)->contains(function ($route): bool {
            return $route->uri() === 'creem-agent/telegram/webhook'
                && in_array('POST', $route->methods(), true);
        }));
    }

    private function reloadAgentRoutes(): array
    {
        $router = $this->app['router'];
        $router->setRoutes(new RouteCollection());
        $this->app->instance(TelegramModeResolver::class, new TelegramModeResolver());

        require dirname(__DIR__, 2).'/routes/agent.php';

        return $router->getRoutes()->getRoutes();
    }
}