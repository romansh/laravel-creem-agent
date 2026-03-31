<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Heartbeat\StateManager;

class StateManagerTest extends TestCase
{
    public function test_load_missing_file_creates_defaults_and_marks_first_run()
    {
        $dir = sys_get_temp_dir() . '/creem-state-missing-' . uniqid();
        config()->set('creem-agent.state_path', $dir);

        $manager = new StateManager();
        $state = $manager->load('alpha');

        $this->assertTrue($manager->isFirstRun($state));
        $this->assertFileExists($manager->statePath('alpha'));
        $this->assertSame($manager->defaults()['subscriptions']['paused'], $state['subscriptions']['paused']);
    }

    public function test_load_resets_corrupted_state_and_merges_missing_keys()
    {
        $dir = sys_get_temp_dir() . '/creem-state-corrupt-' . uniqid();
        @mkdir($dir, 0755, true);
        config()->set('creem-agent.state_path', $dir);

        $manager = new StateManager();
        file_put_contents($manager->statePath('broken'), '{invalid-json');

        $reset = $manager->load('broken');
        $this->assertNull($reset['lastCheckAt']);
        $this->assertArrayHasKey('knownSubscriptions', $reset);

        $manager->save('partial', [
            'lastCheckAt' => '2026-01-01T00:00:00Z',
            'subscriptions' => ['active' => 3],
        ]);

        $merged = $manager->load('partial');
        $this->assertSame('2026-01-01T00:00:00Z', $merged['lastCheckAt']);
        $this->assertSame(3, $merged['subscriptions']['active']);
        $this->assertSame(0, $merged['subscriptions']['expired']);
        $this->assertFalse($manager->isFirstRun($merged));
    }

    public function test_reset_rewrites_state_to_defaults()
    {
        $dir = sys_get_temp_dir() . '/creem-state-reset-' . uniqid();
        @mkdir($dir, 0755, true);
        config()->set('creem-agent.state_path', $dir);

        $manager = new StateManager();
        $manager->save('alpha', [
            'lastCheckAt' => '2026-01-01T00:00:00Z',
            'lastTransactionId' => 'txn_123',
            'transactionCount' => 99,
            'customerCount' => 42,
            'subscriptions' => [
                'active' => 10,
                'trialing' => 4,
                'past_due' => 1,
                'paused' => 2,
                'canceled' => 3,
                'expired' => 5,
                'scheduled_cancel' => 6,
            ],
            'knownSubscriptions' => ['sub_1'],
        ]);

        $reset = $manager->reset('alpha');

        $this->assertSame($manager->defaults(), $reset);
        $this->assertSame($manager->defaults(), $manager->load('alpha'));
        $this->assertTrue($manager->isFirstRun($reset));
    }
}
