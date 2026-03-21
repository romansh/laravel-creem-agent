<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Illuminate\Support\Facades\Notification;
use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Events\ChangeDetected;
use Romansh\LaravelCreemAgent\Events\HeartbeatCompleted;
use Romansh\LaravelCreemAgent\Workflows\FailedPaymentRecovery;
use Romansh\LaravelCreemAgent\Workflows\NewCustomerWelcome;

class WorkflowsEdgeCasesTest extends TestCase
{
    public function test_new_customer_welcome_returns_without_positive_count()
    {
        Notification::fake();

        (new NewCustomerWelcome())->handle(new HeartbeatCompleted('default', [], []));
        (new NewCustomerWelcome())->handle(new HeartbeatCompleted('default', [], [
            ['type' => 'new_customers', 'data' => ['count' => 0]],
        ]));

        Notification::assertNothingSent();
    }

    public function test_failed_payment_recovery_returns_for_non_matching_changes()
    {
        Notification::fake();

        (new FailedPaymentRecovery())->handle(new ChangeDetected('default', [
            'type' => 'subscription_info',
            'data' => ['to' => 'past_due'],
        ]));

        (new FailedPaymentRecovery())->handle(new ChangeDetected('default', [
            'type' => 'subscription_warning',
            'data' => ['to' => 'active'],
        ]));

        Notification::assertNothingSent();
    }
}
