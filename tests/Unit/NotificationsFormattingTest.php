<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Romansh\LaravelCreemAgent\Notifications\WorkflowAlert;
use Romansh\LaravelCreemAgent\Notifications\FirstHeartbeat;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatAlert;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatSummary;
use Illuminate\Support\Facades\Notification;

class NotificationsFormattingTest extends TestCase
{
    public function test_workflow_alert_channels_and_array()
    {
        config()->set('creem-agent.notifications.slack_webhook_url', null);
        $n = new WorkflowAlert('s1', 'Wf', 'msg');
        $this->assertEquals(['database'], $n->via(null));

        config()->set('creem-agent.notifications.slack_webhook_url', 'https://hooks');
        $this->assertContains('slack', $n->via(null));
        $arr = $n->toArray(null);
        $this->assertEquals('workflow_alert', $arr['type']);
    }

    public function test_first_heartbeat_and_summary_and_alert_formats()
    {
        config()->set('creem-agent.notifications.slack_webhook_url', 'https://hooks');

        $fb = new FirstHeartbeat('s1', ['customerCount' => 2, 'transactionCount' => 1, 'subscriptions' => ['active' => 1]]);
        $this->assertContains('slack', $fb->via(null));
        $this->assertEquals('first_heartbeat', $fb->toArray(null)['type']);

        $ha = new HeartbeatAlert('s1', ['message' => 'hey', 'severity' => 'alert']);
        $this->assertEquals('heartbeat_alert', $ha->toArray(null)['type']);

        $hs = new HeartbeatSummary('s1', [['message' => 'm1'], ['message' => 'm2']]);
        $this->assertEquals(2, $hs->toArray(null)['count']);
    }
}
