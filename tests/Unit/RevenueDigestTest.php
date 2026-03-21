<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Workflows\RevenueDigest;
use Romansh\LaravelCreemAgent\Events\HeartbeatCompleted;
use Illuminate\Support\Facades\Notification;
use Romansh\LaravelCreemAgent\Notifications\WorkflowAlert;

class RevenueDigestTest extends TestCase
{
    public function test_no_changes_does_nothing()
    {
        Notification::fake();
        $w = new RevenueDigest();
        $w->handle(new HeartbeatCompleted('s1', [], []));
        Notification::assertNothingSent();
    }

    public function test_with_transactions_sends_workflow_alert()
    {
        Notification::fake();

        $changes = [
            ['type' => 'new_transaction', 'data' => ['amount' => 1000]],
            ['type' => 'new_transaction', 'data' => ['amount' => 2500]],
        ];

        $w = new RevenueDigest();
        $w->handle(new HeartbeatCompleted('s1', [], $changes));

        $sent = Notification::getFacadeRoot()->sentNotifications();
        $this->assertNotEmpty($sent);
    }
}
