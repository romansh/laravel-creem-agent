<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Cache;
use Romansh\LaravelCreemAgent\Workflows\NewCustomerWelcome;
use Romansh\LaravelCreemAgent\Workflows\AnomalyDetection;
use Romansh\LaravelCreemAgent\Workflows\ChurnDetection;
use Romansh\LaravelCreemAgent\Workflows\FailedPaymentRecovery;
use Romansh\LaravelCreemAgent\Events\HeartbeatCompleted;
use Romansh\LaravelCreemAgent\Events\ChangeDetected;
use Romansh\LaravelCreemAgent\Notifications\WorkflowAlert;

class WorkflowsTest extends TestCase
{
    public function test_new_customer_welcome_triggers_notification()
    {
        Notification::fake();

        $changes = [ ['type' => 'new_customers', 'data' => ['count' => 2]] ];
        $event = new HeartbeatCompleted('default', ['customerCount' => 2], $changes);

        $w = new NewCustomerWelcome();
        $w->handle($event);

        Notification::assertSentOnDemand(WorkflowAlert::class);
    }

    public function test_anomaly_detection_triggers_when_drop_big()
    {
        Notification::fake();
        Cache::flush();

        $store = 'default';
        $key = "creem_agent_anomaly_{$store}";

        // seed history with two entries where previous active_subs is higher
        Cache::put($key, [
            ['at' => now()->toIso8601String(), 'transactions' => 0, 'active_subs' => 10],
            ['at' => now()->toIso8601String(), 'transactions' => 0, 'active_subs' => 8],
        ]);

        $state = ['transactionCount' => 0, 'subscriptions' => ['active' => 6]];
        $event = new HeartbeatCompleted($store, $state, []);

        $w = new AnomalyDetection();
        $w->handle($event);

        Notification::assertSentOnDemand(WorkflowAlert::class);
    }

    public function test_anomaly_detection_skips_when_previous_active_subs_is_zero()
    {
        Notification::fake();
        Cache::flush();

        $store = 'default';
        $key = "creem_agent_anomaly_{$store}";

        Cache::put($key, [
            ['at' => now()->toIso8601String(), 'transactions' => 0, 'active_subs' => 0],
            ['at' => now()->toIso8601String(), 'transactions' => 0, 'active_subs' => 0],
        ]);

        (new AnomalyDetection())->handle(new HeartbeatCompleted($store, [
            'transactionCount' => 0,
            'subscriptions' => ['active' => 0],
        ], []));

        Notification::assertNothingSent();
    }

    public function test_anomaly_detection_returns_early_when_history_is_short()
    {
        Notification::fake();
        Cache::flush();

        (new AnomalyDetection())->handle(new HeartbeatCompleted('default', [
            'transactionCount' => 0,
            'subscriptions' => ['active' => 5],
        ], []));

        Notification::assertNothingSent();
    }

    public function test_churn_detection_triggers_on_multiple_cancellations()
    {
        Notification::fake();

        $changes = [
            ['type' => 'subscription_alert', 'data' => ['to' => 'canceled']],
            ['type' => 'subscription_alert', 'data' => ['to' => 'canceled']],
        ];
        $event = new HeartbeatCompleted('default', [], $changes);

        $w = new ChurnDetection();
        $w->handle($event);

        Notification::assertSentOnDemand(WorkflowAlert::class);
    }

    public function test_failed_payment_recovery_triggers_on_past_due()
    {
        Notification::fake();

        $change = ['type' => 'subscription_warning', 'data' => ['to' => 'past_due', 'subscription_id' => 'sub_1']];
        $event = new ChangeDetected('default', $change);

        $w = new FailedPaymentRecovery();
        $w->handle($event);

        Notification::assertSentOnDemand(WorkflowAlert::class);
    }
}
