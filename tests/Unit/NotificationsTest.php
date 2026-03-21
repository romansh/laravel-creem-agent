<?php

namespace Romansh\LaravelCreemAgent\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Facades\Notification;
use Romansh\LaravelCreemAgent\Notifications\FirstHeartbeat;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatAlert;
use Romansh\LaravelCreemAgent\Notifications\HeartbeatSummary;

class NotificationsTest extends TestCase
{
    public function test_first_heartbeat_channels_and_array()
    {
        Notification::fake();

        config(['creem-agent.notifications.slack_webhook_url' => null]);

        $n = new FirstHeartbeat('default', ['customerCount' => 1, 'subscriptions' => ['active' => 2]]);

        $this->assertEquals(['database'], $n->via(null));
        $arr = $n->toArray(null);
        $this->assertEquals('first_heartbeat', $arr['type']);

        config(['creem-agent.notifications.slack_webhook_url' => 'https://hooks.test']);
        $this->assertContains('slack', $n->via(null));
    }

    public function test_heartbeat_alert_and_summary_to_slack_and_array()
    {
        $alert = new HeartbeatAlert('default', ['severity' => 'alert', 'message' => 'something bad']);
        $this->assertEquals('heartbeat_alert', $alert->toArray(null)['type']);

        $summary = new HeartbeatSummary('default', [['message' => 'a'], ['message' => 'b']]);
        $this->assertEquals(2, $summary->toArray(null)['count']);
    }
}
