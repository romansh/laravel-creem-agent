<?php

namespace Romansh\LaravelCreemAgent\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\CreemAgentServiceProvider;
use Romansh\LaravelCreemAgent\Heartbeat\StateManager;

class HeartbeatResetCommandTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CreemAgentServiceProvider::class];
    }

    public function test_heartbeat_reset_command_resets_default_store_state()
    {
        $dir = sys_get_temp_dir() . '/creem-heartbeat-reset-default-' . uniqid();
        @mkdir($dir, 0755, true);

        config()->set('creem-agent.state_path', $dir);
        config()->set('creem-agent.default_store', 'demo');

        $manager = $this->app->make(StateManager::class);
        $manager->save('demo', [
            'lastCheckAt' => '2026-01-01T00:00:00Z',
            'transactionCount' => 7,
            'customerCount' => 5,
            'subscriptions' => ['active' => 3],
            'knownSubscriptions' => ['sub_1'],
        ]);

        $this->artisan('creem-agent:heartbeat-reset')
            ->expectsOutput('[CreemAgent] Heartbeat state reset for store: demo')
            ->expectsOutput('The next heartbeat run will behave like a first run.')
            ->assertExitCode(0);

        $this->assertSame($manager->defaults(), $manager->load('demo'));
    }

    public function test_heartbeat_reset_command_resets_all_configured_stores()
    {
        $dir = sys_get_temp_dir() . '/creem-heartbeat-reset-all-' . uniqid();
        @mkdir($dir, 0755, true);

        config()->set('creem-agent.state_path', $dir);
        config()->set('creem-agent.stores', [
            'alpha' => ['profile' => 'alpha'],
            'beta' => ['profile' => 'beta'],
        ]);

        $manager = $this->app->make(StateManager::class);
        $manager->save('alpha', [
            'lastCheckAt' => '2026-01-01T00:00:00Z',
            'transactionCount' => 2,
            'customerCount' => 1,
            'subscriptions' => ['active' => 1],
            'knownSubscriptions' => ['sub_alpha'],
        ]);
        $manager->save('beta', [
            'lastCheckAt' => '2026-01-01T00:00:00Z',
            'transactionCount' => 4,
            'customerCount' => 3,
            'subscriptions' => ['active' => 2],
            'knownSubscriptions' => ['sub_beta'],
        ]);

        $this->artisan('creem-agent:heartbeat-reset', ['--all-stores' => true])
            ->expectsOutput('[CreemAgent] Heartbeat state reset for store: alpha')
            ->expectsOutput('[CreemAgent] Heartbeat state reset for store: beta')
            ->expectsOutput('The next heartbeat run for each store will behave like a first run.')
            ->assertExitCode(0);

        $this->assertSame($manager->defaults(), $manager->load('alpha'));
        $this->assertSame($manager->defaults(), $manager->load('beta'));
    }
}